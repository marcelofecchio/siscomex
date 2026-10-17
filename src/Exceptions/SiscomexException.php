<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Exceção base de todos os erros do SDK.
 *
 * Preserva os campos do corpo de erro do portal — em especial a `tag`, que deve ser
 * informada em chamados à Central Serpro de Atendimento.
 */
class SiscomexException extends \RuntimeException
{
    /**
     * @param list<ErroDetalhe>    $detalhes
     * @param array<string, mixed> $corpo    corpo de erro decodificado (sem tokens)
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $codigoErro = null,
        public readonly ?string $tag = null,
        public readonly ?string $severidade = null,
        public readonly array $detalhes = [],
        public readonly array $corpo = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /** O erro em formato estável (código, tag, campo, detalhes e mensagem amigável). */
    public function erro(): ErroApi
    {
        return ErroApi::deExcecao($this);
    }

    /** Mensagem para o usuário final (descrição conhecida do código ou a do portal). */
    public function mensagemAmigavel(): string
    {
        return $this->erro()->mensagemAmigavel();
    }

    /**
     * O erro ou algum de seus detalhes tem um destes códigos?
     * Ex.: `$e->possuiCodigo('CATP-ER2021')` (retificação idêntica à versão existente).
     */
    public function possuiCodigo(string ...$codigos): bool
    {
        return $this->erro()->possuiCodigo(...$codigos);
    }

    /** Texto pronto para abrir chamado na Central Serpro. */
    public function resumoParaChamado(): string
    {
        return trim(sprintf(
            'HTTP %s | código %s | tag %s | %s',
            $this->httpStatus ?? '-',
            $this->codigoErro ?? '-',
            $this->tag ?? '-',
            $this->getMessage(),
        ));
    }
}
