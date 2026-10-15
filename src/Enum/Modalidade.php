<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/**
 * Modalidade de operação. `Ambos` só é aceito na consulta de produtos.
 */
enum Modalidade: string
{
    case Importacao = 'IMPORTACAO';
    case Exportacao = 'EXPORTACAO';
    case Ambos = 'AMBOS';

    /**
     * Converte o valor textual usado no Cadastro de Atributos ("Importação"/"Exportação")
     * ou o código da API ("IMPORTACAO").
     */
    public static function fromDescricao(string $valor): ?self
    {
        $normalizado = strtoupper(strtr(trim($valor), ['ç' => 'c', 'Ç' => 'C', 'ã' => 'a', 'Ã' => 'A']));

        return match ($normalizado) {
            'IMPORTACAO' => self::Importacao,
            'EXPORTACAO' => self::Exportacao,
            'AMBOS' => self::Ambos,
            default => null,
        };
    }

    public function abrange(self $outra): bool
    {
        return $this === $outra || $this === self::Ambos || $outra === self::Ambos;
    }
}
