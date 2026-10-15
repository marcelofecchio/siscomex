<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Config;

/**
 * Ambientes públicos do Portal Único Siscomex.
 *
 * O ambiente de Homologação (hom.pucomex.serpro.gov.br) é restrito ao Serpro e
 * propositalmente não é oferecido.
 *
 * @see https://docs.portalunico.siscomex.gov.br/ambientes/
 */
enum Ambiente: string
{
    case Producao = 'producao';
    case Validacao = 'validacao';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Producao => 'https://portalunico.siscomex.gov.br',
            self::Validacao => 'https://val.portalunico.siscomex.gov.br',
        };
    }

    public function host(): string
    {
        return (string) parse_url($this->baseUrl(), PHP_URL_HOST);
    }

    public function isProducao(): bool
    {
        return $this === self::Producao;
    }

    /**
     * Aceita "producao", "prod", "validacao", "val", "treinamento" (sem diferenciar maiúsculas).
     */
    public static function fromString(string $valor): self
    {
        return match (strtolower(trim($valor))) {
            'producao', 'produção', 'prod' => self::Producao,
            'validacao', 'validação', 'val', 'treinamento' => self::Validacao,
            default => throw new \InvalidArgumentException(sprintf('Ambiente desconhecido: "%s".', $valor)),
        };
    }
}
