<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/** Situação de um vínculo de fabricante em incluirComFabricantes(), incluirLote() ou vincularTodos(). */
enum StatusVinculo: string
{
    case Vinculado = 'VINCULADO';

    /** O portal recusou o vínculo (ex.: 422); os demais seguem. */
    case Erro = 'ERRO';

    /** A conexão caiu durante o envio: confira com fabricantes()->historico() antes de reenviar. */
    case Incerto = 'INCERTO';

    /** Não enviado (sequência interrompida ou produto sem código); reenvie com vincularTodos(). */
    case Pendente = 'PENDENTE';
}
