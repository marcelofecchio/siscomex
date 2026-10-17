<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CodigoPais;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CpfCnpjRaiz;

/**
 * Filtros de GET /ext/operador-estrangeiro (Consultar Operador Estrangeiro). Apenas `cpfCnpjRaiz` é obrigatório.
 *
 * Sem `exibirDesativados: true` a consulta omite os operadores desativados (observado na Validação;
 * a consulta de produtos, ao contrário, inclui os desativados por padrão).
 */
final readonly class FiltroOperadoresEstrangeiros
{
    public const int MAX_NOME = 70;

    public CpfCnpjRaiz $cpfCnpjRaiz;

    public ?CodigoPais $paisOrigem;

    public function __construct(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        public ?string $codigo = null,
        public ?string $tin = null,
        public ?string $codigoInterno = null,
        public ?string $nome = null,
        CodigoPais|string|null $paisOrigem = null,
        public ?bool $exibirDesativados = null,
    ) {
        $this->cpfCnpjRaiz = CpfCnpjRaiz::of($cpfCnpjRaiz);
        $this->paisOrigem = $paisOrigem === null ? null : CodigoPais::of($paisOrigem);
        if ($nome !== null && mb_strlen($nome) > self::MAX_NOME) {
            throw new \InvalidArgumentException(\sprintf('O filtro por nome aceita no máximo %d caracteres.', self::MAX_NOME));
        }
    }

    /** @return array<string, scalar|null> */
    public function toQuery(): array
    {
        return [
            'cpfCnpjRaiz' => $this->cpfCnpjRaiz->valor,
            'codigo' => $this->codigo,
            'tin' => $this->tin,
            'codigoInterno' => $this->codigoInterno,
            'nome' => $this->nome,
            'paisOrigem' => $this->paisOrigem?->codigo,
            'exibirDesativados' => $this->exibirDesativados,
        ];
    }
}
