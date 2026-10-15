<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Integration;

use MarceloFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroProdutos;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;

/**
 * Ciclo completo incluir → consultar → detalhar → nova versão → histórico → desativar,
 * apenas em Validação e apenas com SISCOMEX_CNPJ_RAIZ definido.
 */
final class CatalogoIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (self::env('SISCOMEX_CNPJ_RAIZ') === '') {
            self::markTestSkipped('Defina SISCOMEX_CNPJ_RAIZ para o ciclo do catálogo.');
        }
    }

    public function testCicloCompletoDoProduto(): void
    {
        $cnpj = self::env('SISCOMEX_CNPJ_RAIZ');
        $ncm = self::env('SISCOMEX_NCM_TESTE', '84733049');
        $client = self::client();
        $produtos = $client->catalogo()->produtos();

        $atributos = $this->tolerandoIndisponibilidade(static fn() => $client->atributos()->porNcm($ncm, modalidade: Modalidade::Importacao));
        $request = new ProdutoRequest('Teste integração SDK '.date('YmdHis'), Modalidade::Importacao, $ncm);
        foreach ($atributos->obrigatoriosParaProduto(Modalidade::Importacao) as $atributo) {
            $request = $request->comAtributo($atributo->codigo, match ($atributo->formaPreenchimento) {
                FormaPreenchimento::ListaEstatica => $atributo->dominio[0]->codigo ?? '01',
                FormaPreenchimento::Booleano => 'false',
                FormaPreenchimento::NumeroInteiro, FormaPreenchimento::NumeroReal => '1',
                FormaPreenchimento::Data => date('Y-m-d'),
                default => 'Teste',
            });
        }

        $incluido = $produtos->incluir($cnpj, $request);
        self::assertNotNull($incluido->codigo);
        $codigo = $incluido->codigo;

        $consulta = $produtos->consultar(new FiltroProdutos($cnpj, codigo: $codigo));
        self::assertNotNull($consulta->primeiro());

        $detalhe = $produtos->detalhar($cnpj, $codigo, $incluido->versao ?? '1');
        self::assertSame($request->denominacao, $detalhe?->denominacao);

        $v2 = $produtos->novaVersao($cnpj, $codigo, $request->comDescricao('Versão 2 — teste de integração'));
        self::assertNotSame($incluido->versao, $v2->versao);

        self::assertNotEmpty($produtos->historico($cnpj, $codigo));

        $desativado = $produtos->desativar($cnpj, $codigo);
        if ($desativado !== null) {
            self::assertSame(SituacaoProduto::Desativado, $desativado->situacao);
        }
    }
}
