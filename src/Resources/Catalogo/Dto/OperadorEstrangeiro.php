<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoOperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Versão de um operador estrangeiro como devolvida pela API (OperadorEstrangeiroIntegracaoDTO).
 *
 * O operador é identificado por (cpfCnpjRaiz, codigoPais, codigo); cada alteração gera uma
 * versão ("1", "2"...) ou retificação ("1.1").
 */
final readonly class OperadorEstrangeiro
{
    /**
     * @param list<IdentificacaoAdicional> $identificacoesAdicionais
     */
    public function __construct(
        public ?string $codigo,
        public ?string $versao,
        public ?string $cpfCnpjRaiz,
        public ?string $codigoPais,
        public ?SituacaoOperadorEstrangeiro $situacao,
        public string $nome,
        public string $logradouro = '',
        public string $nomeCidade = '',
        public ?string $tin = null,
        public ?string $codigoSubdivisaoPais = null,
        public ?string $cep = null,
        public ?string $codigoInterno = null,
        public ?string $email = null,
        public array $identificacoesAdicionais = [],
        public ?\DateTimeImmutable $inicioVigencia = null,
        public ?\DateTimeImmutable $fimVigencia = null,
        public ?int $seq = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            codigo: Data::stringOrNull($a, 'codigo'),
            versao: Data::stringOrNull($a, 'versao'),
            cpfCnpjRaiz: Data::stringOrNull($a, 'cpfCnpjRaiz'),
            codigoPais: Data::stringOrNull($a, 'codigoPais'),
            situacao: SituacaoOperadorEstrangeiro::fromTexto(Data::stringOrNull($a, 'situacao')),
            nome: Data::string($a, 'nome'),
            logradouro: Data::string($a, 'logradouro'),
            nomeCidade: Data::string($a, 'nomeCidade'),
            tin: Data::stringOrNull($a, 'tin'),
            codigoSubdivisaoPais: Data::stringOrNull($a, 'codigoSubdivisaoPais'),
            cep: Data::stringOrNull($a, 'cep'),
            codigoInterno: Data::stringOrNull($a, 'codigoInterno'),
            email: Data::stringOrNull($a, 'email'),
            identificacoesAdicionais: array_map(IdentificacaoAdicional::fromArray(...), Data::listOfArrays($a, 'identificacoesAdicionais')),
            inicioVigencia: Data::date($a, 'inicioVigencia'),
            fimVigencia: Data::date($a, 'fimVigencia'),
            seq: Data::intOrNull($a, 'seq'),
        );
    }

    public function ativo(): bool
    {
        return $this->situacao === SituacaoOperadorEstrangeiro::Ativado;
    }

    /** Base para uma nova versão/retificação a partir deste operador. */
    public function paraRequest(): OperadorEstrangeiroRequest
    {
        return new OperadorEstrangeiroRequest(
            nome: $this->nome,
            logradouro: $this->logradouro,
            nomeCidade: $this->nomeCidade,
            tin: $this->tin,
            codigoSubdivisaoPais: $this->codigoSubdivisaoPais,
            cep: $this->cep,
            codigoInterno: $this->codigoInterno,
            email: $this->email,
            identificacoesAdicionais: $this->identificacoesAdicionais,
        );
    }
}
