<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Identificação do operador estrangeiro em uma agência internacional (IdentificacaoAdicionalIntegracaoDTO).
 *
 * `codigo` é o código da agência emissora conforme a lista UN/EDIFACT 3055
 * (https://service.unece.org/trade/untdid/d20b/tred/tred3055.htm); `numero` é a identificação nela.
 */
final readonly class IdentificacaoAdicional
{
    public const int MAX_NUMERO = 35;
    public const int MAX_CODIGO = 3;

    public function __construct(
        public string $codigo,
        public string $numero,
    ) {}

    /** Problemas de tamanho conforme a spec (código da agência 1–3, número 1–35 caracteres). */
    public function problema(): ?string
    {
        return match (true) {
            trim($this->codigo) === '' || \strlen($this->codigo) > self::MAX_CODIGO => \sprintf('Código da agência emissora deve ter entre 1 e %d caracteres.', self::MAX_CODIGO),
            trim($this->numero) === '' || mb_strlen($this->numero) > self::MAX_NUMERO => \sprintf('Número da identificação adicional deve ter entre 1 e %d caracteres.', self::MAX_NUMERO),
            default => null,
        };
    }

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::string($a, 'codigo'), Data::string($a, 'numero'));
    }

    /** @return array{numero: string, codigo: string} */
    public function toArray(): array
    {
        return ['numero' => $this->numero, 'codigo' => $this->codigo];
    }
}
