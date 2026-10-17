<?php

declare(strict_types=1);

/*
 * Operador estrangeiro e vínculo de fabricante a um produto existente — SOMENTE em Validação:
 * incluir operador → vincular ao produto → histórico → desvincular → desativar o operador.
 *
 *   SISCOMEX_AMBIENTE=validacao SISCOMEX_CNPJ_RAIZ=12345678 php examples/operador-e-fabricante.php <codigoProduto>
 */

use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;

require __DIR__.'/bootstrap.php';

if (env('SISCOMEX_AMBIENTE', 'validacao') !== 'validacao') {
    fwrite(STDERR, "Este exemplo grava dados: execute apenas com SISCOMEX_AMBIENTE=validacao.\n");
    exit(1);
}

$cnpjRaiz = env('SISCOMEX_CNPJ_RAIZ') ?? exit("Defina SISCOMEX_CNPJ_RAIZ.\n");
$codigoProduto = (int) ($argv[1] ?? exit("Informe o código de um produto ativo: php examples/operador-e-fabricante.php 123\n"));

try {
    $catalogo = criarCliente()->catalogo();
    $operadores = $catalogo->operadoresEstrangeiros();
    $fabricantes = $catalogo->fabricantes();

    $operador = $operadores->incluir($cnpjRaiz, 'CN', new OperadorEstrangeiroRequest('Fornecedor de teste SDK '.date('His'), 'No. 1 Keji Road', 'Shenzhen')
        ->comSubdivisao('CN-GD')
        ->comCodigoInterno('FORN-SDK'));
    printf("Operador %s v%s incluído (%s)\n", $operador->codigo, $operador->versao, $operador->situacao?->value);

    $vinculo = FabricanteRequest::operadorEstrangeiro($codigoProduto, 'CN', (string) $operador->codigo);
    $fabricantes->vincular($cnpjRaiz, $vinculo);
    foreach ($fabricantes->historico($cnpjRaiz, $codigoProduto) as $evento) {
        printf("  %s %s %s %s\n", $evento->data?->format('d/m/Y H:i'), $evento->operacao, $evento->codigoPais, $evento->codigo ?? '(desconhecido)');
    }

    $fabricantes->desvincular($cnpjRaiz, $vinculo);
    $operadores->desativar($cnpjRaiz, 'CN', (string) $operador->codigo, (string) $operador->versao);
    echo "Vínculo removido e operador desativado.\n";
} catch (ValidationException $e) {
    foreach ($e->mensagensPorCampo() as $campo => $mensagens) {
        fwrite(STDERR, ($campo === '' ? '' : $campo.': ').implode('; ', $mensagens).PHP_EOL);
    }
    exit(1);
} catch (SiscomexException $e) {
    fwrite(STDERR, $e->resumoParaChamado().PHP_EOL);
    exit(1);
}
