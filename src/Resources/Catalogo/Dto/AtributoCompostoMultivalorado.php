<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Atributo composto multivalorado (ProdutoCampoCompostoMultivaloradoIntegracaoDTO):
 * cada ocorrência é uma lista de subatributos preenchidos.
 *
 * A spec define `valores` como array[array] sem detalhar o item — assumimos
 * list<list<{atributo, valor}>>. TODO(confirmar) com exemplo real.
 */
final readonly class AtributoCompostoMultivalorado
{
    /** @param list<list<AtributoValor>> $valores */
    public function __construct(
        public string $atributo,
        public array $valores,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $ocorrencias = [];
        foreach (Data::listOfArrays($a, 'valores') as $ocorrencia) {
            $ocorrencias[] = array_map(AtributoValor::fromArray(...), array_values(array_filter($ocorrencia, \is_array(...))));
        }

        return new self(Data::string($a, 'atributo'), $ocorrencias);
    }

    /** @return array{atributo: string, valores: list<list<array{atributo: string, valor: string}>>} */
    public function toArray(): array
    {
        return [
            'atributo' => $this->atributo,
            'valores' => array_map(
                static fn(array $ocorrencia): array => array_map(static fn(AtributoValor $v): array => $v->toArray(), $ocorrencia),
                $this->valores,
            ),
        ];
    }
}
