<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Response;
use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
use MFecchio\Siscomex\PortalUnico\Exceptions\ProductionWriteBlockedException;
use MFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroProdutos;
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class ProdutosResourceTest extends TestCase
{
    private const array PRODUTO = [
        'codigo' => 123,
        'versao' => '1',
        'cpfCnpjRaiz' => '12345678',
        'situacao' => 'ATIVADO',
        'modalidade' => 'IMPORTACAO',
        'ncm' => '84733049',
        'denominacao' => 'Placa-mãe',
        'descricao' => 'Placa-mãe para servidor',
        'atributos' => [['atributo' => 'ATT_18551', 'valor' => '99']],
        'atributosMultivalorados' => [['atributo' => 'ATT_M', 'valores' => ['a', 'b']]],
        'atributosCompostos' => [['atributo' => 'ATT_C', 'valores' => [['atributo' => 'ATT_C1', 'valor' => 'x']]]],
        'atributosCompostosMultivalorados' => [['atributo' => 'ATT_CM', 'valores' => [[['atributo' => 'ATT_CM1', 'valor' => 'y']]]]],
        'codigosInterno' => ['SKU-1'],
        'inicioVigencia' => '2026-10-15',
        'fimVigencia' => null,
    ];

    public function testIncluir(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::PRODUTO));

        $produto = $this->client()->catalogo()->produtos()->incluir('12.345.678', self::request());

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/catp/api/ext/produto/12345678', $request->getUri()->getPath());
        self::assertSame([
            'denominacao' => 'Placa-mãe',
            'descricao' => 'Placa-mãe para servidor',
            'modalidade' => 'IMPORTACAO',
            'ncm' => '84733049',
            'atributos' => [['atributo' => 'ATT_18551', 'valor' => '99']],
            'atributosMultivalorados' => [],
            'atributosCompostos' => [],
            'atributosCompostosMultivalorados' => [],
            'codigosInterno' => ['SKU-1'],
        ], self::body($request));

        self::assertSame(123, $produto->codigo);
        self::assertSame('1', $produto->versao);
        self::assertSame(SituacaoProduto::Ativado, $produto->situacao);
        self::assertTrue($produto->ativo());
        self::assertSame(Modalidade::Importacao, $produto->modalidade);
        self::assertSame('84733049', $produto->ncm?->codigo);
        self::assertSame('99', $produto->valorAtributo('ATT_18551'));
        self::assertNull($produto->valorAtributo('ATT_X'));
        self::assertSame(['a', 'b'], $produto->atributosMultivalorados[0]->valores);
        self::assertSame('x', $produto->atributosCompostos[0]->valores[0]->valor);
        self::assertSame('y', $produto->atributosCompostosMultivalorados[0]->valores[0][0]->valor);
        self::assertSame('2026-10-15', $produto->inicioVigencia?->format('Y-m-d'));
        self::assertNull($produto->fimVigencia);
    }

    public function testIncluirRetroativo(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::PRODUTO));

        $this->client()->catalogo()->produtos()->incluir('12345678', self::request(), new \DateTimeImmutable('2026-01-31'));

        self::assertSame('/catp/api/ext/produto/12345678/2026-01-31', $this->http->lastRequest()->getUri()->getPath());
    }

    public function testIncluirSemRetornoLancaExcecao(): void
    {
        $this->http->queue(self::authResponse(), new Response(204));

        $this->expectException(SiscomexException::class);
        $this->client()->catalogo()->produtos()->incluir('12345678', self::request());
    }

    public function testIncluirComErroDeValidacao(): void
    {
        $this->http->queue(self::authResponse(), self::json(422, [
            'message' => 'Produto inválido',
            'code' => 'CATP-ER0010',
            'tag' => '[CATP-ABC]',
            'detail' => [['message' => 'Atributo ATT_18551 obrigatório']],
        ]));

        try {
            $this->client()->catalogo()->produtos()->incluir('12345678', self::request());
            self::fail('Deveria lançar ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('[CATP-ABC]', $e->tag);
            self::assertSame(['Atributo ATT_18551 obrigatório'], $e->mensagens());
        }
    }

    public function testNovaVersaoERetificar(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, ['versao' => '2'] + self::PRODUTO), self::json(200, ['versao' => '2.1'] + self::PRODUTO));
        $produtos = $this->client()->catalogo()->produtos();

        self::assertSame('2', $produtos->novaVersao('12345678', 123, self::request())->versao);
        self::assertSame('PUT /catp/api/ext/produto/12345678/123', $this->http->calls()[1]);

        self::assertSame('2.1', $produtos->retificar('12345678', 123, '2', self::request())->versao);
        self::assertSame('PUT /catp/api/ext/produto/12345678/123/2', $this->http->calls()[2]);
    }

    public function testVersaoInvalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->catalogo()->produtos()->retificar('12345678', 1, '1/../2', self::request());
    }

    public function testDesativar(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, ['situacao' => 'DESATIVADO'] + self::PRODUTO), new Response(204));
        $produtos = $this->client()->catalogo()->produtos();

        self::assertSame(SituacaoProduto::Desativado, $produtos->desativar('12345678', 123)?->situacao);
        self::assertSame('PUT /catp/api/ext/produto/desativar/12345678/123', $this->http->calls()[1]);
        self::assertNull($produtos->desativar('12345678', 123));
    }

    public function testDetalhar(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::PRODUTO), new Response(204));
        $produtos = $this->client()->catalogo()->produtos();

        self::assertSame('Placa-mãe', $produtos->detalhar('12345678', 123, '1.1')?->denominacao);
        self::assertSame('GET /catp/api/ext/produto/12345678/123/1.1', $this->http->calls()[1]);
        self::assertNull($produtos->detalhar('12345678', 123, '1'));
    }

    public function testConsultarComFiltros(): void
    {
        $this->http->queue(self::authResponse(), self::json(206, [self::PRODUTO, ['codigo' => 124] + self::PRODUTO]));

        $resultado = $this->client()->catalogo()->produtos()->consultar(new FiltroProdutos(
            cpfCnpjRaiz: '12345678',
            ncm: '8473.30.49',
            situacao: SituacaoProduto::Ativado,
            modalidade: Modalidade::Ambos,
            ultimaAlteracaoInicio: new \DateTimeImmutable('2026-01-01'),
            paisOrigem: 'CN',
            cpfCnpjFabricante: '12.345.678/0001-95',
        ));

        self::assertCount(2, $resultado);
        self::assertTrue($resultado->parcial);
        self::assertSame(123, $resultado->primeiro()?->codigo);
        self::assertSame([123, 124], array_map(static fn($p): ?int => $p->codigo, iterator_to_array($resultado)));
        $uri = $this->http->lastRequest()->getUri();
        self::assertSame('/catp/api/ext/produto', $uri->getPath());
        self::assertSame('cpfCnpjRaiz=12345678&ncm=84733049&situacao=0&modalidade=AMBOS&ultimaAlteracaoInicio=2026-01-01&paisOrigem=CN&cpfCnpjFabricante=12345678000195', $uri->getQuery());
    }

    public function testConsultarSemResultados(): void
    {
        $this->http->queue(self::authResponse(), new Response(204));

        $resultado = $this->client()->catalogo()->produtos()->consultar(new FiltroProdutos('12345678'));

        self::assertCount(0, $resultado);
        self::assertFalse($resultado->parcial);
        self::assertNull($resultado->primeiro());
    }

    public function testFiltroPaisInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FiltroProdutos('12345678', paisOrigem: 'china');
    }

    public function testHistorico(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, [
            ['data' => '2026-10-15T10:00:00', 'versao' => '1', 'situacao' => 'ATIVADO', 'usuario' => '111.111.111-11', 'observacao' => 'Ativação da versão inicial'],
        ]));

        $historico = $this->client()->catalogo()->produtos()->historico('12345678', 123);

        self::assertSame('GET /catp/api/ext/produto/historico/12345678/123', $this->http->calls()[1]);
        self::assertSame(SituacaoProduto::Ativado, $historico[0]->situacao);
        self::assertSame('2026-10-15 10:00', $historico[0]->data?->format('Y-m-d H:i'));
        self::assertSame('Ativação da versão inicial', $historico[0]->observacao);
    }

    public function testCodigoInterno(): void
    {
        $this->http->queue(self::authResponse(), new Response(204), new Response(200));
        $produtos = $this->client()->catalogo()->produtos();

        $produtos->incluirCodigoInterno('12345678', 123, '1', 'SKU-9');
        $produtos->removerCodigoInterno('12345678', 123, '1', 'SKU-9');

        self::assertSame([
            'POST /portal/api/autenticar/chave-acesso',
            'POST /catp/api/ext/produto/codigo-interno/12345678/123/1',
            'DELETE /catp/api/ext/produto/codigo-interno/12345678/123/1',
        ], $this->http->calls());
        self::assertSame(['codigoInterno' => 'SKU-9'], self::body($this->http->lastRequest()));
    }

    public function testExportarDescompactaZip(): void
    {
        $zip = self::zip('produtos.json', json_encode([['seq' => 1] + self::PRODUTO], JSON_THROW_ON_ERROR));
        $this->http->queue(self::authResponse(), new Response(200, ['Content-Type' => 'application/zip'], $zip), new Response(204));
        $produtos = $this->client()->catalogo()->produtos();

        $exportados = $produtos->exportar('12345678', true, [123, 124], new \DateTimeImmutable('2026-01-01'));

        self::assertSame(1, $exportados[0]->seq);
        self::assertSame('/catp/api/ext/produto/exportar/12345678/true', $this->http->lastRequest()->getUri()->getPath());
        self::assertSame('codigosProduto=123&codigosProduto=124&inicioVigenciaDe=2026-01-01', $this->http->lastRequest()->getUri()->getQuery());
        self::assertSame([], $produtos->exportar('12345678'));
    }

    public function testEscritaBloqueadaEmProducaoDuranteIntegracao(): void
    {
        $produtos = $this->client(new ClientConfig(ambiente: Ambiente::Producao, bloquearEscritaEmProducao: true))->catalogo()->produtos();

        $this->expectException(ProductionWriteBlockedException::class);
        $produtos->incluir('12345678', self::request());
    }

    public function testProdutoParaRequestPermiteNovaVersao(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::PRODUTO));
        $produto = $this->client()->catalogo()->produtos()->detalhar('12345678', 123, '1');
        self::assertNotNull($produto);

        $request = $produto->paraRequest()->comDescricao('Nova descrição');

        self::assertSame('Nova descrição', $request->descricao);
        self::assertSame('84733049', $request->ncm->codigo);
        self::assertCount(1, $request->atributosCompostosMultivalorados);
        self::assertSame(['ATT_18551', 'ATT_M', 'ATT_C', 'ATT_CM'], $request->codigosAtributosInformados());
    }

    private static function request(): ProdutoRequest
    {
        return new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')
            ->comDescricao('Placa-mãe para servidor')
            ->comAtributo('ATT_18551', '99')
            ->comCodigoInterno('SKU-1');
    }
}
