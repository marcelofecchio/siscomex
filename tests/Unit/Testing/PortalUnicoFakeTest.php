<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Testing;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoCodigoInterno;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Testing\PortalUnicoFake;
use PHPUnit\Framework\TestCase;

final class PortalUnicoFakeTest extends TestCase
{
    public function testCenariosDeCodigoInterno(): void
    {
        $fake = new PortalUnicoFake()
            ->codigoInternoLivre()
            ->codigoInternoAtivo('SKU-1', 10)
            ->codigoInternoDesativado('SKU-2', 11)
            ->codigoInternoDuplicado('SKU-3', 12, 13);
        $produtos = $fake->cliente()->catalogo()->produtos();

        self::assertSame(SituacaoCodigoInterno::Livre, $produtos->buscarPorCodigoInterno('12345678', 'SKU-0')->situacao());
        self::assertSame(10, $produtos->buscarPorCodigoInterno('12345678', 'SKU-1')->maisRecente()?->codigo);
        self::assertSame(SituacaoCodigoInterno::Desativado, $produtos->buscarPorCodigoInterno('12345678', 'SKU-2')->situacao());
        self::assertTrue($produtos->buscarPorCodigoInterno('12345678', 'SKU-3')->duplicado());
        self::assertSame(1, $fake->autenticacoes(), 'autenticação automática e reaproveitada');
        self::assertSame(0, $fake->respostasPendentes());
    }

    public function testProdutoIncluidoEcoaORequest(): void
    {
        $request = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')->comCodigoInterno('SKU-9');
        $fake = new PortalUnicoFake()->produtoIncluido(123, $request);

        $produto = $fake->cliente()->catalogo()->produtos()->incluir('12345678', $request);

        self::assertSame(123, $produto->codigo);
        self::assertTrue($produto->ativo());
        self::assertSame(['SKU-9'], $produto->codigosInterno);
        self::assertSame($request->toArray(), $fake->corpoDaUltima());
        self::assertSame('POST /catp/api/ext/produto/12345678', $fake->chamadas()[0]);
    }

    public function testErrosEFalhas(): void
    {
        $fake = new PortalUnicoFake()
            ->erroValidacao(['ATT_1' => 'O Atributo ATT_1 para o NCM 84733049 é obrigatório.'])
            ->indisponivel()
            ->falhaDeRede()
            ->rateLimit();
        $request = new ProdutoRequest('P', Modalidade::Importacao, '84733049');

        try {
            $fake->cliente()->catalogo()->produtos()->incluir('12345678', $request);
            self::fail('esperava ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['ATT_1' => ['O Atributo ATT_1 para o NCM 84733049 é obrigatório.']], $e->mensagensPorCampo());
        }

        $resultado = $fake->cliente()->catalogo()->produtos()->incluirLote('12345678', [$request, $request]);
        self::assertSame([StatusItemLote::Pendente, StatusItemLote::Pendente], array_map(static fn($i) => $i->status, $resultado->itens));
        self::assertInstanceOf(ServiceUnavailableException::class, $resultado->interrupcao);

        $incerto = $fake->cliente()->catalogo()->produtos()->incluirLote('12345678', [$request]);
        self::assertSame(StatusItemLote::Incerto, $incerto->itens[0]->status);

        self::assertNotNull($fake->cliente()->catalogo()->produtos()->incluirLote('12345678', [$request])->retomarApos());
    }

    public function testOperadorEFilaVazia(): void
    {
        $fake = new PortalUnicoFake()->operadorIncluido('OPE_7', 'DE');
        $operadores = $fake->cliente()->catalogo()->operadoresEstrangeiros();

        $operador = $operadores->incluir('12345678', 'DE', new OperadorEstrangeiroRequest('GmbH', 'Straße 1', 'Berlin'));
        self::assertSame('OPE_7', $operador->codigo);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('nenhuma resposta enfileirada para GET');
        $operadores->detalhar('12345678', 'DE', 'OPE_7', '1');
    }
}
