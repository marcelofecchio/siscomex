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

    /** Nome para exibição ("Produção", "Validação"). */
    public function nome(): string
    {
        return match ($this) {
            self::Producao => 'Produção',
            self::Validacao => 'Validação',
        };
    }

    /**
     * Motivo pelo qual o portal provavelmente está indisponível no momento informado, ou null.
     * Apenas informativo (para a UI avisar): não bloqueia chamadas — a Validação já respondeu
     * normalmente dentro dessas janelas.
     *
     * - Ambos: parada programada diária das 01h às 03h (PUCX-ER0401, plat-auth.json).
     * - Validação: manutenções de segunda a sexta das 18h às 7h e nos fins de semana
     *   (https://docs.portalunico.siscomex.gov.br/ambientes/).
     *
     * Horários de Brasília (America/Sao_Paulo).
     */
    public function janelaDeIndisponibilidade(\DateTimeInterface $em): ?string
    {
        $local = \DateTimeImmutable::createFromInterface($em)->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        $hora = (int) $local->format('G');

        if ($hora >= 1 && $hora < 3) {
            return 'Parada programada diária do Portal Único (01h às 03h).';
        }
        if ($this === self::Validacao && ((int) $local->format('N') >= 6 || $hora >= 18 || $hora < 7)) {
            return 'Janela de manutenção do ambiente de Validação (dias úteis das 18h às 7h e fins de semana).';
        }

        return null;
    }

    public function provavelmenteIndisponivel(\DateTimeInterface $em): bool
    {
        return $this->janelaDeIndisponibilidade($em) !== null;
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
