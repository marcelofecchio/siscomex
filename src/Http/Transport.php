<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Authenticator;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ProductionWriteBlockedException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Support\Sleeper;
use MarceloFecchio\Siscomex\PortalUnico\Support\Zip;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Executa as requisições à API:
 *
 * - injeta Authorization (JWT) e X-CSRF-Token;
 * - captura o X-CSRF-Token renovado de toda resposta;
 * - em 401, reautentica uma única vez e repete a requisição;
 * - repete com backoff exponencial apenas em 503 (exceto PUCX-ER0401) e em erros de rede
 *   de requisições GET; nunca em 4xx nem em PUCX-ER1001;
 * - recusa escrita em produção quando a proteção estiver ativa;
 * - com ClientConfig::$simularEscrita, não envia escritas (veja escritasSimuladas()).
 */
final class Transport
{
    private const array METODOS_LEITURA = ['GET', 'HEAD'];

    /** Header das respostas geradas pela simulação de escrita. */
    public const string HEADER_SIMULACAO = 'X-Siscomex-Simulacao';

    /** @var list<EscritaSimulada> */
    private array $escritasSimuladas = [];

    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly ClientConfig $config,
        private readonly ?Authenticator $authenticator,
        private readonly ClockInterface $clock,
        private readonly Sleeper $sleeper,
        private readonly LoggerInterface $logger,
    ) {}

    public function config(): ClientConfig
    {
        return $this->config;
    }

    /**
     * Escritas não enviadas por ClientConfig::$simularEscrita, na ordem em que ocorreram.
     *
     * @return list<EscritaSimulada>
     */
    public function escritasSimuladas(): array
    {
        return $this->escritasSimuladas;
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query    arrays viram chaves repetidas (collectionFormat=multi)
     * @param array<string, string>                   $headers
     * @param bool|null                               $readOnly força a classificação como leitura (ex.: consultas via POST);
     *                                                          null = deduz pelo método
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        mixed $json = null,
        array $headers = [],
        bool $authenticated = true,
        ?bool $readOnly = null,
    ): ResponseInterface {
        $method = strtoupper($method);
        $isRead = $readOnly ?? \in_array($method, self::METODOS_LEITURA, true);

        if (!$isRead && $this->config->simularEscrita) {
            return $this->simularEscrita($method, $path, $query, $json);
        }

        if (!$isRead && $this->config->escritaBloqueada()) {
            throw new ProductionWriteBlockedException(\sprintf(
                'Escrita bloqueada em produção (%s %s). A proteção é ativada por SISCOMEX_RUN_INTEGRATION=1 ou ClientConfig::$bloquearEscritaEmProducao.',
                $method,
                $path,
            ));
        }
        $authenticator = $authenticated
            ? ($this->authenticator ?? throw new \LogicException('Esta operação exige autenticação: informe uma credencial ao criar o PortalUnicoClient.'))
            : null;

        $url = $this->config->ambiente->baseUrl().$path.self::buildQuery($query);
        $body = $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $attempt = 0;
        $reauthenticated = false;

        while (true) {
            $jwt = null;
            $request = $this->buildRequest($method, $url, $body, $headers);
            if ($authenticator !== null) {
                $session = $authenticator->session();
                $jwt = $session->jwt;
                $request = $request
                    ->withHeader('Authorization', $session->jwt)
                    ->withHeader('X-CSRF-Token', $session->csrfToken);
            }

            $start = microtime(true);

            try {
                $response = $this->http->sendRequest($request);
            } catch (NetworkExceptionInterface $e) {
                if ($isRead && $attempt < $this->config->tentativas503) {
                    $this->backoff(++$attempt, 'erro de rede: '.$e->getMessage());
                    continue;
                }
                throw new TransportException('Falha de rede: '.$e->getMessage(), previous: $e);
            } catch (ClientExceptionInterface $e) {
                throw new TransportException('Falha no cliente HTTP: '.$e->getMessage(), previous: $e);
            }

            $status = $response->getStatusCode();
            $this->logger->debug('{method} {url} -> {status} ({ms} ms)', [
                'method' => $method,
                'url' => $url,
                'status' => $status,
                'ms' => (int) round((microtime(true) - $start) * 1000),
            ]);

            if ($authenticator !== null) {
                $csrf = $response->getHeaderLine('X-CSRF-Token');
                if ($csrf !== '') {
                    $expiration = $response->getHeaderLine('X-CSRF-Expiration');
                    $authenticator->updateCsrf($csrf, $expiration === '' ? null : $expiration);
                }
            }

            if ($status === 401 && $authenticator !== null && !$reauthenticated) {
                $reauthenticated = true;
                $this->logger->notice('HTTP 401: sessão recusada; reautenticando uma única vez.');
                $authenticator->authenticate($jwt);
                continue;
            }

            if ($status < 400) {
                return $response;
            }

            $error = ErrorMapper::fromResponse($response, $this->clock->now());
            if ($status === 503 && $error->codigoErro !== 'PUCX-ER0401' && $attempt < $this->config->tentativas503) {
                $this->backoff(++$attempt, 'HTTP 503');
                continue;
            }

            $this->logError($error, $method, $url);
            throw $error;
        }
    }

    /**
     * Executa a requisição e decodifica o JSON da resposta (null para 204/corpo vazio).
     *
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function json(
        string $method,
        string $path,
        array $query = [],
        mixed $json = null,
        bool $authenticated = true,
        ?bool $readOnly = null,
    ): mixed {
        $response = $this->request($method, $path, $query, $json, ['Accept' => 'application/json'], $authenticated, $readOnly);

        return self::decode($response);
    }

    public static function decode(ResponseInterface $response): mixed
    {
        $raw = (string) $response->getBody();
        if ($response->getStatusCode() === 204 || trim($raw) === '') {
            return null;
        }

        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new SiscomexException('Resposta do portal não é um JSON válido: '.$e->getMessage(), $response->getStatusCode(), previous: $e);
        }
    }

    /**
     * Decodifica exportações/downloads: JSON puro ou ZIP com um JSON dentro (null para 204/corpo vazio).
     */
    public static function decodeArquivo(ResponseInterface $response): mixed
    {
        $bytes = (string) $response->getBody();
        if ($response->getStatusCode() === 204 || trim($bytes) === '') {
            return null;
        }

        try {
            return json_decode(Zip::primeiroArquivo($bytes), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new SiscomexException('Arquivo devolvido pelo portal não contém um JSON válido: '.$e->getMessage(), $response->getStatusCode(), previous: $e);
        }
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public static function buildQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }
            foreach (\is_array($value) ? $value : [$value] as $item) {
                $item = \is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                $parts[] = rawurlencode($name).'='.rawurlencode($item);
            }
        }

        return $parts === [] ? '' : '?'.implode('&', $parts);
    }

    /**
     * Registra a escrita sem enviá-la e devolve o próprio corpo (200) ou 204 sem corpo.
     * Não autentica: a simulação funciona sem credencial e sem rede.
     *
     * @param array<string, scalar|list<scalar>|null> $query
     */
    private function simularEscrita(string $method, string $path, array $query, mixed $json): ResponseInterface
    {
        $url = $this->config->ambiente->baseUrl().$path.self::buildQuery($query);
        $this->escritasSimuladas[] = new EscritaSimulada($method, $path, $url, $json, $this->clock->now());
        $this->logger->info('Escrita simulada (não enviada): {method} {url}', ['method' => $method, 'url' => $url]);

        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse($json === null ? 204 : 200)
            ->withHeader(self::HEADER_SIMULACAO, '1');
        if ($json === null) {
            return $response;
        }

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream(json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }

    /** @param array<string, string> $headers */
    private function buildRequest(string $method, string $url, ?string $body, array $headers): RequestInterface
    {
        $request = $this->requests->createRequest($method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->userAgent);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streams->createStream($body));
        }

        return $request;
    }

    private function backoff(int $attempt, string $reason): void
    {
        $ms = $this->config->backoffInicialMs * (2 ** ($attempt - 1));
        $this->logger->warning('Repetindo requisição em {ms} ms (tentativa {n}, motivo: {reason}).', [
            'ms' => $ms,
            'n' => $attempt,
            'reason' => LogMasker::mascararTexto($reason),
        ]);
        $this->sleeper->sleepMs($ms);
    }

    private function logError(SiscomexException $error, string $method, string $url): void
    {
        $this->logger->error('{method} {url} falhou: {resumo}', [
            'method' => $method,
            'url' => $url,
            'resumo' => LogMasker::mascararTexto($error->resumoParaChamado()),
        ]);
    }
}
