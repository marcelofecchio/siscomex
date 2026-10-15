<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Auth;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\Config\PoliticaIntervaloAutenticacao;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationThrottledException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class AuthenticatorTest extends TestCase
{
    public function testAutenticaComChaveDeAcessoEnviandoHeadersDocumentados(): void
    {
        $this->http->queue(self::authResponse());

        $session = $this->client()->autenticar();

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://val.portalunico.siscomex.gov.br/portal/api/autenticar/chave-acesso', (string) $request->getUri());
        self::assertSame('IMPEXP', $request->getHeaderLine('Role-Type'));
        self::assertSame('client-id-teste', $request->getHeaderLine('Client-Id'));
        self::assertSame('client-secret-teste', $request->getHeaderLine('Client-Secret'));
        self::assertSame('csrf-1', $session->csrfToken);
        self::assertStringStartsWith('eyJ', $session->jwt);
    }

    public function testUsaUrlDeProducaoEPerfilConfigurado(): void
    {
        $this->http->queue(self::authResponse());

        $this->client(new ClientConfig(ambiente: Ambiente::Producao, perfil: Perfil::DEPOSIT))->autenticar();

        $request = $this->http->lastRequest();
        self::assertSame('portalunico.siscomex.gov.br', $request->getUri()->getHost());
        self::assertSame('DEPOSIT', $request->getHeaderLine('Role-Type'));
    }

    public function testReaproveitaSessaoValidaSemNovaAutenticacao(): void
    {
        $this->http->queue(self::authResponse());
        $client = $this->client();

        $s1 = $client->autenticar();
        $this->clock->advance(600);
        $s2 = $client->autenticar();

        self::assertCount(1, $this->http->requests);
        self::assertSame($s1->jwt, $s2->jwt);
    }

    public function testUsaExpiracaoDoHeaderEmMilissegundos(): void
    {
        $expira = $this->clock->now()->modify('+30 minutes')->getTimestamp() * 1000;
        $this->http->queue(self::authResponse(expirationMs: $expira));

        $session = $this->client()->autenticar();

        self::assertSame(intdiv($expira, 1000), $session->csrfExpiresAt->getTimestamp());
    }

    public function testSemHeaderDeExpiracaoAssume60Minutos(): void
    {
        $this->http->queue(self::authResponse());

        $session = $this->client()->autenticar();

        self::assertSame($this->clock->now()->getTimestamp() + 3600, $session->csrfExpiresAt->getTimestamp());
    }

    public function testCsrfNoCorpoQuandoHeaderAusente(): void
    {
        $this->http->queue(new Response(200, ['Set-Token' => 'jwt-x'], json_encode(['token' => 'csrf-do-corpo'], JSON_THROW_ON_ERROR)));

        self::assertSame('csrf-do-corpo', $this->client()->autenticar()->csrfToken);
    }

    public function testRespostaSemTokensLancaExcecao(): void
    {
        $this->http->queue(new Response(200, [], '{}'));

        $this->expectException(AuthenticationException::class);
        $this->client()->autenticar();
    }

    public function testAguardaIntervaloMinimoDe60SegundosAntesDeReautenticar(): void
    {
        $this->http->queue(self::authResponse(csrf: 'a'), self::authResponse(csrf: 'b'));
        $client = $this->client();

        $client->autenticar();
        $this->clock->advance(20);
        $client->encerrarSessao();
        $client->autenticar();

        self::assertCount(2, $this->http->requests);
        self::assertGreaterThanOrEqual(40_000, $this->clock->totalSleptMs());
        self::assertLessThanOrEqual(41_000, $this->clock->totalSleptMs());
    }

    public function testLancaExcecaoQuandoPoliticaForLancarExcecao(): void
    {
        $this->http->queue(self::authResponse());
        $client = $this->client(new ClientConfig(politicaIntervalo: PoliticaIntervaloAutenticacao::LancarExcecao));

        $client->autenticar();
        $this->clock->advance(10);
        $client->encerrarSessao();

        try {
            $client->autenticar();
            self::fail('Deveria lançar AuthenticationThrottledException');
        } catch (AuthenticationThrottledException $e) {
            self::assertSame('PLAT-ER2033', $e->codigoErro);
            self::assertNotNull($e->podeAutenticarEm);
            self::assertSame($this->clock->now()->getTimestamp() + 50, $e->podeAutenticarEm->getTimestamp());
        }
        self::assertCount(1, $this->http->requests);
        self::assertSame([], $this->clock->sleeps);
    }

    public function testNaoAguardaQuandoJaPassaram60Segundos(): void
    {
        $this->http->queue(self::authResponse(), self::authResponse());
        $client = $this->client();

        $client->autenticar();
        $this->clock->advance(61);
        $client->encerrarSessao();
        $client->autenticar();

        self::assertSame([], $this->clock->sleeps);
        self::assertCount(2, $this->http->requests);
    }

    public function testPlatEr2033DoPortalRegistraUltimaAutenticacao(): void
    {
        $this->http->queue(
            self::error(422, 'PLAT-ER2033', 'Operação não autorizada. Usuário realizou autenticação válida nos últimos 60 segundos.'),
            self::authResponse(),
        );
        $client = $this->client();

        try {
            $client->autenticar();
            self::fail('Deveria lançar exceção');
        } catch (AuthenticationThrottledException) {
        }

        $client->autenticar();
        self::assertGreaterThanOrEqual(60_000, $this->clock->totalSleptMs());
    }

    public function testChaveInvalidaLancaAuthenticationExceptionComTag(): void
    {
        $this->http->queue(self::error(422, 'PLAT-ER8007', 'Par de chaves inválido ou inexistente', '[PLAT-SUXKSU2VFS]'));

        try {
            $this->client()->autenticar();
            self::fail('Deveria lançar exceção');
        } catch (AuthenticationException $e) {
            self::assertSame('PLAT-ER8007', $e->codigoErro);
            self::assertSame('[PLAT-SUXKSU2VFS]', $e->tag);
            self::assertSame(422, $e->httpStatus);
        }
    }

    public function testFalhaNaoRegistraUltimaAutenticacao(): void
    {
        $this->http->queue(self::error(422, 'PLAT-ER8003'), self::authResponse());
        $client = $this->client();

        try {
            $client->autenticar();
        } catch (AuthenticationException) {
        }
        $client->autenticar();

        self::assertSame([], $this->clock->sleeps);
    }

    public function testErroDeRedeViraTransportException(): void
    {
        $this->http->queue(new ConnectException('Connection refused', new Request('POST', 'https://x')));

        $this->expectException(TransportException::class);
        $this->client()->autenticar();
    }

    public function testChaveDeSessaoNaoContemSegredo(): void
    {
        $this->http->queue(self::authResponse());
        $this->client(credential: new ChaveAcesso('meu-client-id-secreto', 'meu-secret'))->autenticar();

        $reflection = new \ReflectionProperty($this->store, 'sessions');
        /** @var array<string, mixed> $sessions */
        $sessions = $reflection->getValue($this->store);
        $key = (string) array_key_first($sessions);

        self::assertStringStartsWith('validacao|IMPEXP|chave-', $key);
        self::assertStringNotContainsString('meu-client-id-secreto', $key);
    }

    public function testLogNaoExpoeSegredos(): void
    {
        $this->http->queue(self::authResponse(jwt: 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.segredo'));
        $this->client()->autenticar();

        $logs = $this->logger->allText();
        self::assertStringContainsString('Autenticado', $logs);
        self::assertStringNotContainsString('client-secret-teste', $logs);
        self::assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9', $logs);
        self::assertStringNotContainsString('csrf-1', $logs);
    }
}
