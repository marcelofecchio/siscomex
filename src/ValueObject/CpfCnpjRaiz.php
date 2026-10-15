<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\ValueObject;

/**
 * Identificação do responsável pelo catálogo: CNPJ raiz (8 posições) ou CPF (11 dígitos),
 * conforme o parâmetro `cpfCnpjRaiz` da API do Catálogo de Produtos.
 *
 * O CNPJ raiz aceita letras maiúsculas para suportar o CNPJ alfanumérico
 * (IN RFB nº 2.229/2024, vigente desde julho/2026). A spec ainda descreve o formato
 * como 'NNNNNNNN' — TODO(confirmar) quando houver empresas com raiz alfanumérica.
 */
final class CpfCnpjRaiz implements \Stringable
{
    public private(set) string $valor;

    public bool $isCpf {
        get => \strlen($this->valor) === 11;
    }

    public function __construct(string $valor)
    {
        $limpo = strtoupper(preg_replace('/[\s.\-\/]/', '', $valor) ?? '');

        if (preg_match('/^\d{11}$/', $limpo) === 1) {
            if (!self::cpfValido($limpo)) {
                throw new \InvalidArgumentException(\sprintf('CPF inválido: "%s".', $valor));
            }
        } elseif (preg_match('/^[0-9A-Z]{8}$/', $limpo) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'CPF/CNPJ raiz inválido: "%s". Informe os 8 primeiros caracteres do CNPJ ou o CPF com 11 dígitos.',
                $valor,
            ));
        }

        $this->valor = $limpo;
    }

    public static function of(self|string $valor): self
    {
        return $valor instanceof self ? $valor : new self($valor);
    }

    /** Extrai a raiz (8 primeiros caracteres) de um CNPJ completo de 14 posições. */
    public static function deCnpj(string $cnpj): self
    {
        $limpo = strtoupper(preg_replace('/[\s.\-\/]/', '', $cnpj) ?? '');
        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $limpo) !== 1) {
            throw new \InvalidArgumentException(\sprintf('CNPJ inválido: "%s".', $cnpj));
        }

        return new self(substr($limpo, 0, 8));
    }

    public function __toString(): string
    {
        return $this->valor;
    }

    private static function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($t = 9; $t < 11; ++$t) {
            $soma = 0;
            for ($i = 0; $i < $t; ++$i) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
