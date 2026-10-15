<?php

declare(strict_types=1);

/*
 * Download público (sem credencial) da relação de atributos por NCM.
 *
 *   php examples/atributos-publicos.php 84733049 [validacao|producao]
 */

use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\PortalUnicoClient;

require __DIR__.'/../vendor/autoload.php';

$ncm = $argv[1] ?? '84733049';
$ambiente = Ambiente::fromString($argv[2] ?? 'validacao');

$relacao = PortalUnicoClient::create(ambiente: $ambiente)->atributosPublicos()->baixarPorNcm();
$atributos = $relacao->atributosDaNcm($ncm);

if ($atributos === null) {
    exit("NCM {$ncm} não encontrada na relação versão {$relacao->versao}.\n");
}

printf("Relação versão %s (%s) — NCM %s: %d atributo(s)\n", $relacao->versao, $ambiente->value, $atributos->ncm->formatado, \count($atributos));
foreach ($atributos as $a) {
    printf("  %-10s %-11s %-4s %s\n", $a->codigo, $a->modalidade->value ?? '-', $a->obrigatorio ? 'obr' : '', $a->nomeApresentacao);
}
