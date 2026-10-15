<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Atributo do Cadastro de Atributos (ArquivoDiarioAtributoDTO).
 *
 * Campos de vínculo (modalidade, obrigatorio, multivalorado, vigência) só vêm preenchidos
 * quando o atributo é retornado no contexto de uma NCM.
 */
final readonly class Atributo
{
    /**
     * @param list<ItemDominio>            $dominio
     * @param list<ObjetivoVinculado>      $objetivos
     * @param list<string>                 $orgaos
     * @param list<AtributoCondicionado>   $condicionados
     * @param list<Atributo>               $subatributos
     */
    public function __construct(
        public string $codigo,
        public string $nomeApresentacao = '',
        public ?string $nome = null,
        public ?string $definicao = null,
        public ?string $orientacaoPreenchimento = null,
        public ?FormaPreenchimento $formaPreenchimento = null,
        public ?string $formaPreenchimentoOriginal = null,
        public ?int $tamanhoMaximo = null,
        public ?int $casasDecimais = null,
        public ?string $mascara = null,
        public ?Modalidade $modalidade = null,
        public ?bool $obrigatorio = null,
        public ?bool $multivalorado = null,
        public ?\DateTimeImmutable $dataInicioVigencia = null,
        public ?\DateTimeImmutable $dataFimVigencia = null,
        public ?string $informacaoAdicional = null,
        public array $dominio = [],
        public array $objetivos = [],
        public array $orgaos = [],
        public bool $atributoCondicionante = false,
        public array $condicionados = [],
        public array $subatributos = [],
        public ?string $brid = null,
        public ?string $wcoid = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $forma = Data::stringOrNull($a, 'formaPreenchimento');
        $modalidade = Data::stringOrNull($a, 'modalidade');

        return new self(
            codigo: Data::string($a, 'codigo'),
            nomeApresentacao: Data::string($a, 'nomeApresentacao'),
            nome: Data::stringOrNull($a, 'nome'),
            definicao: Data::stringOrNull($a, 'definicao'),
            orientacaoPreenchimento: Data::stringOrNull($a, 'orientacaoPreenchimento'),
            formaPreenchimento: $forma === null ? null : FormaPreenchimento::tryFrom($forma),
            formaPreenchimentoOriginal: $forma,
            tamanhoMaximo: Data::intOrNull($a, 'tamanhoMaximo'),
            casasDecimais: Data::intOrNull($a, 'casasDecimais'),
            mascara: Data::stringOrNull($a, 'mascara'),
            modalidade: $modalidade === null ? null : Modalidade::fromDescricao($modalidade),
            obrigatorio: \array_key_exists('obrigatorio', $a) ? Data::bool($a, 'obrigatorio') : null,
            multivalorado: \array_key_exists('multivalorado', $a) ? Data::bool($a, 'multivalorado') : null,
            dataInicioVigencia: Data::date($a, 'dataInicioVigencia'),
            dataFimVigencia: Data::date($a, 'dataFimVigencia'),
            informacaoAdicional: Data::stringOrNull($a, 'informacaoAdicional'),
            dominio: array_map(ItemDominio::fromArray(...), Data::listOfArrays($a, 'dominio')),
            objetivos: array_map(ObjetivoVinculado::fromArray(...), Data::listOfArrays($a, 'objetivos')),
            orgaos: Data::listOfStrings($a, 'orgaos'),
            atributoCondicionante: Data::bool($a, 'atributoCondicionante'),
            condicionados: array_map(AtributoCondicionado::fromArray(...), Data::listOfArrays($a, 'condicionados')),
            subatributos: array_map(self::fromArray(...), Data::listOfArrays($a, 'listaSubatributos')),
            brid: Data::stringOrNull($a, 'brid'),
            wcoid: Data::stringOrNull($a, 'wcoid'),
        );
    }

    /**
     * Combina o detalhe do atributo com os dados de um vínculo NCM (obrigatoriedade, modalidade, vigência).
     */
    public function comVinculo(VinculoAtributoNcm $vinculo): self
    {
        return new self(
            codigo: $this->codigo,
            nomeApresentacao: $this->nomeApresentacao,
            nome: $this->nome,
            definicao: $this->definicao,
            orientacaoPreenchimento: $this->orientacaoPreenchimento,
            formaPreenchimento: $this->formaPreenchimento,
            formaPreenchimentoOriginal: $this->formaPreenchimentoOriginal,
            tamanhoMaximo: $this->tamanhoMaximo,
            casasDecimais: $this->casasDecimais,
            mascara: $this->mascara,
            modalidade: $vinculo->modalidade,
            obrigatorio: $vinculo->obrigatorio,
            multivalorado: $vinculo->multivalorado,
            dataInicioVigencia: $vinculo->dataInicioVigencia ?? $this->dataInicioVigencia,
            dataFimVigencia: $vinculo->dataFimVigencia ?? $this->dataFimVigencia,
            informacaoAdicional: $this->informacaoAdicional,
            dominio: $this->dominio,
            objetivos: $this->objetivos,
            orgaos: $this->orgaos,
            atributoCondicionante: $this->atributoCondicionante,
            condicionados: $this->condicionados,
            subatributos: $this->subatributos,
            brid: $this->brid,
            wcoid: $this->wcoid,
        );
    }

    public function temObjetivo(ObjetivoAtributo $objetivo): bool
    {
        foreach ($this->objetivos as $o) {
            if (strcasecmp($o->descricao, $objetivo->descricao()) === 0) {
                return true;
            }
        }

        return false;
    }

    public function vigenteEm(\DateTimeInterface $data): bool
    {
        $dia = $data->format('Y-m-d');

        return ($this->dataInicioVigencia === null || $this->dataInicioVigencia->format('Y-m-d') <= $dia)
            && ($this->dataFimVigencia === null || $this->dataFimVigencia->format('Y-m-d') >= $dia);
    }

    public function valorDominioValido(string $valor): bool
    {
        if ($this->dominio === []) {
            return true;
        }
        foreach ($this->dominio as $item) {
            if ($item->codigo === $valor) {
                return true;
            }
        }

        return false;
    }
}
