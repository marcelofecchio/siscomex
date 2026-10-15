<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Auth;

use MFecchio\Siscomex\PortalUnico\Auth\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testValidadeConsideraMargem(): void
    {
        $agora = new \DateTimeImmutable('2026-10-15 10:00:00');
        $session = new Session('jwt', 'csrf', $agora->modify('+90 seconds'), $agora);

        self::assertTrue($session->isValid($agora, 60));
        self::assertFalse($session->isValid($agora, 90));
        self::assertFalse($session->isValid($agora->modify('+31 seconds'), 60));
    }

    public function testWithCsrfPreservaJwt(): void
    {
        $agora = new \DateTimeImmutable();
        $nova = new Session('jwt', 'a', $agora, $agora)->withCsrf('b', $agora->modify('+1 hour'));

        self::assertSame('jwt', $nova->jwt);
        self::assertSame('b', $nova->csrfToken);
    }

    public function testParseExpiracaoEmMilissegundosESegundos(): void
    {
        $agora = new \DateTimeImmutable('@1760000000');

        self::assertSame(1760003600, Session::parseExpiration('1760003600000', $agora)->getTimestamp());
        self::assertSame(1760003600, Session::parseExpiration('1760003600', $agora)->getTimestamp());
        self::assertSame(1760003600, Session::parseExpiration(null, $agora)->getTimestamp());
        self::assertSame(1760003600, Session::parseExpiration('invalido', $agora)->getTimestamp());
    }

    public function testSerializacao(): void
    {
        $agora = new \DateTimeImmutable('@1760000000');
        $session = new Session('jwt', 'csrf', $agora->modify('+1 hour'), $agora);

        $copia = Session::fromArray($session->toArray());

        self::assertNotNull($copia);
        self::assertSame($session->toArray(), $copia->toArray());
        self::assertNull(Session::fromArray(['jwt' => 'x']));
    }

    public function testDebugInfoNaoExpoeTokens(): void
    {
        $agora = new \DateTimeImmutable();
        $dump = print_r(new Session('jwt-secreto', 'csrf-secreto', $agora, $agora), true);

        self::assertStringNotContainsString('jwt-secreto', $dump);
        self::assertStringNotContainsString('csrf-secreto', $dump);
    }
}
