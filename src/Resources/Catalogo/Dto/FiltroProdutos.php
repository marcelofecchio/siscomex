<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
use MFecchio\Siscomex\PortalUnico\Support\Data;
use MFecchio\Siscomex\PortalUnico\ValueObject\CpfCnpjRaiz;
use MFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

/**
 * Filtros de GET /ext/produto (Consultar Produtos). Apenas `cpfCnpjRaiz` é obrigatório.
 */
final readonly class FiltroProdutos
{
    public CpfCnpjRaiz $cpfCnpjRaiz;

    public ?Ncm $ncm;

    public function __construct(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        public ?int $codigo = null,
        public ?string $codigoInterno = null,
        public ?string $descricao = null,
        public ?string $denominacao = null,
        Ncm|string|null $ncm = null,
        public ?SituacaoProduto $situacao = null,
        public ?Modalidade $modalidade = null,
        public ?\DateTimeInterface $periodoRegistroInicio = null,
        public ?\DateTimeInterface $periodoRegistroFim = null,
        public ?\DateTimeInterface $ultimaAlteracaoInicio = null,
        public ?\DateTimeInterface $ultimaAlteracaoFim = null,
        public ?\DateTimeInterface $dataReferencia = null,
        public ?string $paisOrigem = null,
        public ?string $cpfCnpjFabricante = null,
        public ?string $operadorEstrangeiroCodigo = null,
    ) {
        $this->cpfCnpjRaiz = CpfCnpjRaiz::of($cpfCnpjRaiz);
        $this->ncm = $ncm === null ? null : Ncm::of($ncm);
        if ($paisOrigem !== null && preg_match('/^[A-Z]{2}$/', $paisOrigem) !== 1) {
            throw new \InvalidArgumentException('paisOrigem deve ser o código ISO 3166 de 2 letras maiúsculas (ex.: CN).');
        }
    }

    /** @return array<string, scalar|null> */
    public function toQuery(): array
    {
        return [
            'cpfCnpjRaiz' => $this->cpfCnpjRaiz->valor,
            'codigo' => $this->codigo,
            'codigoInterno' => $this->codigoInterno,
            'descricao' => $this->descricao,
            'denominacao' => $this->denominacao,
            'ncm' => $this->ncm?->codigo,
            'situacao' => $this->situacao?->codigoConsulta(),
            'modalidade' => $this->modalidade?->value,
            'periodoRegistroInicio' => Data::formatDate($this->periodoRegistroInicio),
            'periodoRegistroFim' => Data::formatDate($this->periodoRegistroFim),
            'ultimaAlteracaoInicio' => Data::formatDate($this->ultimaAlteracaoInicio),
            'ultimaAlteracaoFim' => Data::formatDate($this->ultimaAlteracaoFim),
            'dataReferencia' => Data::formatDate($this->dataReferencia),
            'paisOrigem' => $this->paisOrigem,
            'cpfCnpjFabricante' => $this->cpfCnpjFabricante === null ? null : (preg_replace('/\D/', '', $this->cpfCnpjFabricante) ?? ''),
            'operadorEstrangeiroCodigo' => $this->operadorEstrangeiroCodigo,
        ];
    }
}
