<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Operação de escrita recusada pelo SDK em produção (proteção de testes de integração).
 */
class ProductionWriteBlockedException extends SiscomexException {}
