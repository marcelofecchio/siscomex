<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/**
 * Situação de um código interno no catálogo do CPF/CNPJ raiz (derivada da consulta de produtos).
 *
 * Regras observadas na Validação (17/10/2026) — o portal **não** impõe unicidade:
 * - Desativado: o código pode ser reutilizado em um produto novo ou incluído em outro produto;
 *   também é possível reativar o produto antigo enviando uma nova versão (`novaVersao()`).
 * - Ativo: o portal aceita o mesmo código em outro produto ativo; evitar a duplicidade fica
 *   a cargo de quem integra.
 */
enum SituacaoCodigoInterno: string
{
    /** Nenhum produto usa o código. */
    case Livre = 'LIVRE';

    /** Pelo menos um produto ativo usa o código. */
    case Ativo = 'ATIVO';

    /** Só produtos desativados (ou em rascunho) usam o código. */
    case Desativado = 'DESATIVADO';
}
