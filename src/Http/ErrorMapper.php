<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Http;

use MFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationThrottledException;
use MFecchio\Siscomex\PortalUnico\Exceptions\CertificateException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ErroDetalhe;
use MFecchio\Siscomex\PortalUnico\Exceptions\ForbiddenException;
use MFecchio\Siscomex\PortalUnico\Exceptions\NotFoundException;
use MFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ServerException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Converte respostas de erro do portal em exceções tipadas.
 *
 * Formato documentado: {"message","code","tag","status","severity"} e, na prática,
 * também "detail" (lista), "field", "path", "date" e "info".
 */
final class ErrorMapper
{
    public const string TIMEZONE = 'America/Sao_Paulo';

    /** Códigos que indicam problema de certificado digital. */
    private const array CODIGOS_CERTIFICADO = ['PLAT-ER2001', 'PLAT-ER8001', 'PLAT-ER8002'];

    /** Códigos documentados (ou observados) do serviço de autenticação. */
    private const array CODIGOS_AUTENTICACAO = [
        'PUCX-ER0101', 'PUCX-ER0102',
        'PLAT-ER2002', 'PLAT-ER2004', 'PLAT-ER2008', 'PLAT-ER2013', 'PLAT-ER2023', 'PLAT-ER3005',
        'PLAT-ER8003', 'PLAT-ER8004', 'PLAT-ER8007', 'PLAT-ER8008',
    ];

    public static function fromResponse(ResponseInterface $response, ?\DateTimeImmutable $agora = null): SiscomexException
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $corpo = self::decodificar($raw);

        $textoBruto = trim(strip_tags($raw));
        $codigo = self::texto($corpo['code'] ?? null);
        $mensagem = self::texto($corpo['message'] ?? null)
            ?? ($corpo === [] && $textoBruto !== '' ? mb_substr($textoBruto, 0, 500) : null)
            ?? \sprintf('Erro HTTP %d retornado pelo Portal Único.', $status);
        $tag = self::texto($corpo['tag'] ?? null);
        $severidade = self::texto($corpo['severity'] ?? null);
        $detalhes = self::detalhes($corpo);

        $args = [$mensagem, $status, $codigo, $tag, $severidade, $detalhes, $corpo];

        if ($codigo === 'PUCX-ER1001') {
            $e = new RateLimitException(...$args);
            [$e->limite, $e->liberadoEm] = self::extrairLiberacao($mensagem, $agora);

            return $e;
        }
        if ($codigo === 'PLAT-ER2033') {
            return new AuthenticationThrottledException(...$args);
        }
        if ($codigo === 'PUCX-ER0401') {
            return new ServiceUnavailableException(...$args);
        }
        if ($codigo !== null && \in_array($codigo, self::CODIGOS_CERTIFICADO, true)) {
            return new CertificateException(...$args);
        }
        if ($codigo !== null && \in_array($codigo, self::CODIGOS_AUTENTICACAO, true)) {
            return new AuthenticationException(...$args);
        }

        return match (true) {
            $status === 401 => new AuthenticationException(...$args),
            $status === 403 => new ForbiddenException(...$args),
            $status === 404 => new NotFoundException(...$args),
            $status === 400, $status === 422 => new ValidationException(...$args),
            $status === 503 => new ServiceUnavailableException(...$args),
            $status >= 500 => new ServerException(...$args),
            default => new SiscomexException(...$args),
        };
    }

    /**
     * Extrai o limite e o horário de liberação da mensagem do PUCX-ER1001.
     *
     * @return array{0: ?int, 1: ?\DateTimeImmutable}
     */
    public static function extrairLiberacao(string $mensagem, ?\DateTimeImmutable $agora = null): array
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        $agora = ($agora ?? new \DateTimeImmutable('now', $tz))->setTimezone($tz);

        $limite = preg_match('/limite de (\d+) acessos/iu', $mensagem, $m) === 1 ? (int) $m[1] : null;

        $liberadoEm = null;
        if (preg_match('/ap[oó]s\s+(?:as\s+|às\s+)?(?:(\d{2})\/(\d{2})\/(\d{4})\s+)?(\d{1,2}):(\d{2})(?::(\d{2}))?/iu', $mensagem, $m) === 1) {
            $data = $m[1] !== ''
                ? $agora->setDate((int) $m[3], (int) $m[2], (int) $m[1])
                : $agora;
            $liberadoEm = $data->setTime((int) $m[4], (int) $m[5], (int) ($m[6] ?? 0));
        }

        return [$limite, $liberadoEm];
    }

    /** @return array<string, mixed> */
    private static function decodificar(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        try {
            $dados = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($dados)) {
            return [];
        }

        $resultado = [];
        foreach ($dados as $chave => $valor) {
            $resultado[(string) $chave] = $valor;
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $corpo
     *
     * @return list<ErroDetalhe>
     */
    private static function detalhes(array $corpo): array
    {
        $lista = $corpo['detail'] ?? $corpo['details'] ?? $corpo['erros'] ?? [];
        if (!\is_array($lista)) {
            return [];
        }

        return array_values(array_map(ErroDetalhe::fromMixed(...), $lista));
    }

    private static function texto(mixed $valor): ?string
    {
        return \is_scalar($valor) && (string) $valor !== '' ? (string) $valor : null;
    }
}
