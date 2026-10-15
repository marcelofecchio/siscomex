<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Catalogo;

use MFecchio\Siscomex\PortalUnico\Http\Transport;

/**
 * Agrupa os serviços do Catálogo de Produtos.
 *
 * Fabricantes-Produtores e Operadores Estrangeiros estão planejados para a v1.1.
 */
final readonly class CatalogoResource
{
    private ProdutosResource $produtos;

    public function __construct(Transport $transport)
    {
        $this->produtos = new ProdutosResource($transport);
    }

    public function produtos(): ProdutosResource
    {
        return $this->produtos;
    }
}
