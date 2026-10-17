<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CodigoPais;

/**
 * Fabricante/produtor de um produto, ainda sem o código do produto — usado em
 * `produtos()->incluirComFabricantes()` e `ProdutoComFabricantes`, quando o produto ainda não existe.
 * `paraProduto()` gera o FabricanteRequest do vínculo.
 *
 * `conhecido`: true = fabricante/produtor conhecido (identificado por operador estrangeiro ou
 * CPF/CNPJ nacional); false = desconhecido (só o país de origem). Confirmado na Validação em
 * 17/10/2026 (a descrição da spec está invertida).
 */
final readonly class Fabricante
{
    public const int MAX_CODIGO_OPERADOR = 35;

    public CodigoPais $codigoPais;

    public ?string $cpfCnpjFabricante;

    public function __construct(
        CodigoPais|string $codigoPais,
        public bool $conhecido,
        public ?string $codigoOperadorEstrangeiro = null,
        ?string $cpfCnpjFabricante = null,
    ) {
        $this->codigoPais = CodigoPais::of($codigoPais);
        $this->cpfCnpjFabricante = $cpfCnpjFabricante === null ? null : self::normalizarCpfCnpj($cpfCnpjFabricante);
        $this->validar();
    }

    /** Fabricante/produtor estrangeiro já cadastrado como operador estrangeiro. */
    public static function operadorEstrangeiro(CodigoPais|string $codigoPais, string $codigoOperador): self
    {
        return new self($codigoPais, true, codigoOperadorEstrangeiro: $codigoOperador);
    }

    /** Fabricante/produtor nacional, identificado por CPF ou CNPJ completo. */
    public static function nacional(string $cpfCnpjFabricante, CodigoPais|string $codigoPais): self
    {
        return new self($codigoPais, true, cpfCnpjFabricante: $cpfCnpjFabricante);
    }

    /** Fabricante/produtor desconhecido: informa-se apenas o país de origem. */
    public static function desconhecido(CodigoPais|string $codigoPais): self
    {
        return new self($codigoPais, false);
    }

    public function paraProduto(int $codigoProduto): FabricanteRequest
    {
        return new FabricanteRequest($codigoProduto, $this->codigoPais, $this->conhecido, $this->codigoOperadorEstrangeiro, $this->cpfCnpjFabricante);
    }

    private function validar(): void
    {
        if ($this->codigoOperadorEstrangeiro !== null && (trim($this->codigoOperadorEstrangeiro) === '' || \strlen($this->codigoOperadorEstrangeiro) > self::MAX_CODIGO_OPERADOR)) {
            throw new \InvalidArgumentException(\sprintf('Código do operador estrangeiro deve ter entre 1 e %d caracteres.', self::MAX_CODIGO_OPERADOR));
        }
        $identificacoes = ($this->codigoOperadorEstrangeiro === null ? 0 : 1) + ($this->cpfCnpjFabricante === null ? 0 : 1);
        if ($this->conhecido && $identificacoes !== 1) {
            throw new \InvalidArgumentException('Fabricante conhecido: informe o código do operador estrangeiro ou o CPF/CNPJ do fabricante nacional (apenas um).');
        }
        if (!$this->conhecido && $identificacoes !== 0) {
            throw new \InvalidArgumentException('Fabricante desconhecido não deve ter operador estrangeiro nem CPF/CNPJ informado.');
        }
    }

    /** CPF (11 dígitos) ou CNPJ completo (14 posições; as 12 primeiras podem ser alfanuméricas). */
    private static function normalizarCpfCnpj(string $valor): string
    {
        $limpo = strtoupper(preg_replace('/[\s.\/\-]/', '', $valor) ?? '');
        if (preg_match('/^(\d{11}|[A-Z0-9]{12}\d{2})$/', $limpo) !== 1) {
            throw new \InvalidArgumentException(\sprintf('CPF/CNPJ do fabricante inválido: "%s". Informe o CPF (11 dígitos) ou o CNPJ completo (14 posições).', $valor));
        }

        return $limpo;
    }
}
