<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Auth;

use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\Credential;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\TokenStore;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Config\PoliticaIntervaloAutenticacao;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationThrottledException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\CertificateException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Http\ErrorMapper;
use MarceloFecchio\Siscomex\PortalUnico\Support\Sleeper;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Obtém e mantém a sessão no portal, respeitando as regras de autenticação:
 *
 * - reaproveita a sessão válida do TokenStore (nunca autentica por requisição);
 * - nunca autentica duas vezes em menos de 60 s (aguarda ou lança exceção);
 * - serializa autenticações concorrentes com o lock do TokenStore.
 */
final class Authenticator
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly Credential $credential,
        private readonly ClientConfig $config,
        private readonly TokenStore $store,
        private readonly ClockInterface $clock,
        private readonly Sleeper $sleeper,
        private readonly LoggerInterface $logger,
    ) {}

    /** Chave da sessão no TokenStore: ambiente + perfil + credencial. */
    public function sessionKey(): string
    {
        return \sprintf('%s|%s|%s', $this->config->ambiente->value, $this->config->perfil->value, $this->credential->identificador());
    }

    /** Sessão válida atual (do cache) ou uma nova autenticação. */
    public function session(): Session
    {
        $session = $this->store->getSession($this->sessionKey());
        if ($session !== null && $session->isValid($this->clock->now(), $this->config->margemExpiracao)) {
            return $session;
        }

        return $this->authenticate();
    }

    /**
     * Força nova autenticação. Se outro processo já renovou a sessão enquanto
     * aguardávamos o lock (JWT diferente de $rejectedJwt), reaproveita-a.
     */
    public function authenticate(?string $rejectedJwt = null): Session
    {
        $key = $this->sessionKey();

        return $this->store->withLock($key, function () use ($key, $rejectedJwt): Session {
            $current = $this->store->getSession($key);
            if (
                $current !== null
                && $current->jwt !== $rejectedJwt
                && $current->isValid($this->clock->now(), $this->config->margemExpiracao)
            ) {
                return $current;
            }

            $this->respectMinimumInterval($key);

            return $this->doAuthenticate($key);
        });
    }

    /** Registra o X-CSRF-Token renovado devolvido em qualquer resposta. */
    public function updateCsrf(string $csrfToken, ?string $expirationHeader): void
    {
        $key = $this->sessionKey();
        $session = $this->store->getSession($key);
        if ($session === null || $session->csrfToken === $csrfToken) {
            return;
        }
        $this->store->saveSession($key, $session->withCsrf(
            $csrfToken,
            Session::parseExpiration($expirationHeader, $this->clock->now()),
        ));
    }

    public function invalidate(): void
    {
        $this->store->deleteSession($this->sessionKey());
    }

    private function respectMinimumInterval(string $key): void
    {
        $last = $this->store->getLastAuthentication($key);
        if ($last === null) {
            return;
        }

        $intervalo = ClientConfig::INTERVALO_MINIMO_AUTENTICACAO;
        $elapsedMs = (int) round(((float) $this->clock->now()->format('U.u') - (float) $last->format('U.u')) * 1000);
        $remainingMs = $intervalo * 1000 - $elapsedMs;
        if ($remainingMs <= 0) {
            return;
        }

        $allowedAt = $last->modify("+{$intervalo} seconds");
        if ($this->config->politicaIntervalo === PoliticaIntervaloAutenticacao::LancarExcecao) {
            throw AuthenticationThrottledException::local($allowedAt);
        }

        $this->logger->notice('Aguardando {ms} ms para respeitar o intervalo mínimo de 60 s entre autenticações.', ['ms' => $remainingMs + 500]);
        $this->sleeper->sleepMs($remainingMs + 500);
    }

    private function doAuthenticate(string $key): Session
    {
        $url = $this->config->ambiente->baseUrl().$this->credential->endpointAutenticacao();
        $request = $this->requests->createRequest('POST', $url)
            ->withHeader('Role-Type', $this->config->perfil->value)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->userAgent);
        foreach ($this->credential->headersAutenticacao() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->logger->info('Autenticando no Portal Único ({ambiente}, perfil {perfil}).', [
            'ambiente' => $this->config->ambiente->value,
            'perfil' => $this->config->perfil->value,
        ]);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            if ($this->credential->exigeMtls() && preg_match('/ssl|certificate|handshake|tls/i', $e->getMessage()) === 1) {
                throw new CertificateException('Falha no handshake mTLS: '.$e->getMessage(), previous: $e);
            }
            throw new TransportException('Falha de comunicação ao autenticar: '.$e->getMessage(), previous: $e);
        }

        $now = $this->clock->now();
        if ($response->getStatusCode() >= 400) {
            $error = ErrorMapper::fromResponse($response, $now);
            if ($error instanceof AuthenticationThrottledException) {
                // O portal considera que já houve autenticação recente: aguarde 60 s a partir de agora.
                $this->store->saveLastAuthentication($key, $now);
            }
            $this->logger->warning('Falha na autenticação: {resumo}', ['resumo' => $error->resumoParaChamado()]);
            throw $error;
        }

        $session = $this->parseSession($response, $now);
        $this->store->saveLastAuthentication($key, $now);
        $this->store->saveSession($key, $session);
        $this->logger->info('Autenticado no Portal Único; CSRF expira em {exp}.', ['exp' => $session->csrfExpiresAt->format(\DATE_ATOM)]);

        return $session;
    }

    private function parseSession(ResponseInterface $response, \DateTimeImmutable $now): Session
    {
        $jwt = $response->getHeaderLine('Set-Token');
        $csrf = $response->getHeaderLine('X-CSRF-Token');
        if ($csrf === '') {
            $body = json_decode((string) $response->getBody(), true);
            $csrf = \is_array($body) && \is_string($body['token'] ?? null) ? $body['token'] : '';
        }
        if ($jwt === '' || $csrf === '') {
            throw new AuthenticationException(
                'Resposta de autenticação sem os headers Set-Token/X-CSRF-Token.',
                httpStatus: $response->getStatusCode(),
            );
        }

        $expiration = $response->getHeaderLine('X-CSRF-Expiration');

        return new Session(
            $jwt,
            $csrf,
            Session::parseExpiration($expiration === '' ? null : $expiration, $now),
            $now,
        );
    }
}
