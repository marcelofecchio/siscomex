<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Portal indisponível (HTTP 503, PUCX-ER0401 parada programada ou manutenção do ambiente de Validação).
 */
class ServiceUnavailableException extends SiscomexException {}
