<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Falha de autenticação (HTTP 401 ou códigos PLAT-ER e PUCX-ER0101/0102).
 */
class AuthenticationException extends SiscomexException {}
