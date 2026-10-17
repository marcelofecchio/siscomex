<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Mensagens amigáveis (em português, para o usuário final) dos códigos de erro conhecidos.
 *
 * Só entram códigos documentados nas specs (docs/api/specs/) ou observados no portal e
 * registrados em docs/PROGRESSO.md. Para os demais, use a mensagem devolvida pelo portal.
 */
final class CodigosErro
{
    /** @var array<string, string> */
    public const array DESCRICOES = [
        // Genéricos do Portal Único. 0101/0102: na autenticação referem-se ao header Role-Type;
        // no Catálogo foram observados para campos do corpo.
        'PUCX-ER0101' => 'Campo obrigatório não informado.',
        'PUCX-ER0102' => 'Campo com valor inválido.',
        'PUCX-ER0123' => 'O portal encontrou problemas nos dados enviados; veja os detalhes de cada campo.',
        'PUCX-ER0401' => 'O Portal Único está na parada programada diária (01h às 03h). Tente novamente depois.',
        'PUCX-ER1001' => 'Limite de acessos por hora do Portal Único atingido. Aguarde o horário de liberação.',

        // Autenticação (plat-auth.json, exceto PLAT-ER8007, observado).
        'PLAT-ER2001' => 'Não foi possível identificar um certificado digital válido (falha no handshake mTLS).',
        'PLAT-ER2008' => 'O e-CNPJ não é aceito para este perfil: use um certificado e-CPF.',
        'PLAT-ER2013' => 'O usuário não possui representação no perfil selecionado.',
        'PLAT-ER2023' => 'O CNPJ não possui habilitação para atuar no perfil selecionado.',
        'PLAT-ER2033' => 'O portal exige 60 segundos entre autenticações. Tente novamente em instantes.',
        'PLAT-ER3005' => 'O CPF do usuário não está em situação regular.',
        'PLAT-ER8007' => 'Chaves de acesso inválidas ou geradas em outro ambiente (as chaves de Produção não valem na Validação e vice-versa).',
        'PLAT-ER8008' => 'Não foi possível autenticar com a chave de acesso; veja o motivo informado pelo portal.',

        // Cadastro de Atributos e Catálogo (observados na Validação).
        'CADA-ER5008' => 'Atributo obrigatório para a NCM não informado.',
        'CATP-ER2021' => 'Já existe um produto idêntico: a retificação não altera nenhum dado.',
        'CATP-ER5013' => 'Já existe um operador estrangeiro idêntico: a retificação não altera nenhum dado.',

        // Tabelas Comex (observados na Validação).
        'TABX-IN0003' => 'Tabela não encontrada.',
        'TABX-IN0067' => 'Filtro da consulta à tabela inválido.',
    ];

    public static function descricao(?string $codigo): ?string
    {
        return $codigo === null ? null : self::DESCRICOES[$codigo] ?? null;
    }
}
