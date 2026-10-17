<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

/**
 * Filtros, campos de retorno, ordenação e nível de GET /tabx/api/ext/tabela/{nomeTabela}.
 *
 * A spec diz apenas "Modelo: FiltroCampoApiRepresentation"; na Validação (17/10/2026) o portal
 * aceitou cada parâmetro como **lista JSON** (`[{"nome":"SIGLA_ISO2","valores":["CN","DE"]}]`)
 * e recusou um objeto único (422 TABX-IN0067). O filtro é por igualdade; sem resultado, 404.
 *
 * ```php
 * $filtro = new FiltroTabela()->onde('SIGLA_ISO2', 'CN', 'DE')->retornando('SIGLA_ISO2', 'NOME')->ordenadoPor('NOME');
 * ```
 */
final class FiltroTabela
{
    /** @var list<array{nome: string, valores: list<string>, nomeTabela?: string}> */
    private array $filtros = [];

    /** @var list<array{nome: string, nomeTabela?: string}> */
    private array $retorno = [];

    /** @var list<array{nome: string, tipoOrdenacao: string, nomeTabela?: string}> */
    private array $ordenacao = [];

    private bool $comReferenciadas = false;

    /** Registros cujo campo é igual a um dos valores. */
    public function onde(string $campo, string ...$valores): self
    {
        if ($valores === []) {
            throw new \InvalidArgumentException(\sprintf('Informe ao menos um valor para filtrar %s.', $campo));
        }
        $c = clone $this;
        $c->filtros[] = ['nome' => $campo, 'valores' => array_values($valores)];

        return $c;
    }

    /** Devolve só estes campos. */
    public function retornando(string ...$campos): self
    {
        $c = clone $this;
        foreach ($campos as $campo) {
            $c->retorno[] = ['nome' => $campo];
        }

        return $c;
    }

    public function ordenadoPor(string $campo, bool $decrescente = false): self
    {
        $c = clone $this;
        $c->ordenacao[] = ['nome' => $campo, 'tipoOrdenacao' => $decrescente ? 'DESC' : 'ASC'];

        return $c;
    }

    /** Nível 1: inclui os dados das tabelas referenciadas por campos estrangeiros (um nível). */
    public function comTabelasReferenciadas(bool $sim = true): self
    {
        $c = clone $this;
        $c->comReferenciadas = $sim;

        return $c;
    }

    /** @return array<string, string|null> */
    public function toQuery(?int $offset = null): array
    {
        $json = static fn(array $lista): ?string => $lista === [] ? null : json_encode($lista, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return [
            'filtros' => $json($this->filtros),
            'camposRetorno' => $json($this->retorno),
            'camposOrdenacao' => $json($this->ordenacao),
            'nivel' => $this->comReferenciadas ? '1' : null,
            'offset' => $offset === null ? null : (string) $offset,
        ];
    }
}
