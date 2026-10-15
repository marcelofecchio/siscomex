<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Um item de erro dentro da lista `detail` devolvida pelo portal.
 *
 * O formato de cada item não é documentado nas specs (TODO(confirmar)); aceitamos
 * texto simples ou objeto com message/code/field.
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
