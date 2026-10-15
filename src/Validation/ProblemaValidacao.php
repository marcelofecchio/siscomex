<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Validation;

final readonly class ProblemaValidacao implements \Stringable
{
    public function __construct(
        public ?string $atributo,
        public string $mensagem,
    ) {}

    public function __toString(): string
    {
        return $this->mensagem;
    }
}
