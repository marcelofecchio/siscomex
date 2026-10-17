<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Response;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoOperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ProductionWriteBlockedException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroOperadoresEstrangeiros;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\OperadoresEstrangeirosResource;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class OperadoresEstrangeirosResourceTest extends TestCase
{
    public function testIncluirEnviaCorpoEInterpretaResposta(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'operador-estrangeiro-detalhe.json')));

        $operador = $this->operadores()->incluir('12.345.678', 'cn', self::request()->comIdentificacaoAdicional('016', 'DUNS-123'));

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/catp/api/ext/operador-estrangeiro/12345678/CN', $request->getUri()->getPath());
        self::assertSame([
            'nome' => 'SDK Teste Operador',
            'logradouro' => 'No. 1 Keji Road',
            'nomeCidade' => 'Shenzhen',
            'tin' => 'TIN174422',
            'codigoSubdivisaoPais' => 'CN-GD',
            'cep' => '518000',
            'codigoInterno' => 'FORN-SDK-1',
            'email' => 'teste@example.com',
            'identificacoesAdicionais' => [['numero' => 'DUNS-123', 'codigo' => '016']],
        ], self::body($request));

        self::assertSame('OPE_2', $operador->codigo);
        self::assertSame('2.1', $operador->versao);
        self::assertSame('CN', $operador->codigoPais);
        self::assertSame(SituacaoOperadorEstrangeiro::Desativado, $operador->situacao, 'a API devolve "Desativado" (capitalizado)');
        self::assertFalse($operador->ativo());
        self::assertSame('2026-10-17 14:44:23', $operador->inicioVigencia?->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-17 14:45:45', $operador->fimVigencia?->format('Y-m-d H:i:s'));
        self::assertSame('CN-GD', $operador->codigoSubdivisaoPais);
    }

    public function testIncluirRetroativo(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'operador-estrangeiro-detalhe.json')));

        $this->operadores()->incluir('12345678', 'CN', self::request(), new \DateTimeImmutable('2026-01-31'));

        self::assertSame('/catp/api/ext/operador-estrangeiro/12345678/CN/2026-01-31', $this->http->lastRequest()->getUri()->getPath());
    }

    public function testSubdivisaoDeOutroPaisERecusadaSemRequisicao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CN-GD não pertence ao país AR');

        try {
            $this->operadores()->incluir('12345678', 'AR', self::request());
        } finally {
            self::assertSame([], $this->http->requests);
        }
    }

    public function testNovaVersaoERetroativa(): void
    {
        $detalhe = self::fixture('validacao', 'operador-estrangeiro-detalhe.json');
        $this->http->queue(self::authResponse(), self::json(200, $detalhe), self::json(200, $detalhe));
        $operadores = $this->operadores();

        $operadores->novaVersao('12345678', 'CN', 'OPE_2', self::request());
        $operadores->novaVersao('12345678', 'CN', 'OPE_2', self::request(), new \DateTimeImmutable('2026-02-01'));

        self::assertSame([
            'POST /portal/api/autenticar/chave-acesso',
            'PUT /catp/api/ext/operador-estrangeiro/12345678/CN/OPE_2',
            'POST /catp/api/ext/operador-estrangeiro/12345678/CN/OPE_2/2026-02-01',
        ], $this->http->calls());
    }

    public function testRetificarDetalharEDesativar(): void
    {
        $detalhe = self::fixture('validacao', 'operador-estrangeiro-detalhe.json');
        $this->http->queue(self::authResponse(), self::json(200, $detalhe), self::json(200, $detalhe), self::json(204, null), self::json(200, $detalhe));
        $operadores = $this->operadores();

        $operadores->retificar('12345678', 'CN', 'OPE_2', '2', self::request());
        self::assertSame('2.1', $operadores->detalhar('12345678', 'CN', 'OPE_2', '2.1')?->versao);
        self::assertNull($operadores->detalhar('12345678', 'CN', 'OPE_2', '9'));
        self::assertSame(SituacaoOperadorEstrangeiro::Desativado, $operadores->desativar('12345678', 'CN', 'OPE_2', '2.1')?->situacao);

        self::assertSame([
            'POST /portal/api/autenticar/chave-acesso',
            'PUT /catp/api/ext/operador-estrangeiro/12345678/CN/OPE_2/2',
            'GET /catp/api/ext/operador-estrangeiro/12345678/CN/OPE_2/2.1',
            'GET /catp/api/ext/operador-estrangeiro/12345678/CN/OPE_2/9',
            'PUT /catp/api/ext/operador-estrangeiro/desativar/12345678/CN/OPE_2/2.1',
        ], $this->http->calls());
    }

    public function testCodigoEVersaoInvalidos(): void
    {
        $operadores = $this->operadores();

        try {
            $operadores->detalhar('12345678', 'CN', '', '1');
            self::fail('código vazio');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Código do operador', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $operadores->detalhar('12345678', 'CN', 'OPE_2', '1.x');
    }

    public function testConsultarComFiltrosEResultadoParcial(): void
    {
        $this->http->queue(self::authResponse(), self::json(206, self::fixture('validacao', 'operador-estrangeiro-consulta.json')));

        $resultado = $this->operadores()->consultar(new FiltroOperadoresEstrangeiros('12345678', nome: 'SDK Teste', paisOrigem: 'cn', exibirDesativados: true));

        parse_str($this->http->lastRequest()->getUri()->getQuery(), $query);
        self::assertSame(['cpfCnpjRaiz' => '12345678', 'nome' => 'SDK Teste', 'paisOrigem' => 'CN', 'exibirDesativados' => 'true'], $query);
        self::assertTrue($resultado->parcial);
        self::assertCount(1, $resultado);
        self::assertSame(1, $resultado->primeiro()?->seq);
        self::assertSame('OPE_2', $resultado->primeiro()->codigo);
    }

    public function testConsultaVazia(): void
    {
        $this->http->queue(self::authResponse(), self::json(204, null));

        $resultado = $this->operadores()->consultar(new FiltroOperadoresEstrangeiros('12345678'));

        self::assertCount(0, $resultado);
        self::assertNull($resultado->primeiro());
        self::assertFalse($resultado->parcial);
    }

    public function testHistorico(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'operador-estrangeiro-historico.json')));

        $eventos = $this->operadores()->historico('12345678', 'CN', 'OPE_2');

        self::assertSame('/catp/api/ext/operador-estrangeiro/historico/12345678/CN/OPE_2', $this->http->lastRequest()->getUri()->getPath());
        self::assertGreaterThanOrEqual(5, \count($eventos));
        self::assertSame(SituacaoOperadorEstrangeiro::Ativado, $eventos[\count($eventos) - 1]->situacao);
        self::assertSame('Ativação da versão 1', $eventos[\count($eventos) - 1]->observacao);
        self::assertNotNull($eventos[0]->data);
    }

    public function testExportarAceitaZipJsonEVazio(): void
    {
        $lista = self::fixture('validacao', 'operador-estrangeiro-consulta.json');
        $this->http->queue(
            self::authResponse(),
            new Response(200, ['Content-Type' => 'application/zip'], self::zip('operadores.json', (string) json_encode($lista))),
            self::json(200, $lista),
            self::json(204, null),
        );
        $operadores = $this->operadores();

        self::assertSame('OPE_2', $operadores->exportar('12345678', true, ['OPE_2'], new \DateTimeImmutable('2026-10-01'))[0]->codigo);
        self::assertSame(
            '/catp/api/ext/operador-estrangeiro/exportar/12345678/true?codigosOperador=OPE_2&inicioVigenciaDe=2026-10-01',
            $this->http->lastRequest()->getUri()->getPath().'?'.$this->http->lastRequest()->getUri()->getQuery(),
        );
        self::assertCount(1, $operadores->exportar('12345678'));
        self::assertSame([], $operadores->exportar('12345678'));
    }

    public function testErroDeDuplicidadeExpoeCodigo(): void
    {
        // Corpo reconstruído a partir da resposta real (Validação, 17/10/2026).
        $this->http->queue(self::authResponse(), self::json(422, [
            'message' => 'Já existe um operador estrangeiro cadastrado com os mesmos dados. País: CN, Código: OPE_2, Versão: 2.1.',
            'code' => 'CATP-ER5013',
            'tag' => '[CATP-GFZVVM3945]',
            'status' => 422,
            'severity' => 'ERROR',
        ]));

        try {
            $this->operadores()->retificar('12345678', 'CN', 'OPE_2', '2.1', self::request());
            self::fail('esperava ValidationException');
        } catch (ValidationException $e) {
            self::assertTrue($e->possuiCodigo('CATP-ER5013'));
            self::assertFalse($e->possuiCodigo('CATP-ER2021'));
        }
    }

    public function testEscritaBloqueadaEmProducao(): void
    {
        $operadores = $this->client(new ClientConfig(ambiente: Ambiente::Producao, bloquearEscritaEmProducao: true))->catalogo()->operadoresEstrangeiros();

        $this->expectException(ProductionWriteBlockedException::class);
        $operadores->incluir('12345678', 'CN', self::request());
    }

    private function operadores(): OperadoresEstrangeirosResource
    {
        return $this->client()->catalogo()->operadoresEstrangeiros();
    }

    private static function request(): OperadorEstrangeiroRequest
    {
        return new OperadorEstrangeiroRequest('SDK Teste Operador', 'No. 1 Keji Road', 'Shenzhen')
            ->comTin('TIN174422')
            ->comSubdivisao('cn-gd')
            ->comCep('518000')
            ->comCodigoInterno('FORN-SDK-1')
            ->comEmail('teste@example.com');
    }
}
