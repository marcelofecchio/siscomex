<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Certificado digital ausente, inválido ou não habilitado (PLAT-ER2001, PLAT-ER8001, PLAT-ER8002) ou erro ao ler o arquivo local.
 */
class CertificateException extends AuthenticationException {}
