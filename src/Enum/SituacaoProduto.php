<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Enum;

enum SituacaoProduto: string
{
    case Ativado = 'ATIVADO';
    case Desativado = 'DESATIVADO';
    case Rascunho = 'RASCUNHO';

    /** Código numérico usado no filtro `situacao` de GET /ext/produto. */
    public function codigoConsulta(): int
    {
        return match ($this) {
            self::Ativado => 0,
            self::Desativado => 1,
            self::Rascunho => 2,
        };
    }
}
