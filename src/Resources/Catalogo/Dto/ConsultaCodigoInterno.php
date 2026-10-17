<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoCodigoInterno;

/**
 * Produtos que usam exatamente um código interno, em qualquer situação
 * (resultado de `produtos()->buscarPorCodigoInterno()`).
 *
 * A consulta do portal devolve a versão mais recente de cada produto. `parcial` = HTTP 206:
 * o portal truncou o resultado e a situação pode estar incompleta.
 */
final readonly class ConsultaCodigoInterno
{
    /** @param list<Produto> $produtos */
    public function __construct(
        public string $codigoInterno,
        public array $produtos,
        public bool $parcial = false,
    ) {}

    public function situacao(): SituacaoCodigoInterno
    {
        return match (true) {
            $this->ativos() !== [] => SituacaoCodigoInterno::Ativo,
            $this->produtos !== [] => SituacaoCodigoInterno::Desativado,
            default => SituacaoCodigoInterno::Livre,
        };
    }

    /** @return list<Produto> */
    public function ativos(): array
    {
        return array_values(array_filter($this->produtos, static fn(Produto $p): bool => $p->ativo()));
    }

    /** @return list<Produto> */
    public function inativos(): array
    {
        return array_values(array_filter($this->produtos, static fn(Produto $p): bool => !$p->ativo()));
    }

    /** Mais de um produto ativo com o mesmo código (o portal aceita; normalmente é erro de cadastro). */
    public function duplicado(): bool
    {
        return \count($this->ativos()) > 1;
    }

    /**
     * Produto mais recente: o de início de vigência mais novo, preferindo os ativos.
     */
    public function maisRecente(): ?Produto
    {
        $candidatos = $this->ativos() !== [] ? $this->ativos() : $this->produtos;
        usort($candidatos, static fn(Produto $a, Produto $b): int => ($b->inicioVigencia?->getTimestamp() ?? 0) <=> ($a->inicioVigencia?->getTimestamp() ?? 0)
            ?: ($b->codigo ?? 0) <=> ($a->codigo ?? 0));

        return $candidatos[0] ?? null;
    }
}
