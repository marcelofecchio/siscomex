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
 * Fabricante::desconhecido('DE')->paraProduto(123); // equivalente, a partir de um Fabricante
 * ```
 *
 * `conhecido` tem o sentido do nome: true = fabricante/produtor conhecido. A descrição da spec
 * diz o contrário, mas a Validação (17/10/2026) confirmou o sentido do nome: `conhecido=true`
 * sem operador é recusado com "Campo Fabricante/produtor estrangeiro é obrigatório".
 * Fabricante desconhecido (`conhecido=false`, só o país) é aceito.
 */
final readonly class FabricanteRequest
{
    public const int MAX_CODIGO_OPERADOR = Fabricante::MAX_CODIGO_OPERADOR;

    public Fabricante $fabricante;

    public CodigoPais $codigoPais;

    public bool $conhecido;

    public ?string $codigoOperadorEstrangeiro;

    public ?string $cpfCnpjFabricante;

    public function __construct(
        public int $codigoProduto,
        CodigoPais|string $codigoPais,
        bool $conhecido,
        ?string $codigoOperadorEstrangeiro = null,
        ?string $cpfCnpjFabricante = null,
    ) {
        if ($codigoProduto < 1) {
            throw new \InvalidArgumentException('Informe o código do produto ao qual o fabricante será vinculado.');
        }
        $this->fabricante = new Fabricante($codigoPais, $conhecido, $codigoOperadorEstrangeiro, $cpfCnpjFabricante);
        $this->codigoPais = $this->fabricante->codigoPais;
        $this->conhecido = $this->fabricante->conhecido;
        $this->codigoOperadorEstrangeiro = $this->fabricante->codigoOperadorEstrangeiro;
        $this->cpfCnpjFabricante = $this->fabricante->cpfCnpjFabricante;
    }

    /** Fabricante/produtor estrangeiro já cadastrado como operador estrangeiro. */
    public static function operadorEstrangeiro(int $codigoProduto, CodigoPais|string $codigoPais, string $codigoOperador): self
    {
        return Fabricante::operadorEstrangeiro($codigoPais, $codigoOperador)->paraProduto($codigoProduto);
    }

    /** Fabricante/produtor nacional, identificado por CPF ou CNPJ completo. */
    public static function fabricanteNacional(int $codigoProduto, string $cpfCnpjFabricante, CodigoPais|string $codigoPais): self
    {
        return Fabricante::nacional($cpfCnpjFabricante, $codigoPais)->paraProduto($codigoProduto);
    }

    /** Fabricante/produtor desconhecido: informa-se apenas o país de origem. */
    public static function desconhecido(int $codigoProduto, CodigoPais|string $codigoPais): self
    {
        return Fabricante::desconhecido($codigoPais)->paraProduto($codigoProduto);
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
}
