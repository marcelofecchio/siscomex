<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/** Situação de um item em `produtos()->incluirLote()`. */
enum StatusItemLote: string
{
    /** Produto incluído (e fabricantes vinculados, se houver); o retorno está em ItemLote::$produto. */
    case Incluido = 'INCLUIDO';

    /**
     * Produto incluído, mas algum fabricante não foi vinculado (veja ItemLote::$vinculos). Não
     * reenvie o produto: reprocesse os vínculos com ResultadoLote::vinculosParaReprocessar().
     */
    case IncluidoSemVinculo = 'INCLUIDO_SEM_VINCULO';

    /** O portal recusou o item (ex.: 422); os demais seguem. Detalhes em ItemLote::$erro. */
    case Erro = 'ERRO';

    /**
     * A conexão caiu durante o envio: o portal pode ter incluído ou não. Confira (ex.: por
     * buscarPorCodigoInterno()) antes de reenviar, para não duplicar o produto.
     */
    case Incerto = 'INCERTO';

    /** Não enviado porque o lote foi interrompido; reenvie com ResultadoLote::pendentes(). */
    case Pendente = 'PENDENTE';
}
