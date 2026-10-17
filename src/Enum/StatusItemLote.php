<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/** Situação de um item em `produtos()->incluirLote()`. */
enum StatusItemLote: string
{
    /** Produto incluído; o retorno está em ItemLote::$produto. */
    case Incluido = 'INCLUIDO';

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
