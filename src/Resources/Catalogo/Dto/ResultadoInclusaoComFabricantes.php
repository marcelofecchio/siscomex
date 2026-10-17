<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoInclusaoComFabricantes;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

/**
 * Resultado de `produtos()->incluirComFabricantes()`: o produto já foi incluído; os vínculos
 * podem ter falhado individualmente.
 */
final readonly class ResultadoInclusaoComFabricantes
{
    /** @param list<ResultadoVinculo> $vinculos */
    public function __construct(
        public Produto $produto,
        public array $vinculos,
    ) {}

    public function situacao(): SituacaoInclusaoComFabricantes
    {
        foreach ($this->vinculos as $vinculo) {
            if (!$vinculo->vinculado()) {
                return SituacaoInclusaoComFabricantes::ProdutoSemVinculo;
            }
        }

        return SituacaoInclusaoComFabricantes::Completo;
    }

    public function completo(): bool
    {
        return $this->situacao() === SituacaoInclusaoComFabricantes::Completo;
    }

    /**
     * Vínculos que podem ser reenviados (Erro e Pendente) — os Incertos ficam de fora: confira
     * com fabricantes()->historico() antes.
     *
     * @return list<FabricanteRequest>
     */
    public function paraReprocessar(): array
    {
        return self::reprocessaveis($this->vinculos);
    }

    /** @return list<ResultadoVinculo> */
    public function porStatus(StatusVinculo $status): array
    {
        return array_values(array_filter($this->vinculos, static fn(ResultadoVinculo $v): bool => $v->status === $status));
    }

    /** Erro que interrompeu os vínculos (rate limit, indisponibilidade, rede), se houve. */
    public function interrupcao(): ?SiscomexException
    {
        return self::interrupcaoEm($this->vinculos);
    }

    /** @param list<ResultadoVinculo> $vinculos */
    public static function interrupcaoEm(array $vinculos): ?SiscomexException
    {
        foreach ($vinculos as $vinculo) {
            if (\in_array($vinculo->status, [StatusVinculo::Pendente, StatusVinculo::Incerto], true) && $vinculo->erro !== null) {
                return $vinculo->erro;
            }
        }

        return null;
    }

    public function retomarApos(): ?\DateTimeImmutable
    {
        $interrupcao = $this->interrupcao();

        return $interrupcao instanceof RateLimitException ? $interrupcao->liberadoEm : null;
    }

    /**
     * @param list<ResultadoVinculo> $vinculos
     *
     * @return list<FabricanteRequest>
     */
    public static function reprocessaveis(array $vinculos): array
    {
        $requests = [];
        foreach ($vinculos as $vinculo) {
            $request = $vinculo->reprocessavel() ? $vinculo->request() : null;
            if ($request !== null) {
                $requests[] = $request;
            }
        }

        return $requests;
    }
}
