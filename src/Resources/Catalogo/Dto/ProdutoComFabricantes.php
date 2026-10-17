<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

/**
 * Produto a incluir junto com os fabricantes a vincular depois da inclusão
 * (item de `produtos()->incluirLote()`).
 */
final readonly class ProdutoComFabricantes
{
    /** @param list<Fabricante> $fabricantes */
    public function __construct(
        public ProdutoRequest $produto,
        public array $fabricantes,
    ) {}
}
