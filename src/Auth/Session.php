<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Auth;

/**
 * Sessão autenticada no portal: JWT (header Set-Token) + X-CSRF-Token renovável.
 */
final readonly class Session
{
    /** Vida útil do X-CSRF-Token quando o portal não informa X-CSRF-Expiration. */
    public const int CSRF_TTL_PADRAO = 3600;

    public function __construct(
        public string $jwt,
        public string $csrfToken,
        public \DateTimeImmutable $csrfExpiresAt,
        public \DateTimeImmutable $authenticatedAt,
    ) {}

    public function isValid(\DateTimeImmutable $now, int $marginSeconds = 60): bool
    {
        return $this->csrfExpiresAt->getTimestamp() - $marginSeconds > $now->getTimestamp();
    }

    /** Nova sessão com o CSRF renovado (o JWT permanece). */
    public function withCsrf(string $csrfToken, \DateTimeImmutable $expiresAt): self
    {
        return new self($this->jwt, $csrfToken, $expiresAt, $this->authenticatedAt);
    }

    /**
     * Converte o header X-CSRF-Expiration (epoch em milissegundos) em data.
     */
    public static function parseExpiration(?string $header, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if ($header !== null && preg_match('/^\d{10,16}$/', trim($header)) === 1) {
            $ms = (int) trim($header);
            $segundos = \strlen(trim($header)) >= 13 ? intdiv($ms, 1000) : $ms;

            return $now->setTimestamp($segundos);
        }

        return $now->modify('+'.self::CSRF_TTL_PADRAO.' seconds');
    }

    /** @return array{jwt: string, csrfToken: string, csrfExpiresAt: int, authenticatedAt: int} */
    public function toArray(): array
    {
        return [
            'jwt' => $this->jwt,
            'csrfToken' => $this->csrfToken,
            'csrfExpiresAt' => $this->csrfExpiresAt->getTimestamp(),
            'authenticatedAt' => $this->authenticatedAt->getTimestamp(),
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $jwt = $data['jwt'] ?? null;
        $csrf = $data['csrfToken'] ?? null;
        $exp = $data['csrfExpiresAt'] ?? null;
        $auth = $data['authenticatedAt'] ?? null;
        if (!\is_string($jwt) || !\is_string($csrf) || !\is_int($exp) || !\is_int($auth)) {
            return null;
        }
        $tz = new \DateTimeZone('America/Sao_Paulo');

        return new self(
            $jwt,
            $csrf,
            (new \DateTimeImmutable('@'.$exp))->setTimezone($tz),
            (new \DateTimeImmutable('@'.$auth))->setTimezone($tz),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return [
            'jwt' => '***',
            'csrfToken' => '***',
            'csrfExpiresAt' => $this->csrfExpiresAt->format(\DATE_ATOM),
            'authenticatedAt' => $this->authenticatedAt->format(\DATE_ATOM),
        ];
    }
}
