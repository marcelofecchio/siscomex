<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\ValueObject;

/**
 * Código de país ISO 3166-1 alfa-2 (ex.: "CN", "DE"), como exigido pelo Catálogo.
 *
 * Aceita minúsculas e espaços nas pontas; guarda em maiúsculas.
 */
final readonly class CodigoPais implements \Stringable
{
    public string $codigo;

    public function __construct(string $codigo)
    {
        $limpo = strtoupper(trim($codigo));
        if (preg_match('/^[A-Z]{2}$/', $limpo) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Código de país inválido: "%s". Use o código ISO 3166 de 2 letras (ex.: CN).', $codigo));
        }
        $this->codigo = $limpo;
    }

    public static function of(self|string $codigo): self
    {
        return $codigo instanceof self ? $codigo : new self($codigo);
    }

    public function equals(self|string $outro): bool
    {
        return self::of($outro)->codigo === $this->codigo;
    }

    public function __toString(): string
    {
        return $this->codigo;
    }
}
