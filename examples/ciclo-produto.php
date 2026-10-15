<?php

declare(strict_types=1);

/*
 * Ciclo completo de um produto no Catálogo — SOMENTE no ambiente de Validação:
 * atributos da NCM → validar localmente → incluir → detalhar → nova versão → desativar.
 *
 *   SISCOMEX_AMBIENTE=validacao SISCOMEX_CNPJ_RAIZ=12345678 php examples/ciclo-produto.php 84733049
 *
 * Os atributos são preenchidos automaticamente com valores de exemplo (primeiro item do
 * domínio, "false" para booleanos, texto genérico) — apenas para demonstração.
 */

use MFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MFecchio\Siscomex\PortalUnico\Validation\ValidadorProduto;

require __DIR__.'/bootstrap.php';

if (env('SISCOMEX_AMBIENTE', 'validacao') !== 'validacao') {
    fwrite(STDERR, "Este exemplo grava dados: execute apenas com SISCOMEX_AMBIENTE=validacao.\n");
    exit(1);
}

$cnpjRaiz = env('SISCOMEX_CNPJ_RAIZ') ?? exit("Defina SISCOMEX_CNPJ_RAIZ.\n");
$ncm = $argv[1] ?? '84733049';
$modalidade = Modalidade::Importacao;

try {
    $client = criarCliente();
    $produtos = $client->catalogo()->produtos();

    $atributos = $client->atributos()->porNcm($ncm, modalidade: $modalidade);
    $produto = new ProdutoRequest('Produto de teste SDK '.date('YmdHis'), $modalidade, $ncm)
        ->comDescricao('Cadastrado pelo exemplo examples/ciclo-produto.php');

    foreach ($atributos->obrigatoriosParaProduto($modalidade) as $atributo) {
        $valor = match ($atributo->formaPreenchimento) {
            FormaPreenchimento::ListaEstatica => $atributo->dominio[0]->codigo ?? '01',
            FormaPreenchimento::Booleano => 'false',
            FormaPreenchimento::NumeroInteiro, FormaPreenchimento::NumeroReal => '1',
            FormaPreenchimento::Data => date('Y-m-d'),
            default => 'Teste',
        };
        $produto = $produto->comAtributo($atributo->codigo, $valor);
    }

    $problemas = new ValidadorProduto()->validar($produto, $atributos);
    foreach ($problemas as $problema) {
        echo "Aviso (validação local): {$problema}\n";
    }

    $incluido = $produtos->incluir($cnpjRaiz, $produto);
    printf("Incluído: código %d, versão %s, situação %s\n", $incluido->codigo, $incluido->versao, $incluido->situacao?->value);

    $codigo = $incluido->codigo ?? exit("Portal não retornou o código.\n");
    $detalhe = $produtos->detalhar($cnpjRaiz, $codigo, $incluido->versao ?? '1');
    printf("Detalhe: %s\n", $detalhe?->denominacao);

    $versao2 = $produtos->novaVersao($cnpjRaiz, $codigo, $produto->comDescricao('Versão 2'));
    printf("Nova versão: %s\n", $versao2->versao);

    $desativado = $produtos->desativar($cnpjRaiz, $codigo);
    printf("Desativado: %s\n", $desativado?->situacao->value ?? 'ok (204)');
} catch (ValidationException $e) {
    fwrite(STDERR, 'Validação: '.implode('; ', $e->mensagens()).' | tag '.$e->tag."\n");
    exit(1);
} catch (SiscomexException $e) {
    fwrite(STDERR, 'Erro: '.$e->resumoParaChamado()."\n");
    exit(1);
}
