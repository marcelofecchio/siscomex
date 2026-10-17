<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

/**
 * Atributos vinculados a uma NCM, já combinados com os dados do vínculo
 * (modalidade, obrigatoriedade, multivalorado, vigência).
 *
 * @implements \IteratorAggregate<int, Atributo>
 */
final readonly class AtributosDaNcm implements \Countable, \IteratorAggregate
{
    /**
     * @param list<Atributo> $atributos
     */
    public function __construct(
        public Ncm $ncm,
        public array $atributos,
        public ?string $versao = null,
    ) {}

    /**
     * Interpreta a resposta de GET /ext/atributo-ncm/{codigo-ncm}.
     *
     * A spec não documenta o schema da resposta (TODO(confirmar)); aceitamos:
     * - a estrutura do arquivo diário: {versao, listaNcm: [{codigoNcm, listaAtributos}], detalhesAtributos};
     * - {codigoNcm, listaAtributos: [ArquivoDiarioAtributoDTO...]};
     * - uma lista de ArquivoDiarioAtributoDTO.
     */
    public static function fromResponse(Ncm $ncm, mixed $resposta): self
    {
        if (!\is_array($resposta)) {
            return new self($ncm, []);
        }

        if (isset($resposta['listaNcm'])) {
            return RelacaoAtributosNcm::fromArray($resposta)->atributosDaNcm($ncm)
                ?? new self($ncm, [], Data::stringOrNull($resposta, 'versao'));
        }

        $itens = isset($resposta['listaAtributos'])
            ? Data::listOfArrays($resposta, 'listaAtributos')
            : (isset($resposta['atributos']) ? Data::listOfArrays($resposta, 'atributos') : Data::listFromResponse($resposta));

        return new self(
            $ncm,
            array_map(Atributo::fromArray(...), $itens),
            Data::stringOrNull($resposta, 'versao'),
        );
    }

    /**
     * Filtra por modalidade, objetivo e vigência (padrão: hoje).
     */
    public function filtrar(
        ?Modalidade $modalidade = null,
        ?ObjetivoAtributo $objetivo = null,
        ?\DateTimeInterface $vigentesEm = null,
        ?bool $obrigatorio = null,
    ): self {
        $vigentesEm ??= new \DateTimeImmutable('now', new \DateTimeZone(Data::TIMEZONE));

        $filtrados = array_values(array_filter(
            $this->atributos,
            static fn(Atributo $a): bool => ($modalidade === null || $a->modalidade === null || $a->modalidade->abrange($modalidade))
                && ($objetivo === null || $a->objetivos === [] || $a->temObjetivo($objetivo))
                && ($obrigatorio === null || $a->obrigatorio === $obrigatorio)
                && $a->vigenteEm($vigentesEm),
        ));

        return new self($this->ncm, $filtrados, $this->versao);
    }

    /**
     * Atributos obrigatórios **incondicionais** para cadastro de produto na modalidade informada.
     * Para incluir os condicionados cuja condição o produto satisfaz, use obrigatoriosPara().
     */
    public function obrigatoriosParaProduto(Modalidade $modalidade, ?\DateTimeInterface $em = null): self
    {
        return $this->filtrar($modalidade, ObjetivoAtributo::Produto, $em, true);
    }

    /**
     * Atributos aplicáveis ao produto: os vinculados à NCM (modalidade do produto, objetivo
     * Produto, vigentes) mais os condicionados cuja condição é satisfeita pelos valores já
     * informados — em cascata, pois um condicionado pode condicionar outros.
     *
     * Os condicionados entram com a obrigatoriedade e o multivalorado do vínculo condicional.
     */
    public function aplicaveisPara(ProdutoRequest $produto, ?\DateTimeInterface $em = null): self
    {
        $em ??= new \DateTimeImmutable('now', new \DateTimeZone(Data::TIMEZONE));
        $aplicaveis = $this->filtrar($produto->modalidade, ObjetivoAtributo::Produto, $em)->atributos;
        $codigos = array_fill_keys(array_map(static fn(Atributo $a): string => $a->codigo, $aplicaveis), true);

        for ($i = 0; $i < \count($aplicaveis); ++$i) {
            $condicionante = $aplicaveis[$i];
            foreach (self::condicionadosParaProdutoDe($condicionante, $em) as $condicionado) {
                if (isset($codigos[$condicionado->atributo->codigo])
                    || $condicionado->satisfeitaPor($produto->valoresDoAtributo($condicionante->codigo)) !== true) {
                    continue;
                }
                $aplicaveis[] = $condicionado->comoAtributo($condicionante->modalidade);
                $codigos[$condicionado->atributo->codigo] = true;
            }
        }

        return new self($this->ncm, $aplicaveis, $this->versao);
    }

    /** Obrigatórios para o produto, incluindo os condicionados cuja condição ele satisfaz. */
    public function obrigatoriosPara(ProdutoRequest $produto, ?\DateTimeInterface $em = null): self
    {
        $aplicaveis = $this->aplicaveisPara($produto, $em);

        return new self(
            $this->ncm,
            array_values(array_filter($aplicaveis->atributos, static fn(Atributo $a): bool => $a->obrigatorio === true)),
            $this->versao,
        );
    }

    /**
     * Todos os atributos condicionados possíveis para cadastro de produto na modalidade
     * (vigentes e com objetivo Produto), em qualquer nível, cada um com o código do
     * condicionante e a condição. Útil para montar formulários e planilhas-modelo.
     *
     * @return list<AtributoCondicionado>
     */
    public function condicionadosParaProduto(Modalidade $modalidade, ?\DateTimeInterface $em = null): array
    {
        $em ??= new \DateTimeImmutable('now', new \DateTimeZone(Data::TIMEZONE));
        $fila = $this->filtrar($modalidade, ObjetivoAtributo::Produto, $em)->atributos;
        $resultado = [];
        $vistos = [];

        while ($fila !== []) {
            $atributo = array_shift($fila);
            foreach (self::condicionadosParaProdutoDe($atributo, $em) as $condicionado) {
                $chave = $condicionado->codigoCondicionante.'>'.$condicionado->atributo->codigo;
                if (isset($vistos[$chave])) {
                    continue;
                }
                $vistos[$chave] = true;
                $resultado[] = $condicionado;
                $fila[] = $condicionado->atributo;
            }
        }

        return $resultado;
    }

    /** Definição condicional de um atributo (em qualquer nível), se ele for condicionado. */
    public function condicionadoPorCodigo(string $codigo, ?Modalidade $modalidade = null): ?AtributoCondicionado
    {
        foreach ($this->condicionadosParaProduto($modalidade ?? Modalidade::Ambos) as $condicionado) {
            if ($condicionado->atributo->codigo === $codigo) {
                return $condicionado;
            }
        }

        return null;
    }

    /** @return list<AtributoCondicionado> */
    private static function condicionadosParaProdutoDe(Atributo $atributo, \DateTimeInterface $em): array
    {
        return array_values(array_filter(
            $atributo->condicionados,
            static fn(AtributoCondicionado $c): bool => $c->vigenteEm($em)
                && ($c->atributo->objetivos === [] || $c->atributo->temObjetivo(ObjetivoAtributo::Produto)),
        ));
    }

    public function porCodigo(string $codigo): ?Atributo
    {
        foreach ($this->atributos as $a) {
            if ($a->codigo === $codigo) {
                return $a;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function codigos(): array
    {
        return array_map(static fn(Atributo $a): string => $a->codigo, $this->atributos);
    }

    public function count(): int
    {
        return \count($this->atributos);
    }

    /** @return \ArrayIterator<int, Atributo> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->atributos);
    }
}
