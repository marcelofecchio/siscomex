<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Erro do portal em formato estável, igual para qualquer exceção do SDK — pronto para a UI
 * (ex.: uma linha de importação) ou para serializar em JSON com `toArray()`.
 *
 * Não inclui o corpo bruto da resposta (que pode trazer o CPF do usuário em `info.usuario`).
 */
final readonly class ErroApi
{
    /** @param list<ErroDetalhe> $detalhes */
    public function __construct(
        public string $mensagem,
        public ?int $httpStatus = null,
        public ?string $codigo = null,
        public ?string $tag = null,
        public ?string $severidade = null,
        public ?string $campo = null,
        public array $detalhes = [],
    ) {}

    public static function deExcecao(SiscomexException $e): self
    {
        $campo = $e->corpo['field'] ?? null;

        return new self(
            mensagem: $e->getMessage(),
            httpStatus: $e->httpStatus,
            codigo: $e->codigoErro,
            tag: $e->tag,
            severidade: $e->severidade,
            campo: \is_scalar($campo) && (string) $campo !== '' ? (string) $campo : null,
            detalhes: $e->detalhes,
        );
    }

    /** Mensagem para o usuário: a descrição conhecida do código ou, na falta dela, a do portal. */
    public function mensagemAmigavel(): string
    {
        return CodigosErro::descricao($this->codigo) ?? trim($this->mensagem);
    }

    /**
     * Mensagens do portal agrupadas pelo campo/atributo afetado (ex.: "ATT_1234"); erros sem
     * campo ficam na chave "".
     *
     * @return array<string, list<string>>
     */
    public function porCampo(): array
    {
        if ($this->detalhes === []) {
            return [$this->campo ?? '' => [$this->mensagem]];
        }
        $porCampo = [];
        foreach ($this->detalhes as $detalhe) {
            $porCampo[$detalhe->campo ?? ''][] = $detalhe->mensagem !== '' ? $detalhe->mensagem : (CodigosErro::descricao($detalhe->codigo) ?? '');
        }

        return $porCampo;
    }

    /**
     * Código do erro e dos detalhes, sem repetição.
     *
     * @return list<string>
     */
    public function codigos(): array
    {
        $codigos = [$this->codigo, ...array_map(static fn(ErroDetalhe $d): ?string => $d->codigo, $this->detalhes)];

        return array_values(array_unique(array_filter($codigos, static fn(?string $c): bool => $c !== null && $c !== '')));
    }

    public function possuiCodigo(string ...$codigos): bool
    {
        return array_intersect($this->codigos(), $codigos) !== [];
    }

    /** Texto pronto para abrir chamado na Central Serpro. */
    public function resumoParaChamado(): string
    {
        return trim(\sprintf('HTTP %s | código %s | tag %s | %s', $this->httpStatus ?? '-', $this->codigo ?? '-', $this->tag ?? '-', $this->mensagem));
    }

    /**
     * @return array{httpStatus: ?int, codigo: ?string, tag: ?string, severidade: ?string, campo: ?string, mensagem: string, mensagemAmigavel: string, detalhes: list<array{codigo: ?string, campo: ?string, mensagem: string}>}
     */
    public function toArray(): array
    {
        return [
            'httpStatus' => $this->httpStatus,
            'codigo' => $this->codigo,
            'tag' => $this->tag,
            'severidade' => $this->severidade,
            'campo' => $this->campo,
            'mensagem' => $this->mensagem,
            'mensagemAmigavel' => $this->mensagemAmigavel(),
            'detalhes' => array_map(static fn(ErroDetalhe $d): array => ['codigo' => $d->codigo, 'campo' => $d->campo, 'mensagem' => $d->mensagem], $this->detalhes),
        ];
    }
}
