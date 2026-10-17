<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/** Resultado de `produtos()->incluirComFabricantes()`. */
enum SituacaoInclusaoComFabricantes: string
{
    /** Produto incluído e todos os fabricantes vinculados. */
    case Completo = 'COMPLETO';

    /**
     * Produto incluído, mas algum vínculo falhou ou ficou pendente. Não inclua o produto de novo:
     * reprocesse só os vínculos (`fabricantes()->vincularTodos($cnpj, $resultado->paraReprocessar())`).
     */
    case ProdutoSemVinculo = 'PRODUTO_SEM_VINCULO';
}
