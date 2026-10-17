<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

/**
 * Dados de um produto a incluir, versionar ou retificar (ProdutoIntegracaoRequestDTO).
 *
 * Imutável: os métodos com*() devolvem uma nova instância.
 *
 * ```php
 * $produto = new ProdutoRequest('Parafuso sextavado M8', Modalidade::Importacao, '73181500')
 *     ->comDescricao('Aço carbono, zincado')
 *     ->comAtributo('ATT_XXXX', '01')
 *     ->comCodigoInterno('SKU-123');
 * ```
 */
final class ProdutoRequest
{
    public const int MAX_DENOMINACAO = 120;
    public const int MAX_DESCRICAO = 3700;
    public const int MAX_CODIGO_INTERNO = 60;

    public private(set) Ncm $ncm;

    /**
     * @param list<AtributoValor>                 $atributos
     * @param list<AtributoMultivalorado>         $atributosMultivalorados
     * @param list<AtributoComposto>              $atributosCompostos
     * @param list<AtributoCompostoMultivalorado> $atributosCompostosMultivalorados
     * @param list<string>                        $codigosInterno
     */
    public function __construct(
        public private(set) string $denominacao,
        public private(set) Modalidade $modalidade,
        Ncm|string $ncm,
        public private(set) ?string $descricao = null,
        public private(set) array $atributos = [],
        public private(set) array $atributosMultivalorados = [],
        public private(set) array $atributosCompostos = [],
        public private(set) array $atributosCompostosMultivalorados = [],
        public private(set) array $codigosInterno = [],
    ) {
        $this->ncm = Ncm::of($ncm);
        $this->validar();
    }

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $produto = Produto::fromArray($a);
        if ($produto->modalidade === null || $produto->ncm === null) {
            throw new \InvalidArgumentException('Produto sem modalidade ou NCM.');
        }

        return $produto->paraRequest();
    }

    public function comDescricao(?string $descricao): self
    {
        $c = clone $this;
        $c->descricao = $descricao;
        $c->validar();

        return $c;
    }

    public function comAtributo(string $atributo, string $valor): self
    {
        $c = clone $this;
        $c->atributos[] = new AtributoValor($atributo, $valor);
        $c->validar();

        return $c;
    }

    /** @param list<string> $valores */
    public function comAtributoMultivalorado(string $atributo, array $valores): self
    {
        $c = clone $this;
        $c->atributosMultivalorados[] = new AtributoMultivalorado($atributo, $valores);

        return $c;
    }

    /** @param array<string, string> $subatributos código do subatributo => valor */
    public function comAtributoComposto(string $atributo, array $subatributos): self
    {
        $valores = [];
        foreach ($subatributos as $codigo => $valor) {
            $valores[] = new AtributoValor((string) $codigo, $valor);
        }
        $c = clone $this;
        $c->atributosCompostos[] = new AtributoComposto($atributo, $valores);
        $c->validar();

        return $c;
    }

    public function comCodigoInterno(string $codigoInterno): self
    {
        $c = clone $this;
        $c->codigosInterno[] = $codigoInterno;
        $c->validar();

        return $c;
    }

    /**
     * Códigos de todos os atributos informados (simples, multivalorados e compostos).
     *
     * @return list<string>
     */
    public function codigosAtributosInformados(): array
    {
        return array_values(array_unique([
            ...array_map(static fn(AtributoValor $a): string => $a->atributo, $this->atributos),
            ...array_map(static fn(AtributoMultivalorado $a): string => $a->atributo, $this->atributosMultivalorados),
            ...array_map(static fn(AtributoComposto $a): string => $a->atributo, $this->atributosCompostos),
            ...array_map(static fn(AtributoCompostoMultivalorado $a): string => $a->atributo, $this->atributosCompostosMultivalorados),
        ]));
    }

    /**
     * Valores informados para um atributo: o valor simples, os valores do multivalorado ou o
     * valor de um subatributo de composto (usado para avaliar atributos condicionados).
     *
     * @return list<string>
     */
    public function valoresDoAtributo(string $codigo): array
    {
        $valores = [];
        foreach ($this->atributos as $a) {
            if ($a->atributo === $codigo) {
                $valores[] = $a->valor;
            }
        }
        foreach ($this->atributosMultivalorados as $a) {
            if ($a->atributo === $codigo) {
                array_push($valores, ...$a->valores);
            }
        }
        $subatributos = [];
        foreach ($this->atributosCompostos as $a) {
            array_push($subatributos, ...$a->valores);
        }
        foreach ($this->atributosCompostosMultivalorados as $a) {
            foreach ($a->valores as $ocorrencia) {
                array_push($subatributos, ...$ocorrencia);
            }
        }
        foreach ($subatributos as $sub) {
            if ($sub->atributo === $codigo) {
                $valores[] = $sub->valor;
            }
        }

        return $valores;
    }

    /**
     * Corpo JSON enviado à API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dados = [
            'denominacao' => $this->denominacao,
            'descricao' => $this->descricao,
            'modalidade' => $this->modalidade->value,
            'ncm' => $this->ncm->codigo,
            'atributos' => array_map(static fn(AtributoValor $a): array => $a->toArray(), $this->atributos),
            'atributosMultivalorados' => array_map(static fn(AtributoMultivalorado $a): array => $a->toArray(), $this->atributosMultivalorados),
            'atributosCompostos' => array_map(static fn(AtributoComposto $a): array => $a->toArray(), $this->atributosCompostos),
            'atributosCompostosMultivalorados' => array_map(static fn(AtributoCompostoMultivalorado $a): array => $a->toArray(), $this->atributosCompostosMultivalorados),
            'codigosInterno' => $this->codigosInterno,
        ];

        return array_filter($dados, static fn(mixed $v): bool => $v !== null);
    }

    private function validar(): void
    {
        if ($this->modalidade === Modalidade::Ambos) {
            throw new \InvalidArgumentException('A modalidade do produto deve ser IMPORTACAO ou EXPORTACAO.');
        }
        $tamanho = mb_strlen(trim($this->denominacao));
        if ($tamanho < 1 || $tamanho > self::MAX_DENOMINACAO) {
            throw new \InvalidArgumentException(\sprintf('A denominação deve ter entre 1 e %d caracteres.', self::MAX_DENOMINACAO));
        }
        if ($this->descricao !== null && mb_strlen($this->descricao) > self::MAX_DESCRICAO) {
            throw new \InvalidArgumentException(\sprintf('A descrição deve ter no máximo %d caracteres.', self::MAX_DESCRICAO));
        }
        foreach ($this->codigosInterno as $codigo) {
            if ($codigo === '' || mb_strlen($codigo) > self::MAX_CODIGO_INTERNO) {
                throw new \InvalidArgumentException(\sprintf('Código interno deve ter entre 1 e %d caracteres.', self::MAX_CODIGO_INTERNO));
            }
        }
        $simples = $this->atributos;
        foreach ($this->atributosCompostos as $composto) {
            array_push($simples, ...$composto->valores);
        }
        foreach ($simples as $atributo) {
            $problema = $atributo->problemas();
            if ($problema !== null) {
                throw new \InvalidArgumentException($problema);
            }
        }
    }
}
