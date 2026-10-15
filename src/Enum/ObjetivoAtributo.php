<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/**
 * Objetivos aceitos no filtro `objetivos` das consultas de atributos por NCM.
 *
 * Para o Catálogo de Produtos devem ser considerados apenas atributos com objetivo Produto.
 */
enum ObjetivoAtributo: string
{
    case Produto = 'PRODUTO';
    case TratamentoAdministrativo = 'TRATAMENTO_ADMINISTRATIVO';
    case Duimp = 'DUIMP';

    /** Descrição usada nos arquivos de atributos (campo objetivos[].descricao). */
    public function descricao(): string
    {
        return match ($this) {
            self::Produto => 'Produto',
            self::TratamentoAdministrativo => 'Tratamento administrativo',
            self::Duimp => 'Duimp',
        };
    }
}
