<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/** Campo de uma tabela (CampoApiRepresentation). `tipo`: STRING, INTEIRO, DATA_HORA... */
final readonly class CampoTabela
{
    /** @param array<string, string> $dominios código => descrição */
    public function __construct(
        public string $nome,
        public ?string $rotulo = null,
        public ?string $descricao = null,
        public ?string $tipo = null,
        public ?int $tamanho = null,
        public ?int $casasDecimais = null,
        public ?string $formato = null,
        public bool $obrigatorio = false,
        public bool $chaveNegocio = false,
        public bool $campoEstrangeiro = false,
        public ?string $nomeTabelaEstrangeira = null,
        public array $dominios = [],
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $dominios = [];
        foreach (Data::listOfArrays($a, 'dominios') as $d) {
            $dominios[Data::string($d, 'codigo')] = Data::string($d, 'descricao');
        }

        return new self(
            nome: Data::string($a, 'nome'),
            rotulo: Data::stringOrNull($a, 'rotulo'),
            descricao: Data::stringOrNull($a, 'descricao'),
            tipo: Data::stringOrNull($a, 'tipo'),
            tamanho: Data::intOrNull($a, 'tamanho'),
            casasDecimais: Data::intOrNull($a, 'casasDecimais'),
            formato: Data::stringOrNull($a, 'formato'),
            obrigatorio: Data::bool($a, 'obrigatorio'),
            chaveNegocio: Data::bool($a, 'chaveNegocio'),
            campoEstrangeiro: Data::bool($a, 'campoEstrangeiro'),
            nomeTabelaEstrangeira: Data::stringOrNull($a, 'nomeTabelaEstrangeira'),
            dominios: $dominios,
        );
    }
}
