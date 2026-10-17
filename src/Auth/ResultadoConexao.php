<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Auth;

use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationThrottledException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\CertificateException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;

/**
 * Resultado de `PortalUnicoClient::testarConexao()`, pronto para um botão "Testar conexão".
 * Nunca contém o JWT nem o CSRF.
 */
final readonly class ResultadoConexao
{
    public function __construct(
        public bool $sucesso,
        public Ambiente $ambiente,
        public Perfil $perfil,
        public string $mensagem,
        public ?\DateTimeImmutable $autenticadoEm = null,
        public ?\DateTimeImmutable $sessaoExpiraEm = null,
        public ?SiscomexException $erro = null,
    ) {}

    public static function sucesso(Ambiente $ambiente, Perfil $perfil, Session $sessao): self
    {
        return new self(
            true,
            $ambiente,
            $perfil,
            \sprintf('Conectado ao Portal Único (%s, perfil %s).', $ambiente->nome(), $perfil->value),
            $sessao->authenticatedAt,
            $sessao->csrfExpiresAt,
        );
    }

    public static function falha(Ambiente $ambiente, Perfil $perfil, SiscomexException $erro): self
    {
        $mensagem = match (true) {
            $erro instanceof AuthenticationThrottledException => 'O portal exige 60 segundos entre autenticações. Tente novamente em instantes.',
            $erro instanceof CertificateException => 'Certificado digital recusado: verifique o arquivo, a senha e se é um e-CPF A1 válido.',
            $erro instanceof AuthenticationException => \sprintf('Credenciais recusadas. Confira se as chaves de acesso foram geradas no ambiente de %s (as chaves de Produção não valem na Validação e vice-versa).', $ambiente->nome()),
            $erro instanceof RateLimitException => 'Limite de acessos do portal atingido. Tente novamente mais tarde.',
            $erro instanceof ServiceUnavailableException => 'Portal Único indisponível no momento (o ambiente de Validação tem janelas de manutenção).',
            $erro instanceof TransportException => 'Não foi possível se comunicar com o Portal Único (rede, DNS, firewall ou TLS).',
            default => 'Falha ao conectar ao Portal Único.',
        };

        return new self(false, $ambiente, $perfil, $mensagem.' Detalhes: '.$erro->resumoParaChamado(), erro: $erro);
    }
}
