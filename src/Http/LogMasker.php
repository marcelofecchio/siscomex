<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Http;

/**
 * Mascara segredos (tokens, senhas, chaves de acesso) antes de enviá-los ao logger.
 */
final class LogMasker
{
    /** Nomes normalizados (minúsculas, sem "-", "_" ou espaço). */
    private const array CHAVES_SENSIVEIS = [
        'authorization', 'settoken', 'xcsrftoken', 'clientid', 'clientsecret',
        'token', 'jwt', 'csrftoken', 'senha', 'password', 'secret',
    ];

    public static function mascarar(string $valor): string
    {
        $tamanho = \strlen($valor);
        if ($tamanho <= 8) {
            return str_repeat('*', $tamanho);
        }

        return substr($valor, 0, 4).'***'.substr($valor, -2);
    }

    public static function chaveSensivel(string $chave): bool
    {
        return \in_array(strtolower(str_replace(['_', '-', ' '], '', $chave)), self::CHAVES_SENSIVEIS, true);
    }

    /**
     * Mascara recursivamente valores cujas chaves sejam sensíveis.
     *
     * @param array<mixed> $dados
     *
     * @return array<mixed>
     */
    public static function mascararArray(array $dados): array
    {
        $resultado = [];
        foreach ($dados as $chave => $valor) {
            if (\is_string($chave) && self::chaveSensivel($chave)) {
                $resultado[$chave] = \is_array($valor)
                    ? array_map(static fn(mixed $v): string => \is_scalar($v) ? self::mascarar((string) $v) : '***', $valor)
                    : (\is_scalar($valor) ? self::mascarar((string) $valor) : '***');
                continue;
            }
            $resultado[$chave] = \is_array($valor) ? self::mascararArray($valor) : $valor;
        }

        return $resultado;
    }

    /**
     * Mascara JWTs e pares "chave: valor" / "chave=valor" sensíveis em texto livre.
     */
    public static function mascararTexto(string $texto): string
    {
        $texto = (string) preg_replace_callback(
            '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/',
            static fn(array $m): string => self::mascarar($m[0]),
            $texto,
        );

        return (string) preg_replace_callback(
            '/("?(?:authorization|set-token|x-csrf-token|client-id|client-secret|token|senha|password)"?\s*[:=]\s*"?)([^"\s,}]+)/i',
            static fn(array $m): string => $m[1].self::mascarar($m[2]),
            $texto,
        );
    }
}
