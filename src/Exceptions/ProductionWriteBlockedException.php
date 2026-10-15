<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Operação de escrita recusada pelo SDK em produção (proteção de testes de integração).
 */
class ProductionWriteBlockedException extends SiscomexException {}
