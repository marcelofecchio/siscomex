<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ProductionWriteBlockedException;
use MFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MFecchio\Siscomex\PortalUnico\Http\Transport;
use MFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class TransportTest extends TestCase
{
    public function testInjetaJwtECsrfNasRequisicoes(): void
    {
        $this->http->queue(self::authResponse(jwt: 'jwt-1', csrf: 'csrf-1'), self::json(200, []));

        $this->client()->transport()->json('GET', '/catp/api/ext/produto', ['cpfCnpjRaiz' => '12345678']);

        $request = $this->http->lastRequest();
        self::assertSame('jwt-1', $request->getHeaderLine('Authorization'));
        self::assertSame('csrf-1', $request->getHeaderLine('X-CSRF-Token'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testRenovaCsrfACadaResposta(): void
    {
        $this->http->queue(
            self::authResponse(csrf: 'csrf-1'),
            self::json(200, [], ['X-CSRF-Token' => 'csrf-2', 'X-CSRF-Expiration' => (string) (($this->clock->now()->getTimestamp() + 3600) * 1000)]),
            self::json(200, [], ['X-CSRF-Token' => 'csrf-3']),
            self::json(200, []),
        );
        $transport = $this->client()->transport();

        $transport->json('GET', '/a');
        $transport->json('GET', '/b');
        $transport->json('GET', '/c');

        self::assertSame('csrf-1', $this->http->request(1)->getHeaderLine('X-CSRF-Token'));
        self::assertSame('csrf-2', $this->http->request(2)->getHeaderLine('X-CSRF-Token'));
        self::assertSame('csrf-3', $this->http->request(3)->getHeaderLine('X-CSRF-Token'));
        self::assertCount(4, $this->http->requests, 'apenas uma autenticação');
    }

    public function testCsrfRenovadoEmRespostaDeErroTambemECapturado(): void
    {
        $this->http->queue(
            self::authResponse(csrf: 'csrf-1'),
            new Response(422, ['X-CSRF-Token' => 'csrf-2', 'Content-Type' => 'application/json'], '{"message":"x","code":"CATP-ER0001"}'),
            self::json(200, []),
        );
        $transport = $this->client()->transport();

        try {
            $transport->json('GET', '/a');
        } catch (ValidationException) {
        }
        $transport->json('GET', '/b');

        self::assertSame('csrf-2', $this->http->lastRequest()->getHeaderLine('X-CSRF-Token'));
    }

    public function testReautenticaUmaUnicaVezEm401(): void
    {
        $this->http->queue(
            self::authResponse(jwt: 'jwt-1', csrf: 'csrf-1'),
            self::error(401, 'PUCX-ER0001', 'Token expirado'),
            self::authResponse(jwt: 'jwt-2', csrf: 'csrf-2'),
            self::json(200, ['ok' => true]),
        );
        $this->clock->advance(0);
        $transport = $this->client()->transport();

        $resultado = $transport->json('GET', '/x');

        self::assertSame(['ok' => true], $resultado);
        self::assertSame(['POST /portal/api/autenticar/chave-acesso', 'GET /x', 'POST /portal/api/autenticar/chave-acesso', 'GET /x'], $this->http->calls());
        self::assertSame('jwt-2', $this->http->lastRequest()->getHeaderLine('Authorization'));
        self::assertGreaterThanOrEqual(60_000, $this->clock->totalSleptMs(), 'respeita 60 s mesmo no 401');
    }

    public function testSegundo401ConsecutivoLancaExcecao(): void
    {
        $this->http->queue(
            self::authResponse(jwt: 'jwt-1'),
            self::error(401, 'X', 'nao autenticado'),
            self::authResponse(jwt: 'jwt-2'),
            self::error(401, 'X', 'nao autenticado'),
        );

        $this->expectException(AuthenticationException::class);
        try {
            $this->client()->transport()->json('GET', '/x');
        } finally {
            self::assertCount(4, $this->http->requests);
        }
    }

    public function testRepete503ComBackoffExponencial(): void
    {
        $this->http->queue(
            self::authResponse(),
            self::error(503, 'X', 'indisponivel'),
            self::error(503, 'X', 'indisponivel'),
            self::json(200, ['ok' => 1]),
        );

        $resultado = $this->client(new ClientConfig(tentativas503: 2, backoffInicialMs: 100))->transport()->json('GET', '/x');

        self::assertSame(['ok' => 1], $resultado);
        self::assertSame([100, 200], $this->clock->sleeps);
    }

    public function testDesistePosEsgotarTentativas503(): void
    {
        $this->http->queue(self::authResponse(), self::error(503, 'X'), self::error(503, 'X'));

        $this->expectException(ServiceUnavailableException::class);
        $this->client(new ClientConfig(tentativas503: 1, backoffInicialMs: 10))->transport()->json('GET', '/x');
    }

    public function testNaoRepeteParadaProgramada(): void
    {
        $this->http->queue(self::authResponse(), self::error(503, 'PUCX-ER0401', 'parada programada'));

        try {
            $this->client()->transport()->json('GET', '/x');
            self::fail('Deveria lançar exceção');
        } catch (ServiceUnavailableException $e) {
            self::assertSame('PUCX-ER0401', $e->codigoErro);
        }
        self::assertSame([], $this->clock->sleeps);
    }

    public function testNaoRepete4xxNemRateLimit(): void
    {
        $this->http->queue(
            self::authResponse(),
            self::error(422, 'PUCX-ER1001', 'Foi atingido o limite de 1000 acessos permitidos à funcionalidade em uma hora. A funcionalidade estará disponível novamente após as 15:00.'),
        );

        try {
            $this->client()->transport()->json('GET', '/x');
            self::fail('Deveria lançar RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(1000, $e->limite);
            self::assertSame('15:00', $e->liberadoEm?->format('H:i'));
        }
        self::assertSame([], $this->clock->sleeps);
        self::assertCount(2, $this->http->requests);
    }

    public function testRepeteErroDeRedeApenasEmGet(): void
    {
        $erro = new ConnectException('timeout', new Request('GET', 'https://x'));
        $this->http->queue(self::authResponse(), $erro, self::json(200, ['ok' => 1]));

        self::assertSame(['ok' => 1], $this->client(new ClientConfig(backoffInicialMs: 5))->transport()->json('GET', '/x'));
    }

    public function testNaoRepeteErroDeRedeEmPost(): void
    {
        $erro = new ConnectException('timeout', new Request('POST', 'https://x'));
        $this->http->queue(self::authResponse(), $erro);

        $this->expectException(TransportException::class);
        $this->client()->transport()->json('POST', '/x', json: ['a' => 1]);
    }

    public function testBloqueiaEscritaEmProducaoQuandoProtecaoAtiva(): void
    {
        $client = $this->client(new ClientConfig(ambiente: Ambiente::Producao, bloquearEscritaEmProducao: true));

        $this->expectException(ProductionWriteBlockedException::class);
        try {
            $client->transport()->json('POST', '/catp/api/ext/produto/12345678', json: []);
        } finally {
            self::assertSame([], $this->http->requests, 'nenhuma requisição deve sair');
        }
    }

    public function testConsultaViaPostMarcadaComoLeituraNaoEBloqueada(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, []));
        $client = $this->client(new ClientConfig(ambiente: Ambiente::Producao, bloquearEscritaEmProducao: true));

        $client->transport()->json('POST', '/cadatributos/api/ext/atributo/consulta-codigo', json: [], readOnly: true);

        self::assertCount(2, $this->http->requests);
    }

    public function testProtecaoAtivadaPelaVariavelDeIntegracao(): void
    {
        putenv('SISCOMEX_RUN_INTEGRATION=1');
        try {
            self::assertTrue((new ClientConfig(ambiente: Ambiente::Producao))->escritaBloqueada());
            self::assertFalse((new ClientConfig(ambiente: Ambiente::Validacao))->escritaBloqueada());
            self::assertFalse((new ClientConfig(ambiente: Ambiente::Producao, bloquearEscritaEmProducao: false))->escritaBloqueada());
        } finally {
            putenv('SISCOMEX_RUN_INTEGRATION');
        }
        self::assertFalse((new ClientConfig(ambiente: Ambiente::Producao))->escritaBloqueada());
    }

    public function testRequisicaoSemAutenticacaoNaoAutentica(): void
    {
        $this->http->queue(self::json(200, ['a' => 1]));

        $this->client()->transport()->request('GET', '/publico', authenticated: false);

        self::assertSame(['GET /publico'], $this->http->calls());
        self::assertFalse($this->http->lastRequest()->hasHeader('Authorization'));
    }

    public function testRequisicaoAutenticadaSemCredencialFalha(): void
    {
        $this->expectException(\LogicException::class);
        $this->client(semCredencial: true)->transport()->json('GET', '/x');
    }

    public function testEnviaCorpoJson(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, []));

        $this->client()->transport()->json('POST', '/x', json: ['denominacao' => 'Ação']);

        $request = $this->http->lastRequest();
        self::assertSame('{"denominacao":"Ação"}', (string) $request->getBody());
        self::assertStringStartsWith('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testBuildQueryComArraysRepetidosEBooleanos(): void
    {
        self::assertSame(
            '?codigosNCM=01011000&codigosNCM=02011000&exibir=true&nulo_omitido=1',
            Transport::buildQuery(['codigosNCM' => ['01011000', '02011000'], 'vazio' => null, 'exibir' => true, 'nulo_omitido' => 1]),
        );
        self::assertSame('', Transport::buildQuery(['a' => null, 'b' => []]));
    }

    public function testDecodeTrata204ComoNull(): void
    {
        self::assertNull(Transport::decode(new Response(204)));
        self::assertNull(Transport::decode(new Response(200, [], '  ')));
    }

    public function testDecodeJsonInvalidoLancaExcecao(): void
    {
        $this->expectException(\MFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException::class);
        Transport::decode(new Response(200, [], '<html>'));
    }
}
