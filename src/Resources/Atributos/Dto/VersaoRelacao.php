<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Entrada do histórico de versões das relações de atributos
 * (ChangeLogArquivoDiarioAtributoDTO / ChangeLogArquivoDiarioAtributoNcmDTO).
 *
 * Para relações por NCM, os códigos são NCMs; para a relação de atributos, códigos de atributos.
 */
final readonly class VersaoRelacao
{
    /**
     * @param list<string> $adicionados
     * @param list<string> $excluidos
     * @param list<string> $alterados   códigos dos itens alterados (o diff detalhado fica em $alteracoes)
     * @param list<array<mixed>> $alteracoes diff bruto de cada item alterado (DiffArquivoDiario*DTO)
     */
    public function __construct(
        public int $numero,
        public ?\DateTimeImmutable $dataVersao,
        public ?string $versao,
        public ?string $resumo,
        public array $adicionados,
        public array $excluidos,
        public array $alterados,
        public array $alteracoes,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $alt = Data::arrayOrNull($a, 'alteracoes') ?? [];
        $alterados = Data::listOfArrays($alt, 'alterado');

        return new self(
            numero: Data::int($a, 'numero'),
            dataVersao: Data::date($a, 'dataVersao'),
            versao: Data::stringOrNull($alt, 'versao'),
            resumo: Data::stringOrNull($alt, 'resumo'),
            adicionados: Data::listOfStrings($alt, 'adicionado'),
            excluidos: Data::listOfStrings($alt, 'excluido'),
            alterados: array_values(array_filter(array_map(
                static fn(array $d): string => Data::string($d, 'codigoNcm', Data::string($d, 'codigo')),
                $alterados,
            ), static fn(string $c): bool => $c !== '')),
            alteracoes: $alterados,
        );
    }
}
