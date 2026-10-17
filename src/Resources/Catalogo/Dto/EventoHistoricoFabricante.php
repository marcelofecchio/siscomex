<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Evento do histórico de fabricantes/produtores de um produto (FabricanteHistoricoResponseDTO).
 *
 * `operacao`: "Inclusão" ou "Exclusão". `cpfCnpjTin`: CPF/CNPJ do fabricante nacional ou TIN do
 * operador estrangeiro. `codigo`/`nome`: operador estrangeiro, quando houver.
 * `conhecido` vem como "Sim"/"Não" (observado na Validação em 17/10/2026; a spec diz "true"/"false").
 */
final readonly class EventoHistoricoFabricante
{
    public function __construct(
        public ?\DateTimeImmutable $data,
        public ?string $usuario,
        public ?string $operacao,
        public ?bool $conhecido,
        public ?string $codigoPais,
        public ?string $cpfCnpjTin,
        public ?string $codigo,
        public ?string $nome,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            data: Data::date($a, 'data'),
            usuario: Data::stringOrNull($a, 'usuario'),
            operacao: Data::stringOrNull($a, 'operacao'),
            conhecido: self::simNao($a['conhecido'] ?? null),
            codigoPais: Data::stringOrNull($a, 'codigoPais'),
            cpfCnpjTin: Data::stringOrNull($a, 'cpfCnpjTin'),
            codigo: Data::stringOrNull($a, 'codigo'),
            nome: Data::stringOrNull($a, 'nome'),
        );
    }

    public function inclusao(): bool
    {
        return $this->operacao !== null && str_starts_with(mb_strtolower($this->operacao), 'inclus');
    }

    private static function simNao(mixed $valor): ?bool
    {
        if (\is_bool($valor)) {
            return $valor;
        }

        return match (\is_scalar($valor) ? mb_strtolower(trim((string) $valor)) : null) {
            'sim', 'true', '1' => true,
            'não', 'nao', 'false', '0' => false,
            default => null,
        };
    }
}
