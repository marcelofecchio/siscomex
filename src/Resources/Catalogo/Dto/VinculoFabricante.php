<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Vínculo de fabricante/produtor a produto como devolvido pela API
 * (FabricanteIntegracaoResponseDTO no vínculo/desvínculo; FabricanteIntegracaoDTO na exportação).
 *
 * `conhecido`: true = fabricante/produtor conhecido (veja FabricanteRequest).
 */
final readonly class VinculoFabricante
{
    public function __construct(
        public ?string $cpfCnpjRaiz,
        public ?int $codigoProduto,
        public ?string $codigoPais,
        public bool $conhecido,
        public ?string $codigoOperadorEstrangeiro = null,
        public ?string $cpfCnpjFabricante = null,
        public ?int $seq = null,
        public ?bool $vincular = null,
        public ?\DateTimeImmutable $dataReferencia = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            cpfCnpjRaiz: Data::stringOrNull($a, 'cpfCnpjRaiz'),
            codigoProduto: Data::intOrNull($a, 'codigoProduto'),
            codigoPais: Data::stringOrNull($a, 'codigoPais'),
            conhecido: Data::bool($a, 'conhecido'),
            codigoOperadorEstrangeiro: Data::stringOrNull($a, 'codigoOperadorEstrangeiro'),
            cpfCnpjFabricante: Data::stringOrNull($a, 'cpfCnpjFabricante'),
            seq: Data::intOrNull($a, 'seq'),
            vincular: \array_key_exists('vincular', $a) ? Data::bool($a, 'vincular') : null,
            dataReferencia: Data::date($a, 'dataReferencia'),
        );
    }
}
