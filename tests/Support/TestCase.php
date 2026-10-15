<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Support;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\Credential;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\InMemoryTokenStore;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\TokenStore;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected FakeHttpClient $http;

    protected FakeClock $clock;

    protected MemoryLogger $logger;

    protected TokenStore $store;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->clock = new FakeClock();
        $this->logger = new MemoryLogger();
        $this->store = new InMemoryTokenStore();
    }

    protected function client(?ClientConfig $config = null, ?Credential $credential = null, bool $semCredencial = false): PortalUnicoClient
    {
        $factory = new HttpFactory();

        return PortalUnicoClient::create(
            credencial: $semCredencial ? null : ($credential ?? self::chave()),
            cache: $this->store,
            logger: $this->logger,
            config: $config ?? new ClientConfig(),
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            clock: $this->clock,
            sleeper: $this->clock,
        );
    }

    protected static function chave(): ChaveAcesso
    {
        return new ChaveAcesso('client-id-teste', 'client-secret-teste');
    }

    /**
     * Resposta de autenticação bem-sucedida.
     */
    protected static function authResponse(string $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.assinatura', string $csrf = 'csrf-1', ?int $expirationMs = null): Response
    {
        $headers = ['Set-Token' => $jwt, 'X-CSRF-Token' => $csrf];
        if ($expirationMs !== null) {
            $headers['X-CSRF-Expiration'] = (string) $expirationMs;
        }

        return new Response(200, $headers + ['Content-Type' => 'application/json'], json_encode(['token' => $csrf], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<mixed>|null            $body
     * @param array<string, string> $headers
     */
    protected static function json(int $status, ?array $body, array $headers = []): Response
    {
        return new Response($status, $headers + ['Content-Type' => 'application/json'], $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected static function error(int $status, string $code, string $message = 'Erro', string $tag = '[TAG123]'): Response
    {
        return self::json($status, ['message' => $message, 'code' => $code, 'tag' => $tag, 'status' => $status, 'severity' => 'ERROR']);
    }

    /** @return array<mixed> */
    protected static function body(RequestInterface $request): array
    {
        $data = json_decode((string) $request->getBody(), true);

        return \is_array($data) ? $data : [];
    }

    /** @return array<mixed> */
    protected static function fixture(string $ambiente, string $arquivo): array
    {
        $data = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/'.$ambiente.'/'.$arquivo), true);

        return \is_array($data) ? $data : [];
    }

    protected static function fixturePath(string $ambiente, string $arquivo): string
    {
        return __DIR__.'/../Fixtures/'.$ambiente.'/'.$arquivo;
    }

    protected static function zip(string $nome, string $conteudo): string
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'zip-test-');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString($nome, $conteudo);
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
