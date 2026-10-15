<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Config;

/**
 * O que fazer quando for necessário autenticar antes de completar 60 s desde a
 * última autenticação bem-sucedida (o portal recusa com PLAT-ER2033).
 */
enum PoliticaIntervaloAutenticacao
{
    /** Aguarda (sleep) o tempo restante e então autentica. Indicado para CLI/jobs. */
    case Aguardar;

    /** Lança AuthenticationThrottledException imediatamente. Indicado para requisições web. */
    case LancarExcecao;
}
