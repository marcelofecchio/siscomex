<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials;

/**
 * Arquivo PEM gerado a partir de um .pfx; removido do disco ao ser destruído.
 */
final class CertificadoPemTemporario
{
    public function __construct(
        public readonly string $caminho,
        private readonly bool $apagarAoDestruir,
    ) {}

    public function apagar(): void
    {
        if ($this->apagarAoDestruir && is_file($this->caminho)) {
            @unlink($this->caminho);
        }
    }

    public function __destruct()
    {
        $this->apagar();
    }
}
