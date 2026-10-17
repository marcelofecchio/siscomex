<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Um registro (linha) de tabela (DadoTabelaApiRepresentation): campo => valor como texto.
 * Valores vazios ("") viram null. Com nível 1, os dados das tabelas referenciadas por campos
 * estrangeiros ficam em `$referenciados[campo]`.
 */
final readonly class RegistroTabela
{
    /**
     * @param array<string, string|null>       $valores
     * @param array<string, list<RegistroTabela>> $referenciados
     */
    public function __construct(
        public array $valores,
        public array $referenciados = [],
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $valores = [];
        $referenciados = [];
        foreach (Data::listOfArrays($a, 'campos') as $campo) {
            $nome = Data::string($campo, 'nome');
            $valores[$nome] = Data::stringOrNull($campo, 'valor');
            $estrangeira = Data::arrayOrNull($campo, 'dadosTabelaEstrangeira');
            if ($estrangeira !== null) {
                $referenciados[$nome] = array_map(self::fromArray(...), Data::listOfArrays($estrangeira, 'dados'));
            }
        }

        return new self($valores, $referenciados);
    }

    public function valor(string $campo): ?string
    {
        return $this->valores[$campo] ?? null;
    }

    public function data(string $campo): ?\DateTimeImmutable
    {
        return Data::parseDate($this->valor($campo));
    }

    /** Vigente na data, pelos campos DATA_INICIO/DATA_FIM (padrão das tabelas Comex). */
    public function vigenteEm(\DateTimeInterface $em): bool
    {
        $inicio = $this->data('DATA_INICIO');
        $fim = $this->data('DATA_FIM');

        return ($inicio === null || $inicio <= $em) && ($fim === null || $fim >= $em);
    }
}
