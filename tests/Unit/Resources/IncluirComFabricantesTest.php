<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoInclusaoComFabricantes;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Fabricante;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoComFabricantes;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Testing\PortalUnicoFake;
use PHPUnit\Framework\TestCase;

final class IncluirComFabricantesTest extends TestCase
{
    private PortalUnicoFake $fake;

    protected function setUp(): void
    {
        $this->fake = new PortalUnicoFake();
    }

    public function testCompleto(): void
    {
        $this->fake->produtoIncluido(codigo: 42)->fabricanteVinculado()->fabricanteVinculado();

        $resultado = $this->produtos()->incluirComFabricantes('12345678', self::request(), [
            Fabricante::operadorEstrangeiro('CN', 'OPE_2'),
            Fabricante::desconhecido('DE'),
        ]);

        self::assertSame(SituacaoInclusaoComFabricantes::Completo, $resultado->situacao());
        self::assertTrue($resultado->completo());
        self::assertSame(42, $resultado->produto->codigo);
        self::assertSame(['POST /catp/api/ext/produto/12345678', 'POST /catp/api/ext/fabricante/12345678', 'POST /catp/api/ext/fabricante/12345678'], $this->fake->chamadas());
        self::assertSame(['codigoProduto' => 42, 'codigoPais' => 'DE', 'conhecido' => false], $this->fake->corpoDaUltima());
        self::assertSame([], $resultado->paraReprocessar());
    }

    public function testVinculoRecusadoDeixaProdutoSemVinculoEPermiteReprocessar(): void
    {
        $this->fake
            ->produtoIncluido(codigo: 42)
            ->erro(422, null, 'Problemas encontrados: ', [['mensagem' => 'Campo Fabricante/produtor estrangeiro é obrigatório.', 'codigo' => 'PUCX-ER0101']])
            ->fabricanteVinculado();

        $resultado = $this->produtos()->incluirComFabricantes('12345678', self::request(), [
            Fabricante::operadorEstrangeiro('CN', 'OPE_INEXISTENTE'),
            Fabricante::desconhecido('DE'),
        ]);

        self::assertSame(SituacaoInclusaoComFabricantes::ProdutoSemVinculo, $resultado->situacao());
        self::assertSame([StatusVinculo::Erro, StatusVinculo::Vinculado], array_map(static fn(ResultadoVinculo $v) => $v->status, $resultado->vinculos));
        self::assertInstanceOf(ValidationException::class, $resultado->vinculos[0]->erro);
        self::assertNull($resultado->interrupcao(), 'erro do item não interrompe');

        $reprocessar = $resultado->paraReprocessar();
        self::assertCount(1, $reprocessar);
        self::assertSame('OPE_INEXISTENTE', $reprocessar[0]->codigoOperadorEstrangeiro);
        self::assertSame(42, $reprocessar[0]->codigoProduto);

        $this->fake->fabricanteVinculado();
        $novos = $this->fake->cliente()->catalogo()->fabricantes()->vincularTodos('12345678', $reprocessar);
        self::assertTrue($novos[0]->vinculado());
    }

    public function testRateLimitNoVinculoDeixaRestantePendente(): void
    {
        $this->fake->produtoIncluido(codigo: 42)->rateLimit('16:30');

        $resultado = $this->produtos()->incluirComFabricantes('12345678', self::request(), [Fabricante::desconhecido('DE'), Fabricante::desconhecido('FR')]);

        self::assertSame([StatusVinculo::Pendente, StatusVinculo::Pendente], array_map(static fn(ResultadoVinculo $v) => $v->status, $resultado->vinculos));
        self::assertInstanceOf(RateLimitException::class, $resultado->interrupcao());
        self::assertSame('16:30', $resultado->retomarApos()?->format('H:i'));
        self::assertCount(2, $resultado->paraReprocessar());
        self::assertSame(0, $this->fake->respostasPendentes());
    }

