<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Um item de erro dentro da lista `detail` devolvida pelo portal.
 *
 * O formato não está nas specs. Observado no Catálogo (Validação, 15/10/2026):
 * `{"error": {"message", "code", "field", ...}}` — ex.: CADA-ER5008 "O Atributo ATT_x para o
 * NCM y é obrigatório.", field "ATT_x". Também aceitamos o objeto sem o envelope "error"
 * e texto simples.
 */
final readonly class ErroDetalhe
{
    public function __construct(
        public string $mensagem,
        public ?string $codigo = null,
        public ?string $campo = null,
    ) {}

    public static function fromMixed(mixed $item): self
    {
        if (\is_array($item) && \is_array($item['error'] ?? null)) {
            $item = $item['error'];
        }
        if (\is_array($item)) {
            $mensagem = $item['message'] ?? $item['mensagem'] ?? $item['descricao'] ?? json_encode($item, JSON_UNESCAPED_UNICODE);
            $codigo = $item['code'] ?? $item['codigo'] ?? null;
            $campo = $item['field'] ?? $item['campo'] ?? $item['path'] ?? null;

            return new self(
                \is_scalar($mensagem) ? (string) $mensagem : '',
                \is_scalar($codigo) ? (string) $codigo : null,
                \is_scalar($campo) ? (string) $campo : null,
            );
        }

        return new self(\is_scalar($item) ? (string) $item : '');
    }
}
