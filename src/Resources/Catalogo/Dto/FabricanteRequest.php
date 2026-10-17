<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CodigoPais;

/**
 * Vínculo (ou desvínculo) de fabricante/produtor a um produto (FabricanteIntegracaoRequestDTO).
 *
 * O fabricante não faz parte do ProdutoRequest: o vínculo é uma operação própria
 * (POST/PUT /ext/fabricante/{cpfCnpjRaiz}) feita depois de o produto existir. Prefira os
 * construtores nomeados, que garantem a combinação coerente dos campos:
 *
 * ```php
 * FabricanteRequest::operadorEstrangeiro(codigoProduto: 123, codigoPais: 'CN', codigoOperador: 'OPE_1');
 * FabricanteRequest::fabricanteNacional(codigoProduto: 123, cpfCnpjFabricante: '12.345.678/0001-95', codigoPais: 'BR');
 * FabricanteRequest::desconhecido(codigoProduto: 123, codigoPais: 'DE');
 * ```
 *
 * `conhecido` tem o sentido do nome: true = fabricante/produtor conhecido. A descrição da spec
 * diz o contrário, mas a Validação (17/10/2026) confirmou o sentido do nome: `conhecido=true`
 * sem operador é recusado com "Campo Fabricante/produtor estrangeiro é obrigatório".
 * Fabricante desconhecido (`conhecido=false`, só o país) é aceito.
 */
final readonly class FabricanteRequest
{
    public const int MAX_CODIGO_OPERADOR = 35;

    public CodigoPais $codigoPais;

    public ?string $cpfCnpjFabricante;

    public function __construct(
        public int $codigoProduto,
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
    public static function operadorEstrangeiro(int $codigoProduto, CodigoPais|string $codigoPais, string $codigoOperador): self
    {
        return new self($codigoProduto, $codigoPais, true, codigoOperadorEstrangeiro: $codigoOperador);
    }

    /** Fabricante/produtor nacional, identificado por CPF ou CNPJ completo. */
    public static function fabricanteNacional(int $codigoProduto, string $cpfCnpjFabricante, CodigoPais|string $codigoPais): self
    {
        return new self($codigoProduto, $codigoPais, true, cpfCnpjFabricante: $cpfCnpjFabricante);
    }

    /** Fabricante/produtor desconhecido: informa-se apenas o país de origem. */
    public static function desconhecido(int $codigoProduto, CodigoPais|string $codigoPais): self
    {
        return new self($codigoProduto, $codigoPais, false);
    }

    /**
     * Corpo JSON enviado à API.
     *
     * @return array<string, scalar>
     */
    public function toArray(): array
    {
        $dados = [
            'codigoProduto' => $this->codigoProduto,
            'codigoPais' => $this->codigoPais->codigo,
            'conhecido' => $this->conhecido,
            'codigoOperadorEstrangeiro' => $this->codigoOperadorEstrangeiro,
            'cpfCnpjFabricante' => $this->cpfCnpjFabricante,
        ];

        return array_filter($dados, static fn(mixed $v): bool => $v !== null);
    }

    private function validar(): void
    {
        if ($this->codigoProduto < 1) {
            throw new \InvalidArgumentException('Informe o código do produto ao qual o fabricante será vinculado.');
        }
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
