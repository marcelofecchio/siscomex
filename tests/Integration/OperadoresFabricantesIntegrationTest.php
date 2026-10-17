<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Integration;

use MarceloFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoCodigoInterno;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoOperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroOperadoresEstrangeiros;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;

/**
 * Operador estrangeiro → produto → vínculo de fabricante → código interno, apenas em
 * Validação e com SISCOMEX_CNPJ_RAIZ definido. Tudo o que é criado é desativado no fim.
 */
final class OperadoresFabricantesIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (self::env('SISCOMEX_CNPJ_RAIZ') === '') {
            self::markTestSkipped('Defina SISCOMEX_CNPJ_RAIZ para o ciclo de operadores e fabricantes.');
        }
    }

    public function testOperadorFabricanteECodigoInterno(): void
    {
        $cnpj = self::env('SISCOMEX_CNPJ_RAIZ');
        $ncm = self::env('SISCOMEX_NCM_TESTE', '84733049');
        $client = self::client();
        $catalogo = $client->catalogo();
        $sufixo = date('YmdHis');
        $codigoInterno = 'SKU-INT-'.$sufixo;

        $operadorRequest = new OperadorEstrangeiroRequest('Teste integração SDK '.$sufixo, 'No. 1 Keji Road', 'Shenzhen')
            ->comSubdivisao('CN-GD')
            ->comCodigoInterno('FORN-'.$sufixo);
        $operador = $this->tolerandoIndisponibilidade(static fn() => $catalogo->operadoresEstrangeiros()->incluir($cnpj, 'CN', $operadorRequest));
        $codigoOperador = (string) $operador->codigo;
        $versaoOperador = $operador->versao ?? '1';
        $produto = null;

        try {
            self::assertSame(SituacaoOperadorEstrangeiro::Ativado, $operador->situacao);
            $consulta = $catalogo->operadoresEstrangeiros()->consultar(new FiltroOperadoresEstrangeiros($cnpj, codigo: $codigoOperador));
            self::assertSame($codigoOperador, $consulta->primeiro()?->codigo);

            $v2 = $catalogo->operadoresEstrangeiros()->novaVersao($cnpj, 'CN', $codigoOperador, $operadorRequest->comEmail('v2@example.com'));
            $versaoOperador = $v2->versao ?? $versaoOperador;
            self::assertNotEmpty($catalogo->operadoresEstrangeiros()->historico($cnpj, 'CN', $codigoOperador));

            self::assertSame(SituacaoCodigoInterno::Livre, $catalogo->produtos()->buscarPorCodigoInterno($cnpj, $codigoInterno)->situacao());

            $atributos = $client->atributos()->porNcm($ncm, modalidade: Modalidade::Importacao);
            $request = new ProdutoRequest('Teste fabricante SDK '.$sufixo, Modalidade::Importacao, $ncm)->comCodigoInterno($codigoInterno);
            foreach ($atributos->obrigatoriosParaProduto(Modalidade::Importacao) as $atributo) {
                $request = $request->comAtributo($atributo->codigo, match ($atributo->formaPreenchimento) {
                    FormaPreenchimento::ListaEstatica => $atributo->dominio[0]->codigo ?? '01',
                    FormaPreenchimento::Booleano => 'false',
                    FormaPreenchimento::NumeroInteiro, FormaPreenchimento::NumeroReal => '1',
                    FormaPreenchimento::Data => date('Y-m-d'),
                    default => 'Teste',
                });
            }
            $pendentes = array_diff($atributos->obrigatoriosPara($request)->codigos(), $request->codigosAtributosInformados());
            self::assertSame([], array_values($pendentes), 'nenhum condicionado obrigatório pendente');

            $produto = $catalogo->produtos()->incluir($cnpj, $request);
            $codigoProduto = (int) $produto->codigo;
            self::assertSame(SituacaoCodigoInterno::Ativo, $catalogo->produtos()->buscarPorCodigoInterno($cnpj, $codigoInterno)->situacao());

            $vinculo = FabricanteRequest::operadorEstrangeiro($codigoProduto, 'CN', $codigoOperador);
            self::assertTrue($catalogo->fabricantes()->vincular($cnpj, $vinculo)?->conhecido);
            $catalogo->fabricantes()->vincular($cnpj, FabricanteRequest::desconhecido($codigoProduto, 'DE'));

            $historico = $catalogo->fabricantes()->historico($cnpj, $codigoProduto);
            self::assertCount(2, $historico);
            self::assertTrue($historico[0]->inclusao());

            $catalogo->fabricantes()->desvincular($cnpj, $vinculo);
            $catalogo->fabricantes()->desvincular($cnpj, FabricanteRequest::desconhecido($codigoProduto, 'DE'));
        } finally {
            if ($produto?->codigo !== null) {
                $catalogo->produtos()->desativar($cnpj, $produto->codigo);
            }
            $catalogo->operadoresEstrangeiros()->desativar($cnpj, 'CN', $codigoOperador, $versaoOperador);
        }

        self::assertSame(SituacaoCodigoInterno::Desativado, $catalogo->produtos()->buscarPorCodigoInterno($cnpj, $codigoInterno)->situacao());
    }
}
