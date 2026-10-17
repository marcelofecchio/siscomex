<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Config;

/**
 * Configuração imutável do cliente.
 */
final readonly class ClientConfig
{
    /** Intervalo mínimo exigido pelo portal entre autenticações (Comunicados nº 014/2024 e 010/2025). */
    public const int INTERVALO_MINIMO_AUTENTICACAO = 60;

    /**
     * @param int  $timeout              timeout (s) por requisição — usado pelas fábricas de cliente HTTP
     * @param int  $tentativas503        quantas vezes repetir uma requisição após 503/erro de rede
     * @param int  $backoffInicialMs     espera antes da 1ª repetição; dobra a cada nova tentativa
     * @param int  $margemExpiracao      segundos de folga antes da expiração do CSRF para considerar a sessão vencida
     * @param bool $bloquearEscritaEmProducao recusa POST/PUT/DELETE em produção; se null, ativa automaticamente
     *                                        quando SISCOMEX_RUN_INTEGRATION=1
     */
    public function __construct(
        public Ambiente $ambiente = Ambiente::Validacao,
        public Perfil $perfil = Perfil::IMPEXP,
        public PoliticaIntervaloAutenticacao $politicaIntervalo = PoliticaIntervaloAutenticacao::Aguardar,
        public int $timeout = 60,
        public int $tentativas503 = 2,
        public int $backoffInicialMs = 1000,
        public int $margemExpiracao = 60,
        public ?bool $bloquearEscritaEmProducao = null,
        public string $userAgent = 'marcelofecchio-siscomex-sdk/0.2',
    ) {
        if ($tentativas503 < 0 || $backoffInicialMs < 0 || $margemExpiracao < 0 || $timeout < 1) {
            throw new \InvalidArgumentException('Parâmetros numéricos de ClientConfig devem ser positivos.');
        }
    }

    public function escritaBloqueada(): bool
    {
        if (!$this->ambiente->isProducao()) {
            return false;
        }

        return $this->bloquearEscritaEmProducao ?? (getenv('SISCOMEX_RUN_INTEGRATION') === '1');
    }
}
