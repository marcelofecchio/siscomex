<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
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

    /** Atributos obrigatórios para cadastro de produto na modalidade informada. */
    public function obrigatoriosParaProduto(Modalidade $modalidade, ?\DateTimeInterface $em = null): self
    {
        return $this->filtrar($modalidade, ObjetivoAtributo::Produto, $em, true);
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
