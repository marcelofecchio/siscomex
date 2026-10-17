<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Http;

use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Fabricante;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoComFabricantes;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class SimulacaoEscritaTest extends TestCase
{
    public function testEscritaNaoEEnviadaNemAutentica(): void
    {
        $client = $this->client(new ClientConfig(simularEscrita: true));
        $request = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')->comAtributo('ATT_1', '01')->comCodigoInterno('SKU-1');

        $produto = $client->catalogo()->produtos()->incluir('12345678', $request);

        self::assertSame([], $this->http->requests, 'nenhuma requisição, nem de autenticação');
        self::assertNull($produto->codigo, 'o portal não gerou código');
        self::assertSame('Placa-mãe', $produto->denominacao);
        self::assertSame(['SKU-1'], $produto->codigosInterno);

        $simuladas = $client->escritasSimuladas();
        self::assertCount(1, $simuladas);
        self::assertSame('POST', $simuladas[0]->metodo);
        self::assertSame('/catp/api/ext/produto/12345678', $simuladas[0]->path);
        self::assertSame('https://val.portalunico.siscomex.gov.br/catp/api/ext/produto/12345678', $simuladas[0]->url);
        self::assertSame($request->toArray(), $simuladas[0]->corpo);
        self::assertStringContainsString('info: Escrita simulada (não enviada): POST https://val.portalunico.siscomex.gov.br/catp/api/ext/produto/12345678', $this->logger->allText());
    }

    public function testLeiturasContinuamIndoAoPortal(): void
    {
        $this->http->queue(self::authResponse(), self::json(204, null));
        $client = $this->client(new ClientConfig(simularEscrita: true));

        $client->catalogo()->produtos()->buscarPorCodigoInterno('12345678', 'SKU-1');
        $client->catalogo()->produtos()->desativar('12345678', 9);

        self::assertSame(['POST /portal/api/autenticar/chave-acesso', 'GET /catp/api/ext/produto'], $this->http->calls());
        self::assertSame('PUT', $client->escritasSimuladas()[0]->metodo);
    }

    public function testSimulacaoTemPrioridadeSobreOBloqueioEmProducao(): void
    {
        $client = $this->client(new ClientConfig(ambiente: Ambiente::Producao, bloquearEscritaEmProducao: true, simularEscrita: true));

        $client->catalogo()->fabricantes()->vincular('12345678', Fabricante::desconhecido('DE')->paraProduto(5));

        self::assertSame([], $this->http->requests);
        self::assertSame('https://portalunico.siscomex.gov.br/catp/api/ext/fabricante/12345678', $client->escritasSimuladas()[0]->url);
    }

    public function testLoteSimuladoComFabricantesNaoVinculaSemCodigo(): void
    {
        $client = $this->client(new ClientConfig(simularEscrita: true));

        $resultado = $client->catalogo()->produtos()->incluirLote('12345678', [
            new ProdutoComFabricantes(new ProdutoRequest('A', Modalidade::Importacao, '84733049'), [Fabricante::desconhecido('DE')]),
            new ProdutoRequest('B', Modalidade::Importacao, '84733049'),
        ]);

        self::assertSame([StatusItemLote::IncluidoSemVinculo, StatusItemLote::Incluido], array_map(static fn($i) => $i->status, $resultado->itens));
        self::assertSame(StatusVinculo::Pendente, $resultado->itens[0]->vinculos[0]->status);
        self::assertNull($resultado->itens[0]->vinculos[0]->erro);
        self::assertFalse($resultado->interrompido());
        self::assertSame([], $resultado->vinculosParaReprocessar(), 'sem código de produto não há o que reenviar');
        self::assertCount(2, $client->escritasSimuladas());
    }
}
