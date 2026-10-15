<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Validation;

use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\Atributo;
use MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributosDaNcm;
use MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\RelacaoAtributosNcm;
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;
use MFecchio\Siscomex\PortalUnico\Validation\ProblemaValidacao;
use MFecchio\Siscomex\PortalUnico\Validation\ValidadorProduto;
use MFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

final class ValidadorProdutoTest extends TestCase
{
    private AtributosDaNcm $atributos;

    private \DateTimeImmutable $hoje;

    protected function setUp(): void
    {
        parent::setUp();
        $relacao = RelacaoAtributosNcm::fromArray(self::fixture('validacao', 'atributos-por-ncm-amostra.json'));
        $this->atributos = $relacao->atributosDaNcm('84733049') ?? throw new \RuntimeException('fixture');
        $this->hoje = new \DateTimeImmutable('2026-10-15');
    }

    public function testProdutoCompletoNaoTemProblemas(): void
    {
        $produto = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')
            ->comAtributo('ATT_5426', 'false')
            ->comAtributo('ATT_5333', 'false')
            ->comAtributo('ATT_7866', 'Silício')
            ->comAtributo('ATT_18551', '99');

        self::assertSame([], new ValidadorProduto()->validar($produto, $this->atributos, $this->hoje));
    }

    public function testDetectaObrigatoriosAusentesEValoresInvalidos(): void
    {
        $produto = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')
            ->comAtributo('ATT_5426', 'sim')
            ->comAtributo('ATT_18551', 'ZZ')
            ->comAtributo('ATT_14545', '99');

        $problemas = new ValidadorProduto()->validar($produto, $this->atributos, $this->hoje);
        $porAtributo = array_map(static fn(ProblemaValidacao $p): string => (string) $p->atributo, $problemas);

        self::assertContains('ATT_5333', $porAtributo, 'obrigatório ausente');
        self::assertContains('ATT_7866', $porAtributo, 'obrigatório ausente');
        self::assertContains('ATT_14545', $porAtributo, 'código de produção usado em validação');
        self::assertContains('ATT_5426', $porAtributo, 'booleano inválido');
        self::assertContains('ATT_18551', $porAtributo, 'fora do domínio');
        self::assertStringContainsString('Validação e Produção', implode("\n", array_map(strval(...), $problemas)));
    }

    public function testAtributoSemObjetivoProdutoNaoEAceito(): void
    {
        $produto = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')->comAtributo('ATT_11303', 'SN123');

        $problemas = new ValidadorProduto()->validar($produto, $this->atributos, $this->hoje);

        self::assertContains('ATT_11303', array_map(static fn(ProblemaValidacao $p): ?string => $p->atributo, $problemas));
    }

    public function testNcmDiferente(): void
    {
        $produto = new ProdutoRequest('X', Modalidade::Importacao, '02011000');

        $problemas = new ValidadorProduto()->validar($produto, $this->atributos);

        self::assertCount(1, $problemas);
        self::assertNull($problemas[0]->atributo);
    }

    public function testTamanhoMaximoEInteiroEMultivalorado(): void
    {
        $atributos = new AtributosDaNcm(new Ncm('02011000'), [
            Atributo::fromArray(['codigo' => 'ATT_T', 'nomeApresentacao' => 'T', 'formaPreenchimento' => 'TEXTO', 'tamanhoMaximo' => 3, 'modalidade' => 'Importação', 'obrigatorio' => false, 'multivalorado' => false, 'objetivos' => [['codigo' => 7, 'descricao' => 'Produto']]]),
            Atributo::fromArray(['codigo' => 'ATT_I', 'nomeApresentacao' => 'I', 'formaPreenchimento' => 'NUMERO_INTEIRO', 'modalidade' => 'Importação', 'obrigatorio' => false, 'multivalorado' => false, 'objetivos' => [['codigo' => 7, 'descricao' => 'Produto']]]),
            Atributo::fromArray(['codigo' => 'ATT_M', 'nomeApresentacao' => 'M', 'formaPreenchimento' => 'TEXTO', 'modalidade' => 'Importação', 'obrigatorio' => false, 'multivalorado' => true, 'objetivos' => [['codigo' => 7, 'descricao' => 'Produto']]]),
        ]);
        $produto = new ProdutoRequest('X', Modalidade::Importacao, '02011000')
            ->comAtributo('ATT_T', 'abcd')
            ->comAtributo('ATT_I', '1.5')
            ->comAtributo('ATT_M', 'x');

        $problemas = new ValidadorProduto()->validar($produto, $atributos, $this->hoje);

        self::assertSame(['ATT_T', 'ATT_I', 'ATT_M'], array_values(array_unique(array_map(static fn(ProblemaValidacao $p): ?string => $p->atributo, $problemas))));
    }
}
