<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

/**
 * Dados de um operador estrangeiro a incluir, versionar ou retificar (OperadorEstrangeiroRequestIntegracaoDTO).
 *
 * O país não faz parte do corpo: ele vai no path das operações. Imutável: os métodos
 * com*() devolvem uma nova instância.
 *
 * ```php
 * $operador = new OperadorEstrangeiroRequest('Shenzhen Parts Co., Ltd.', 'No. 1 Keji Road, Nanshan', 'Shenzhen')
 *     ->comTin('91440300MA5XXXXXX')
 *     ->comSubdivisao('CN-GD')
 *     ->comEmail('export@example.com')
 *     ->comCodigoInterno('FORN-001');
 * ```
 */
final class OperadorEstrangeiroRequest
{
    public const int MAX_NOME = 150;
    public const int MAX_LOGRADOURO = 70;
    public const int MAX_CIDADE = 35;
    public const int MAX_TIN = 35;
    public const int MAX_CEP = 9;
    public const int MAX_CODIGO_INTERNO = 35;
    public const int MAX_EMAIL = 70;

    /**
     * @param list<IdentificacaoAdicional> $identificacoesAdicionais
     */
    public function __construct(
        public private(set) string $nome,
        public private(set) string $logradouro,
        public private(set) string $nomeCidade,
        public private(set) ?string $tin = null,
        public private(set) ?string $codigoSubdivisaoPais = null,
        public private(set) ?string $cep = null,
        public private(set) ?string $codigoInterno = null,
        public private(set) ?string $email = null,
        public private(set) array $identificacoesAdicionais = [],
    ) {
        $this->codigoSubdivisaoPais = self::normalizarSubdivisao($codigoSubdivisaoPais);
        $this->validar();
    }

    public function comNome(string $nome): self
    {
        return $this->com(static fn(self $c) => $c->nome = $nome);
    }

    public function comEndereco(string $logradouro, string $nomeCidade, ?string $cep = null, ?string $codigoSubdivisaoPais = null): self
    {
        return $this->com(static function (self $c) use ($logradouro, $nomeCidade, $cep, $codigoSubdivisaoPais): void {
            $c->logradouro = $logradouro;
            $c->nomeCidade = $nomeCidade;
            $c->cep = $cep;
            $c->codigoSubdivisaoPais = self::normalizarSubdivisao($codigoSubdivisaoPais);
        });
    }

    public function comTin(?string $tin): self
    {
        return $this->com(static fn(self $c) => $c->tin = $tin);
    }

    /** Subdivisão do país no formato ISO 3166-2 (ex.: "CN-GD", "AR-B"). */
    public function comSubdivisao(?string $codigoSubdivisaoPais): self
    {
        return $this->com(static fn(self $c) => $c->codigoSubdivisaoPais = self::normalizarSubdivisao($codigoSubdivisaoPais));
    }

    public function comCep(?string $cep): self
    {
        return $this->com(static fn(self $c) => $c->cep = $cep);
    }

    public function comEmail(?string $email): self
    {
        return $this->com(static fn(self $c) => $c->email = $email);
    }

    public function comCodigoInterno(?string $codigoInterno): self
    {
        return $this->com(static fn(self $c) => $c->codigoInterno = $codigoInterno);
    }

    /**
     * @param string $codigoAgencia código da agência emissora (lista UN/EDIFACT 3055)
     */
    public function comIdentificacaoAdicional(string $codigoAgencia, string $numero): self
    {
        $identificacao = new IdentificacaoAdicional($codigoAgencia, $numero);

        return $this->com(static fn(self $c) => $c->identificacoesAdicionais[] = $identificacao);
    }

    /**
     * Corpo JSON enviado à API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dados = [
            'nome' => $this->nome,
            'logradouro' => $this->logradouro,
            'nomeCidade' => $this->nomeCidade,
            'tin' => $this->tin,
            'codigoSubdivisaoPais' => $this->codigoSubdivisaoPais,
            'cep' => $this->cep,
            'codigoInterno' => $this->codigoInterno,
            'email' => $this->email,
            'identificacoesAdicionais' => array_map(static fn(IdentificacaoAdicional $i): array => $i->toArray(), $this->identificacoesAdicionais),
        ];

        return array_filter($dados, static fn(mixed $v): bool => $v !== null);
    }

    /** @param \Closure(self): mixed $alteracao */
    private function com(\Closure $alteracao): self
    {
        $c = clone $this;
        $alteracao($c);
        $c->validar();

        return $c;
    }

    private static function normalizarSubdivisao(?string $codigo): ?string
    {
        return $codigo === null ? null : strtoupper(trim($codigo));
    }

    private function validar(): void
    {
        self::tamanho('O nome', $this->nome, self::MAX_NOME);
        self::tamanho('O logradouro', $this->logradouro, self::MAX_LOGRADOURO);
        self::tamanho('O nome da cidade', $this->nomeCidade, self::MAX_CIDADE);
        self::tamanho('O TIN', $this->tin, self::MAX_TIN);
        self::tamanho('O código postal', $this->cep, self::MAX_CEP);
        self::tamanho('O código interno', $this->codigoInterno, self::MAX_CODIGO_INTERNO);
        self::tamanho('O e-mail', $this->email, self::MAX_EMAIL);

        if ($this->codigoSubdivisaoPais !== null && preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $this->codigoSubdivisaoPais) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Subdivisão do país inválida: "%s". Use o formato ISO 3166-2 (ex.: CN-GD).', $this->codigoSubdivisaoPais));
        }
        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
            throw new \InvalidArgumentException(\sprintf('E-mail inválido: "%s".', $this->email));
        }
        foreach ($this->identificacoesAdicionais as $identificacao) {
            $problema = $identificacao->problema();
            if ($problema !== null) {
                throw new \InvalidArgumentException($problema);
            }
        }
    }

    /** Campos opcionais aceitam null; quando informados (e nos obrigatórios), exigem 1..$maximo caracteres. */
    private static function tamanho(string $campo, ?string $valor, int $maximo): void
    {
        if ($valor !== null && (trim($valor) === '' || mb_strlen($valor) > $maximo)) {
            throw new \InvalidArgumentException(\sprintf('%s deve ter entre 1 e %d caracteres.', $campo, $maximo));
        }
    }
}
