<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

/** Resultado do vínculo de um fabricante a um produto. */
final readonly class ResultadoVinculo
{
    public function __construct(
        public Fabricante $fabricante,
        public ?int $codigoProduto,
        public StatusVinculo $status,
        public ?VinculoFabricante $vinculo = null,
        public ?SiscomexException $erro = null,
    ) {}

    public function vinculado(): bool
    {
        return $this->status === StatusVinculo::Vinculado;
    }

    /** Requisição para reenviar o vínculo (null se o produto ainda não tem código). */
    public function request(): ?FabricanteRequest
    {
        return $this->codigoProduto === null || $this->codigoProduto < 1 ? null : $this->fabricante->paraProduto($this->codigoProduto);
    }

    /** Erro e Pendente podem ser reenviados; Incerto exige conferência antes. */
    public function reprocessavel(): bool
    {
        return \in_array($this->status, [StatusVinculo::Erro, StatusVinculo::Pendente], true) && $this->request() !== null;
    }
}
