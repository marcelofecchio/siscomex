<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Config;

/**
 * Perfis de atuação (header "Role-Type").
 *
 * O foco do SDK é o perfil IMPEXP, mas todos os perfis de intervenientes privados
 * documentados estão disponíveis.
 *
 * @see https://docs.portalunico.siscomex.gov.br/api/plat/ (tabela "Perfis de Acesso")
 */
enum Perfil: string
{
    case IMPEXP = 'IMPEXP';
    case AGECARGA = 'AGECARGA';
    case AGEREMESS = 'AGEREMESS';
    case AJUDESPAC = 'AJUDESPAC';
    case CONTATOOEA = 'CONTATOOEA';
    case DEPOSIT = 'DEPOSIT';
    case EMPRCOMEL = 'EMPRCOMEL';
    case HABILITAD = 'HABILITAD';
    case INSTFINANC = 'INSTFINANC';
    case OPERPORT = 'OPERPORT';
    case RESPLEGAL = 'RESPLEGAL';
    case TRANSPEST = 'TRANSPEST';
    case TRANSPORT = 'TRANSPORT';
    case TERCEIROS = 'TERCEIROS';

    public function descricao(): string
    {
        return match ($this) {
            self::IMPEXP => 'Declarante Importador/Exportador',
            self::AGECARGA => 'Agente de Carga',
            self::AGEREMESS => 'Remessa Expressa/Correio',
            self::AJUDESPAC => 'Ajudante de Despachante',
            self::CONTATOOEA => 'Ponto de Contato OEA',
            self::DEPOSIT => 'Depositário',
            self::EMPRCOMEL => 'Empresa de Comércio Eletrônico',
            self::HABILITAD => 'Habilitador',
            self::INSTFINANC => 'Instituição Financeira',
            self::OPERPORT => 'Operador Portuário',
            self::RESPLEGAL => 'Responsável Legal OEA',
            self::TRANSPEST => 'PF – Representante de TETI',
            self::TRANSPORT => 'Transportador',
            self::TERCEIROS => 'Terceiros (Outros Intervenientes)',
        };
    }

    /** O perfil admite autenticação com certificado e-CPF? */
    public function aceitaECpf(): bool
    {
        return $this !== self::TERCEIROS;
    }

    /** O perfil admite autenticação com certificado e-CNPJ? (IMPEXP não admite.) */
    public function aceitaECnpj(): bool
    {
        return \in_array($this, [
            self::AGEREMESS,
            self::DEPOSIT,
            self::EMPRCOMEL,
            self::OPERPORT,
            self::TRANSPORT,
            self::TERCEIROS,
        ], true);
    }
}
