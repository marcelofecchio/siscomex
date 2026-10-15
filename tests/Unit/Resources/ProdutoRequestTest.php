<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Produto;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProdutoRequestTest extends TestCase
{
    public function testImutabilidade(): void
    {
        $base = new ProdutoRequest('Parafuso', Modalidade::Exportacao, '73181500');
        $comAtributo = $base->comAtributo('ATT_1', '01');

        self::assertSame([], $base->atributos);
        self::assertCount(1, $comAtributo->atributos);
    }

    public function testAtributosCompostosEMultivalorados(): void
    {
        $produto = new ProdutoRequest('Parafuso', Modalidade::Exportacao, '73181500')
            ->comAtributoMultivalorado('ATT_M', ['1', '2'])
            ->comAtributoComposto('ATT_C', ['ATT_C1' => 'a', 'ATT_C2' => 'b']);

        $dados = $produto->toArray();
        self::assertSame([['atributo' => 'ATT_M', 'valores' => ['1', '2']]], $dados['atributosMultivalorados']);
        self::assertSame([['atributo' => 'ATT_C', 'valores' => [['atributo' => 'ATT_C1', 'valor' => 'a'], ['atributo' => 'ATT_C2', 'valor' => 'b']]]], $dados['atributosCompostos']);
        self::assertArrayNotHasKey('descricao', $dados);
    }

    public function testFromArray(): void
    {
        $produto = ProdutoRequest::fromArray(['denominacao' => 'X', 'modalidade' => 'EXPORTACAO', 'ncm' => '02011000', 'atributos' => [['atributo' => 'ATT_1', 'valor' => 'v']]]);

        self::assertSame(Modalidade::Exportacao, $produto->modalidade);
        self::assertSame('v', $produto->atributos[0]->valor);

        $this->expectException(\InvalidArgumentException::class);
        ProdutoRequest::fromArray(['denominacao' => 'X']);
    }

    public function testParaRequestSemNcmFalha(): void
    {
        $this->expectException(\LogicException::class);
        Produto::fromArray(['denominacao' => 'X', 'ncm' => 'invalida'])->paraRequest();
    }

    /** @return iterable<string, array{\Closure(): mixed}> */
    public static function invalidos(): iterable
    {
        yield 'modalidade ambos' => [static fn() => new ProdutoRequest('X', Modalidade::Ambos, '02011000')];
        yield 'denominação vazia' => [static fn() => new ProdutoRequest('  ', Modalidade::Importacao, '02011000')];
        yield 'denominação longa' => [static fn() => new ProdutoRequest(str_repeat('a', 121), Modalidade::Importacao, '02011000')];
        yield 'descrição longa' => [static fn() => new ProdutoRequest('X', Modalidade::Importacao, '02011000', str_repeat('a', 3701))];
        yield 'código interno longo' => [static fn() => new ProdutoRequest('X', Modalidade::Importacao, '02011000')->comCodigoInterno(str_repeat('a', 61))];
        yield 'atributo vazio' => [static fn() => new ProdutoRequest('X', Modalidade::Importacao, '02011000')->comAtributo('ATT_1', '')];
        yield 'código de atributo longo' => [static fn() => new ProdutoRequest('X', Modalidade::Importacao, '02011000')->comAtributo(str_repeat('A', 26), 'x')];
        yield 'subatributo longo' => [static fn() => new ProdutoRequest('X', Modalidade::Importacao, '02011000')->comAtributoComposto('ATT_C', ['ATT_C1' => str_repeat('x', 101)])];
        yield 'NCM inválida' => [static fn() => new ProdutoRequest('X', Modalidade::Importacao, '123')];
    }

    /** @param \Closure(): mixed $criar */
    #[DataProvider('invalidos')]
    public function testValidacoes(\Closure $criar): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $criar();
    }
}
