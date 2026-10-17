<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/** Estrutura de uma tabela (TabelaCamposApiRepresentation). */
final readonly class MetadadosTabela
{
    /** @param list<CampoTabela> $campos */
    public function __construct(
        public string $nome,
        public ?string $rotulo,
        public ?string $descricao,
        public array $campos,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            Data::string($a, 'nome'),
            Data::stringOrNull($a, 'rotulo'),
            Data::stringOrNull($a, 'descricao'),
            array_map(CampoTabela::fromArray(...), Data::listOfArrays($a, 'campos')),
        );
    }

    public function campo(string $nome): ?CampoTabela
    {
        foreach ($this->campos as $campo) {
            if ($campo->nome === $nome) {
                return $campo;
            }
        }

        return null;
    }

    /** @return list<string> campos que formam a chave de negócio (ex.: SIGLA_ISO2 em PAIS) */
    public function chaveNegocio(): array
    {
        return array_values(array_map(static fn(CampoTabela $c): string => $c->nome, array_filter($this->campos, static fn(CampoTabela $c): bool => $c->chaveNegocio)));
    }
}
