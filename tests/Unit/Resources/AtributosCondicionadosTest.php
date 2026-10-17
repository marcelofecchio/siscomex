<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\Atributo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributoCondicionado;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributosDaNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\CondicaoAtributo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\RelacaoAtributosNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AtributosCondicionadosTest extends TestCase
{
    /** @return iterable<string, array{array<mixed>, list<string>, bool|null}> */
    public static function condicoes(): iterable
    {
        $igual = ['operador' => '==', 'valor' => '01'];
        $ou = ['operador' => '==', 'valor' => '80', 'composicao' => '||', 'condicao' => ['operador' => '==', 'valor' => '81']];
        // Formato real (Produção, ATT_14545 → ATT_14546): Diferente '99' E Diferente '97'.
        $e = ['operador' => '!=', 'valor' => '99', 'composicao' => '&&', 'condicao' => ['operador' => '!=', 'valor' => '97']];

        yield 'igual satisfeito' => [$igual, ['01'], true];
        yield 'igual não satisfeito' => [$igual, ['02'], false];
        yield 'condicionante não informado' => [$igual, [], false];
        yield 'ou: segundo termo' => [$ou, ['81'], true];
        yield 'ou: nenhum' => [$ou, ['82'], false];
        yield 'e: diferente dos dois' => [$e, ['01'], true];
        yield 'e: igual a um' => [$e, ['97'], false];
        yield 'e: sem valor' => [$e, [], false];
        yield 'maior ou igual' => [['operador' => '>=', 'valor' => '10'], ['10'], true];
        yield 'maior' => [['operador' => '>', 'valor' => '10'], ['9.5'], false];
        yield 'menor ou igual' => [['operador' => '<=', 'valor' => '10'], ['3'], true];
        yield 'numérico com texto' => [['operador' => '>', 'valor' => '10'], ['abc'], null];
        yield 'operador desconhecido' => [['operador' => '~', 'valor' => 'x'], ['x'], null];
        yield 'ou com indeterminado e verdadeiro' => [['operador' => '~', 'valor' => 'x', 'composicao' => '||', 'condicao' => ['operador' => '==', 'valor' => 'y']], ['y'], true];
        yield 'e com indeterminado e falso' => [['operador' => '~', 'valor' => 'x', 'composicao' => '&&', 'condicao' => ['operador' => '==', 'valor' => 'y']], ['z'], false];
        yield 'multivalorado: algum igual' => [$igual, ['02', '01'], true];
        yield 'multivalorado: diferente exige nenhum igual' => [['operador' => '!=', 'valor' => '99'], ['01', '99'], false];
    }

    /**
     * @param array<mixed> $condicao
     * @param list<string> $valores
     */
    #[DataProvider('condicoes')]
    public function testAvaliaCondicao(array $condicao, array $valores, ?bool $esperado): void
    {
        self::assertSame($esperado, CondicaoAtributo::fromArray($condicao)->avaliar($valores));
    }

    public function testCondicionadosDaFixtureDeValidacao(): void
    {
        $condicionados = $this->atributos()->condicionadosParaProduto(Modalidade::Importacao, self::hoje());

        $pares = array_map(static fn(AtributoCondicionado $c): string => $c->codigoCondicionante.'>'.$c->atributo->codigo, $condicionados);
        self::assertContains('ATT_5333>ATT_10008', $pares);
        self::assertContains('ATT_5333>ATT_10015', $pares);
        self::assertContains('ATT_18551>ATT_18490', $pares);
        self::assertSame("'ATT_5333' Igual 'true'", $this->atributos()->condicionadoPorCodigo('ATT_10008')?->descricaoCondicao, 'sem o espaço inicial do portal');
        self::assertNull($this->atributos()->condicionadoPorCodigo('ATT_5333'), 'condicionante de topo não é condicionado');
    }

    public function testObrigatoriosParaIncluiCondicionadosSatisfeitos(): void
    {
        $base = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')->comAtributo('ATT_18551', '81');
        $atributos = $this->atributos();

        $semCondicao = $atributos->obrigatoriosPara($base->comAtributo('ATT_5333', 'false'), self::hoje())->codigos();
        self::assertNotContains('ATT_10008', $semCondicao);
        self::assertSame($atributos->obrigatoriosParaProduto(Modalidade::Importacao, self::hoje())->codigos(), $semCondicao);

        $comCondicao = $atributos->obrigatoriosPara($base->comAtributo('ATT_5333', 'true'), self::hoje());
        self::assertContains('ATT_10008', $comCondicao->codigos());
        self::assertContains('ATT_10015', $comCondicao->codigos());
        self::assertTrue($comCondicao->porCodigo('ATT_10008')?->obrigatorio);

        $aplicaveis = $atributos->aplicaveisPara($base, self::hoje());
        self::assertFalse($aplicaveis->porCodigo('ATT_18490')?->obrigatorio, 'condicionado opcional (OU encadeado) entra como aplicável');
    }

    public function testCondicionadoEmCascata(): void
    {
        $neto = ['codigo' => 'ATT_C', 'nomeApresentacao' => 'Neto', 'objetivos' => [['codigo' => 7, 'descricao' => 'Produto']]];
        $filho = [
            'codigo' => 'ATT_B', 'nomeApresentacao' => 'Filho', 'atributoCondicionante' => true,
            'objetivos' => [['codigo' => 7, 'descricao' => 'Produto']],
            'condicionados' => [['obrigatorio' => true, 'multivalorado' => false, 'atributo' => $neto, 'condicao' => ['operador' => '==', 'valor' => 'S']]],
        ];
        $pai = Atributo::fromArray([
            'codigo' => 'ATT_A', 'nomeApresentacao' => 'Pai', 'modalidade' => 'Importação', 'obrigatorio' => true, 'atributoCondicionante' => true,
            'objetivos' => [['codigo' => 7, 'descricao' => 'Produto']],
            'condicionados' => [['obrigatorio' => true, 'multivalorado' => false, 'atributo' => $filho, 'condicao' => ['operador' => '==', 'valor' => '1']]],
        ]);
        $atributos = new AtributosDaNcm(new \MarceloFecchio\Siscomex\PortalUnico\ValueObject\Ncm('84733049'), [$pai]);
        $produto = new ProdutoRequest('P', Modalidade::Importacao, '84733049')->comAtributo('ATT_A', '1');

        self::assertSame(['ATT_A', 'ATT_B'], $atributos->obrigatoriosPara($produto)->codigos());
        self::assertSame(['ATT_A', 'ATT_B', 'ATT_C'], $atributos->obrigatoriosPara($produto->comAtributo('ATT_B', 'S'))->codigos());
        self::assertSame('ATT_B', $atributos->condicionadoPorCodigo('ATT_C')?->codigoCondicionante);
    }

    public function testValoresDoAtributoConsideraMultivaloradosECompostos(): void
    {
        $produto = new ProdutoRequest('P', Modalidade::Importacao, '84733049')
            ->comAtributo('ATT_1', 'a')
            ->comAtributoMultivalorado('ATT_2', ['b', 'c'])
            ->comAtributoComposto('ATT_3', ['ATT_31' => 'd']);

        self::assertSame(['a'], $produto->valoresDoAtributo('ATT_1'));
        self::assertSame(['b', 'c'], $produto->valoresDoAtributo('ATT_2'));
        self::assertSame(['d'], $produto->valoresDoAtributo('ATT_31'));
        self::assertSame([], $produto->valoresDoAtributo('ATT_X'));
    }

    private function atributos(): AtributosDaNcm
    {
        return RelacaoAtributosNcm::fromArray(self::fixture('validacao', 'atributos-por-ncm-amostra.json'))->atributosDaNcm('84733049')
            ?? throw new \RuntimeException('fixture');
    }

    private static function hoje(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-15');
    }
}
