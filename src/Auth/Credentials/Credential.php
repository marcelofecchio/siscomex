<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials;

/**
 * Forma de autenticação no serviço "plat-auth" do portal.
 */
interface Credential
{
    /** Caminho do endpoint de autenticação, relativo à URL base do ambiente. */
    public function endpointAutenticacao(): string;

    /**
     * Headers específicos da credencial (além de Role-Type).
     *
     * @return array<string, string>
     */
    public function headersAutenticacao(): array;

    /** A credencial exige certificado no handshake TLS (mTLS)? */
    public function exigeMtls(): bool;

    /**
     * Identificador estável e não sensível da credencial, usado na chave de cache da sessão.
     */
    public function identificador(): string;
}