    public function testFalhaDeRedeNoVinculoFicaIncertoENaoEReprocessada(): void
    {
        $this->fake->produtoIncluido(codigo: 42)->falhaDeRede();

        $resultado = $this->produtos()->incluirComFabricantes('12345678', self::request(), [Fabricante::desconhecido('DE'), Fabricante::desconhecido('FR')]);

        self::assertSame(StatusVinculo::Incerto, $resultado->vinculos[0]->status);
        self::assertSame(StatusVinculo::Pendente, $resultado->vinculos[1]->status);
        self::assertSame(['FR'], array_map(static fn(FabricanteRequest $r) => $r->codigoPais->codigo, $resultado->paraReprocessar()));
    }

    public function testErroNaInclusaoDoProdutoELancado(): void
    {
        $this->fake->erroValidacao(['ATT_5426' => 'O Atributo ATT_5426 para o NCM 84733049 é obrigatório.']);

        $this->expectException(ValidationException::class);
        $this->produtos()->incluirComFabricantes('12345678', self::request(), [Fabricante::desconhecido('DE')]);
    }

    public function testLoteComFabricantes(): void
    {
        $this->fake
            ->produtoIncluido(codigo: 1)->fabricanteVinculado()                                  // linha 2: completo
            ->produtoIncluido(codigo: 2)->erro(422, null, 'Operador inexistente.')              // linha 3: sem vínculo
            ->produtoIncluido(codigo: 3)                                                        // linha 4: sem fabricantes
            ->produtoIncluido(codigo: 4)->rateLimit();                                          // linha 5: interrompe no vínculo

        $resultado = $this->produtos()->incluirLote('12345678', [
            'linha-2' => new ProdutoComFabricantes(self::request(), [Fabricante::desconhecido('DE')]),
            'linha-3' => new ProdutoComFabricantes(self::request(), [Fabricante::operadorEstrangeiro('CN', 'OPE_X')]),
            'linha-4' => self::request(),
            'linha-5' => new ProdutoComFabricantes(self::request(), [Fabricante::desconhecido('FR')]),
            'linha-6' => new ProdutoComFabricantes(self::request(), [Fabricante::desconhecido('IT')]),
        ]);

        self::assertSame(
            [StatusItemLote::Incluido, StatusItemLote::IncluidoSemVinculo, StatusItemLote::Incluido, StatusItemLote::IncluidoSemVinculo, StatusItemLote::Pendente],
            array_map(static fn($i) => $i->status, $resultado->itens),
        );
        self::assertCount(4, $resultado->incluidos());
        self::assertInstanceOf(RateLimitException::class, $resultado->interrupcao);
        self::assertSame(['Fabricante OPE_X: Operador inexistente.'], $resultado->itens[1]->mensagens());

        $vinculos = $resultado->vinculosParaReprocessar();
        self::assertSame([2, 4], array_map(static fn(FabricanteRequest $r) => $r->codigoProduto, $vinculos));

        $pendentes = iterator_to_array($resultado->pendentes());
        self::assertSame(['linha-6'], array_keys($pendentes));
        self::assertInstanceOf(ProdutoComFabricantes::class, $pendentes['linha-6']);
        self::assertSame('IT', $pendentes['linha-6']->fabricantes[0]->codigoPais->codigo);
    }

    public function testFabricanteSemProdutoValidaEGeraRequest(): void
    {
        $request = Fabricante::nacional('12.345.678/0001-95', 'BR')->paraProduto(7);

        self::assertSame(['codigoProduto' => 7, 'codigoPais' => 'BR', 'conhecido' => true, 'cpfCnpjFabricante' => '12345678000195'], $request->toArray());
        self::assertSame($request->fabricante->cpfCnpjFabricante, $request->cpfCnpjFabricante);

        $this->expectException(\InvalidArgumentException::class);
        new Fabricante('CN', false, 'OPE_1');
    }

    private function produtos(): \MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\ProdutosResource
    {
        return $this->fake->cliente()->catalogo()->produtos();
    }

    private static function request(): ProdutoRequest
    {
        return new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049');
    }
}
