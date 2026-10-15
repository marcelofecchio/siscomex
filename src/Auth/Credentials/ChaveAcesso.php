<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Auth\Credentials;

/**
 * Par de Chaves de Acesso gerado pelo usuário em "Perfil do usuário → Chaves de acesso".
 *
 * Autentica em POST /portal/api/autenticar/chave-acesso com os headers Client-Id e
 * Client-Secret, sem certificado digital. As chaves são vinculadas ao ambiente em que
 * foram geradas (chaves de produção são recusadas em Validação com PLAT-ER8007).
 *
 * @see https://docs.portalunico.siscomex.gov.br/pages/chaves-acesso/
 */
final readonly class ChaveAcesso implements Credential
{
    public function __construct(
        #[\SensitiveParameter]
        private string $clientId,
        #[\SensitiveParameter]
        private string $clientSecret,
    ) {
        if (trim($clientId) === '' || trim($clientSecret) === '') {
            throw new \InvalidArgumentException('Client-Id e Client-Secret são obrigatórios.');
        }
    }

    /**
     * Lê as chaves das variáveis de ambiente SISCOMEX_CLIENT_ID e SISCOMEX_CLIENT_SECRET.
     */
    public static function fromEnv(string $prefixo = 'SISCOMEX_'): self
    {
        $id = getenv($prefixo.'CLIENT_ID');
        $secret = getenv($prefixo.'CLIENT_SECRET');
        if (!\is_string($id) || !\is_string($secret) || $id === '' || $secret === '') {
            throw new \InvalidArgumentException(\sprintf('Defina %1$sCLIENT_ID e %1$sCLIENT_SECRET.', $prefixo));
        }

        return new self($id, $secret);
    }

    public function endpointAutenticacao(): string
    {
        return '/portal/api/autenticar/chave-acesso';
    }

    public function headersAutenticacao(): array
    {
        return [
            'Client-Id' => $this->clientId,
            'Client-Secret' => $this->clientSecret,
        ];
    }

    public function exigeMtls(): bool
    {
        return false;
    }

    public function identificador(): string
    {
        return 'chave-'.substr(hash('sha256', $this->clientId), 0, 16);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['clientId' => '***', 'clientSecret' => '***'];
    }
}
