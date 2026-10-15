<?php

declare(strict_types=1);

/*
 * Consulta os atributos de uma NCM (somente leitura).
 *
 *   php examples/consultar-atributos-ncm.php 84733049 [IMPORTACAO|EXPORTACAO]
 */

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

require __DIR__.'/bootstrap.php';

$ncm = $argv[1] ?? '84733049';
$modalidade = Modalidade::fromDescricao($argv[2] ?? 'IMPORTACAO') ?? Modalidade::Importacao;

try {
    $client = criarCliente();
    $atributos = $client->atributos()->porNcm($ncm, modalidade: $modalidade);

    printf("NCM %s — %d atributo(s) (%s)\n", $atributos->ncm->formatado, \count($atributos), $modalidade->value);
    foreach ($atributos as $atributo) {
        printf(
            "  %-10s %-3s %-16s %s\n",
            $atributo->codigo,
            $atributo->obrigatorio === true ? '*' : '',
            $atributo->formaPreenchimentoOriginal ?? '-',
            $atributo->nomeApresentacao,
        );
    }

    echo "\nObrigatórios para cadastro de produto:\n";
    foreach ($atributos->obrigatoriosParaProduto($modalidade) as $atributo) {
        printf("  %s — %s\n", $atributo->codigo, $atributo->nomeApresentacao);
    }
} catch (SiscomexException $e) {
    fwrite(STDERR, 'Erro: '.$e->resumoParaChamado()."\n");
    exit(1);
}
