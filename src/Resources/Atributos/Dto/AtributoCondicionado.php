<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Atributo que passa a valer quando a condição sobre o atributo condicionante é satisfeita
 * (ArquivoDiarioCondicionadoDTO).
 *
 * O próprio atributo condicionado pode ser condicionante de outros (`$atributo->condicionados`).
 */
final readonly class AtributoCondicionado
{
    public function __construct(
        public Atributo $atributo,
        public bool $obrigatorio,
        public bool $multivalorado,
        public ?\DateTimeImmutable $dataInicioVigencia,
        public ?\DateTimeImmutable $dataFimVigencia,
        public ?string $descricaoCondicao,
        public ?CondicaoAtributo $condicao,
        public ?string $codigoCondicionante = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a, ?string $codigoCondicionante = null): self
    {
        $condicao = Data::arrayOrNull($a, 'condicao');
        $descricao = Data::stringOrNull($a, 'descricaoCondicao');

        return new self(
            atributo: Atributo::fromArray(Data::arrayOrNull($a, 'atributo') ?? []),
            obrigatorio: Data::bool($a, 'obrigatorio'),
            multivalorado: Data::bool($a, 'multivalorado'),
            dataInicioVigencia: Data::date($a, 'dataInicioVigencia'),
            dataFimVigencia: Data::date($a, 'dataFimVigencia'),
            descricaoCondicao: $descricao === null ? null : trim($descricao),
            condicao: $condicao === null ? null : CondicaoAtributo::fromArray($condicao),
            codigoCondicionante: $codigoCondicionante,
        );
    }

    /**
     * A condição é satisfeita pelos valores do condicionante? (null = não foi possível avaliar)
     *
     * @param list<string> $valoresCondicionante
     */
    public function satisfeitaPor(array $valoresCondicionante): ?bool
    {
        return $this->condicao?->avaliar($valoresCondicionante);
    }

    public function vigenteEm(\DateTimeInterface $data): bool
    {
        $dia = $data->format('Y-m-d');

        return ($this->dataInicioVigencia === null || $this->dataInicioVigencia->format('Y-m-d') <= $dia)
            && ($this->dataFimVigencia === null || $this->dataFimVigencia->format('Y-m-d') >= $dia);
    }

    /**
     * O atributo condicionado com obrigatoriedade, multivalorado e vigência deste vínculo
     * (no lugar dos valores genéricos do cadastro do atributo).
     */
    public function comoAtributo(?Modalidade $modalidade): Atributo
    {
        return $this->atributo->comVinculo(new VinculoAtributoNcm(
            $this->atributo->codigo,
            $modalidade,
            $this->obrigatorio,
            $this->multivalorado,
            $this->dataInicioVigencia,
            $this->dataFimVigencia,
        ));
    }
}
