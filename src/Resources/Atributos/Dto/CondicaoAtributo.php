<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Expressão condicionante (ExpressaoCondicionanteConsultaDTO), encadeável via `composicao`
 * ("||", "&&") e `condicao`.
 *
 * A spec não enumera operadores. Nas relações públicas de 17/10/2026 aparecem `==`, `!=`, `>`,
 * `>=` e `<=` (este SDK também aceita `<`), e nenhuma condição mistura `||` com `&&` — o
 * encadeamento é avaliado da esquerda para a direita.
 */
final readonly class CondicaoAtributo
{
    public function __construct(
        public ?string $operador,
        public ?string $valor,
        public ?string $composicao = null,
        public ?CondicaoAtributo $proxima = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $proxima = Data::arrayOrNull($a, 'condicao');

        return new self(
            Data::stringOrNull($a, 'operador'),
            Data::stringOrNull($a, 'valor'),
            Data::stringOrNull($a, 'composicao'),
            $proxima === null ? null : self::fromArray($proxima),
        );
    }

    /**
     * Avalia a condição contra os valores informados no atributo condicionante.
     *
     * Sem valores, a condição não é satisfeita. Com vários valores (condicionante multivalorado),
     * `!=` exige que nenhum valor seja igual e os demais operadores, que algum valor satisfaça.
     * Retorna null quando não é possível avaliar (operador desconhecido ou comparação numérica
     * com valor não numérico).
     *
     * @param list<string> $valores
     */
    public function avaliar(array $valores): ?bool
    {
        $resultado = $this->avaliarTermo($valores);
        $atual = $this;
        while ($atual->proxima !== null) {
            $proximo = $atual->proxima->avaliarTermo($valores);
            $resultado = match (trim((string) $atual->composicao)) {
                '&&' => match (true) {
                    $resultado === false || $proximo === false => false,
                    $resultado === null || $proximo === null => null,
                    default => true,
                },
                '||' => match (true) {
                    $resultado === true || $proximo === true => true,
                    $resultado === null || $proximo === null => null,
                    default => false,
                },
                default => null,
            };
            $atual = $atual->proxima;
        }

        return $resultado;
    }

    /** @param list<string> $valores */
    private function avaliarTermo(array $valores): ?bool
    {
        if ($valores === []) {
            return false;
        }
        $esperado = (string) $this->valor;
        $operador = trim((string) $this->operador);

        if ($operador === '!=') {
            return !\in_array($esperado, $valores, true);
        }

        $algumIndeterminado = false;
        foreach ($valores as $valor) {
            $ok = self::comparar($valor, $operador, $esperado);
            if ($ok === true) {
                return true;
            }
            $algumIndeterminado = $algumIndeterminado || $ok === null;
        }

        return $algumIndeterminado ? null : false;
    }

    private static function comparar(string $valor, string $operador, string $esperado): ?bool
    {
        if ($operador === '==') {
            return $valor === $esperado;
        }
        if (!\in_array($operador, ['>', '>=', '<', '<='], true) || !is_numeric($valor) || !is_numeric($esperado)) {
            return null;
        }
        $a = (float) $valor;
        $b = (float) $esperado;

        return match ($operador) {
            '>' => $a > $b,
            '>=' => $a >= $b,
            '<' => $a < $b,
            default => $a <= $b,
        };
    }
}
