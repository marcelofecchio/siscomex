<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/**
 * Forma de preenchimento de um atributo (ArquivoDiarioAtributoDTO.formaPreenchimento).
 */
enum FormaPreenchimento: string
{
    case ListaEstatica = 'LISTA_ESTATICA';
    case Booleano = 'BOOLEANO';
    case Data = 'DATA';
    case DataHora = 'DATA_HORA';
    case NumeroInteiro = 'NUMERO_INTEIRO';
    case NumeroReal = 'NUMERO_REAL';
    case Texto = 'TEXTO';
    case DominioDinamico = 'DOMINIO_DINAMICO';
    case Composto = 'COMPOSTO';
    case ListaTabxFiltro = 'LISTA_TABX_FILTRO';

    public function possuiDominio(): bool
    {
        return $this === self::ListaEstatica;
    }
}
