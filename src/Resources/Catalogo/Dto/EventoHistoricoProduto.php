<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/** Evento do histórico de alterações de um produto (ProdutoHistoricoResponseDTO). */
final readonly class EventoHistoricoProduto
{
    public function __construct(
        public ?\DateTimeImmutable $data,
        public ?string $versao,
        public ?SituacaoProduto $situacao,
        public ?string $usuario,
        public ?string $observacao,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $situacao = Data::stringOrNull($a, 'situacao');

        return new self(
            Data::date($a, 'data'),
            Data::stringOrNull($a, 'versao'),
            $situacao === null ? null : SituacaoProduto::tryFrom(strtoupper($situacao)),
            Data::stringOrNull($a, 'usuario'),
            Data::stringOrNull($a, 'observacao'),
        );
    }
}
