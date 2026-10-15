<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Limite de acessos por hora excedido (PUCX-ER1001). O SDK nunca repete a requisição
 * automaticamente neste caso: aguarde até `$liberadoEm`.
 *
 * @see https://docs.portalunico.siscomex.gov.br/pages/limites-acesso/
 */
class RateLimitException extends SiscomexException
{
    /** Horário (America/Sao_Paulo) em que a funcionalidade volta a ser atendida, se informado na mensagem. */
    public ?\DateTimeImmutable $liberadoEm = null;

    /** Limite de acessos por hora informado na mensagem, se houver. */
    public ?int $limite = null;
}
