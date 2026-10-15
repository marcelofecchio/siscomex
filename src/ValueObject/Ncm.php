<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\ValueObject;

/**
 * Código NCM (Nomenclatura Comum do Mercosul) com 8 dígitos.
 *
 * Aceita a forma pontuada usada nos arquivos do portal ("0101.21.00").
 */
final class Ncm implements \Stringable
{
    public private(set) string $codigo;

    /** Forma pontuada "NNNN.NN.NN". */
    public string $formatado {
        get => \sprintf('%s.%s.%s', substr($this->codigo, 0, 4), substr($this->codigo, 4, 2), substr($this->codigo, 6, 2));
    }

    /** Capítulo (2 primeiros dígitos). */
    public string $capitulo {
        get => substr($this->codigo, 0, 2);
    }

    public function __construct(string $codigo)
    {
        $limpo = preg_replace('/[\s.\-]/', '', $codigo) ?? '';
        if (preg_match('/^\d{8}$/', $limpo) !== 1) {
            throw new \InvalidArgumentException(\sprintf('NCM inválida: "%s". Informe 8 dígitos.', $codigo));
        }
        $this->codigo = $limpo;
    }

    public static function of(self|string $ncm): self
    {
        return $ncm instanceof self ? $ncm : new self($ncm);
    }

    public function equals(self|string $outra): bool
    {
        return self::of($outra)->codigo === $this->codigo;
    }

    public function __toString(): string
    {
        return $this->codigo;
    }
}
