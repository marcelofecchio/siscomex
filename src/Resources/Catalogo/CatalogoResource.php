<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo;

use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;

/**
 * Agrupa os serviços do Catálogo de Produtos: produtos, operadores estrangeiros e
 * vínculos de fabricantes/produtores.
 */
final readonly class CatalogoResource
{
    private ProdutosResource $produtos;

    private OperadoresEstrangeirosResource $operadoresEstrangeiros;

    private FabricantesResource $fabricantes;

    public function __construct(Transport $transport)
    {
        $this->fabricantes = new FabricantesResource($transport);
        $this->produtos = new ProdutosResource($transport, $this->fabricantes);
        $this->operadoresEstrangeiros = new OperadoresEstrangeirosResource($transport);
    }

    public function produtos(): ProdutosResource
    {
        return $this->produtos;
    }

    public function operadoresEstrangeiros(): OperadoresEstrangeirosResource
    {
        return $this->operadoresEstrangeiros;
    }

    public function fabricantes(): FabricantesResource
    {
        return $this->fabricantes;
    }
}
