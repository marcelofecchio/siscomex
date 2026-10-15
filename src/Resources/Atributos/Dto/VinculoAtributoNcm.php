<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Vínculo de um atributo a uma NCM (item de listaNcm[].listaAtributos[] do arquivo de atributos por NCM).
 */
final readonly class VinculoAtributoNcm
{
    public function __construct(
        public string $codigo,
        public ?Modalidade $modalidade,
        public bool $obrigatorio,
        public bool $multivalorado,
        public ?\DateTimeImmutable $dataInicioVigencia,
        public ?\DateTimeImmutable $dataFimVigencia = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            Data::string($a, 'codigo'),
            Modalidade::fromDescricao(Data::string($a, 'modalidade')),
            Data::bool($a, 'obrigatorio'),
            Data::bool($a, 'multivalorado'),
            Data::date($a, 'dataInicioVigencia'),
            Data::date($a, 'dataFimVigencia'),
        );
    }

    public function vigenteEm(\DateTimeInterface $data): bool
    {
        $dia = $data->format('Y-m-d');

        return ($this->dataInicioVigencia === null || $this->dataInicioVigencia->format('Y-m-d') <= $dia)
            && ($this->dataFimVigencia === null || $this->dataFimVigencia->format('Y-m-d') >= $dia);
    }
}
