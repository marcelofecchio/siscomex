<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

/**
 * Produto do catálogo como devolvido pela API (ProdutoIntegracaoResponseDTO / ProdutoIntegracaoDTO).
 */
final readonly class Produto
{
    /**
     * @param list<AtributoValor>                 $atributos
     * @param list<AtributoMultivalorado>         $atributosMultivalorados
     * @param list<AtributoComposto>              $atributosCompostos
     * @param list<AtributoCompostoMultivalorado> $atributosCompostosMultivalorados
     * @param list<string>                        $codigosInterno
     */
    public function __construct(
        public ?int $codigo,
        public ?string $versao,
        public ?string $cpfCnpjRaiz,
        public ?SituacaoProduto $situacao,
        public ?Modalidade $modalidade,
        public ?Ncm $ncm,
        public string $denominacao,
        public ?string $descricao = null,
        public array $atributos = [],
        public array $atributosMultivalorados = [],
        public array $atributosCompostos = [],
        public array $atributosCompostosMultivalorados = [],
        public array $codigosInterno = [],
        public ?\DateTimeImmutable $inicioVigencia = null,
        public ?\DateTimeImmutable $fimVigencia = null,
        public ?int $seq = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $ncm = Data::stringOrNull($a, 'ncm');
        $situacao = Data::stringOrNull($a, 'situacao');
        $modalidade = Data::stringOrNull($a, 'modalidade');

        return new self(
            codigo: Data::intOrNull($a, 'codigo'),
            versao: Data::stringOrNull($a, 'versao'),
            cpfCnpjRaiz: Data::stringOrNull($a, 'cpfCnpjRaiz'),
            situacao: $situacao === null ? null : SituacaoProduto::tryFrom(strtoupper($situacao)),
            modalidade: $modalidade === null ? null : Modalidade::fromDescricao($modalidade),
            ncm: $ncm === null ? null : self::ncmOuNull($ncm),
            denominacao: Data::string($a, 'denominacao'),
            descricao: Data::stringOrNull($a, 'descricao'),
            atributos: array_map(AtributoValor::fromArray(...), Data::listOfArrays($a, 'atributos')),
            atributosMultivalorados: array_map(AtributoMultivalorado::fromArray(...), Data::listOfArrays($a, 'atributosMultivalorados')),
            atributosCompostos: array_map(AtributoComposto::fromArray(...), Data::listOfArrays($a, 'atributosCompostos')),
            atributosCompostosMultivalorados: array_map(AtributoCompostoMultivalorado::fromArray(...), Data::listOfArrays($a, 'atributosCompostosMultivalorados')),
            codigosInterno: Data::listOfStrings($a, 'codigosInterno'),
            inicioVigencia: Data::date($a, 'inicioVigencia'),
            fimVigencia: Data::date($a, 'fimVigencia'),
            seq: Data::intOrNull($a, 'seq'),
        );
    }

    public function valorAtributo(string $codigo): ?string
    {
        foreach ($this->atributos as $a) {
            if ($a->atributo === $codigo) {
                return $a->valor;
            }
        }

        return null;
    }

    public function ativo(): bool
    {
        return $this->situacao === SituacaoProduto::Ativado;
    }

    /** Base para uma nova versão/retificação a partir deste produto. */
    public function paraRequest(): ProdutoRequest
    {
        if ($this->modalidade === null || $this->ncm === null) {
            throw new \LogicException('Produto sem modalidade ou NCM não pode ser convertido em requisição.');
        }

        return new ProdutoRequest(
            denominacao: $this->denominacao,
            modalidade: $this->modalidade,
            ncm: $this->ncm,
            descricao: $this->descricao,
            atributos: $this->atributos,
            atributosMultivalorados: $this->atributosMultivalorados,
            atributosCompostos: $this->atributosCompostos,
            atributosCompostosMultivalorados: $this->atributosCompostosMultivalorados,
            codigosInterno: $this->codigosInterno,
        );
    }

    private static function ncmOuNull(string $ncm): ?Ncm
    {
        try {
            return new Ncm($ncm);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
