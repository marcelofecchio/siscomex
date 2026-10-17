<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Testing;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** Falha de rede simulada pelo PortalUnicoFake (vira TransportException no SDK). */
final class FalhaDeRede extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
