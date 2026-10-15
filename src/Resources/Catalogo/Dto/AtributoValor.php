<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;

/** Atributo simples preenchido (ProdutoCampoSimplesIntegracaoDTO). */
final readonly class AtributoValor
{
    public function __construct(
        public string $atributo,
        public string $valor,
    ) {}

    /** Problemas de tamanho conforme a spec (atributo 1–25, valor 1–100 caracteres). */
    public function problemas(): ?string
    {
        return match (true) {
            $this->atributo === '' || \strlen($this->atributo) > 25 => 'Código de atributo deve ter entre 1 e 25 caracteres.',
            $this->valor === '' || mb_strlen($this->valor) > 100 => \sprintf('Valor do atributo %s deve ter entre 1 e 100 caracteres.', $this->atributo),
            default => null,
        };
    }

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::string($a, 'atributo'), Data::string($a, 'valor'));
    }

    /** @return array{atributo: string, valor: string} */
    public function toArray(): array
    {
        return ['atributo' => $this->atributo, 'valor' => $this->valor];
    }
}
