<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Http;

use MFecchio\Siscomex\PortalUnico\Http\LogMasker;
use PHPUnit\Framework\TestCase;

final class LogMaskerTest extends TestCase
{
    public function testMascaraValoresCurtosELongos(): void
    {
        self::assertSame('****', LogMasker::mascarar('abcd'));
        self::assertSame('abcd***yz', LogMasker::mascarar('abcdefghijklmnopqrstuvwxyz'));
    }

    public function testIdentificaChavesSensiveis(): void
    {
        foreach (['Authorization', 'Set-Token', 'X-CSRF-Token', 'Client-Id', 'Client-Secret', 'client_secret', 'senha', 'password', 'csrfToken'] as $chave) {
            self::assertTrue(LogMasker::chaveSensivel($chave), $chave);
        }
        self::assertFalse(LogMasker::chaveSensivel('ncm'));
    }

    public function testMascaraArrayRecursivo(): void
    {
        $resultado = LogMasker::mascararArray([
            'Authorization' => 'eyJhbGciOiJIUzI1NiJ9.payload.assinatura',
            'headers' => ['Client-Secret' => ['segredo-muito-longo'], 'Accept' => 'application/json'],
            'ncm' => '84733049',
            'senha' => 12345,
            'token' => ['x' => ['y']],
        ]);

        self::assertSame('eyJh***ra', $resultado['Authorization']);
        self::assertSame(['Client-Secret' => ['segr***go'], 'Accept' => 'application/json'], $resultado['headers']);
        self::assertSame('84733049', $resultado['ncm']);
        self::assertSame('*****', $resultado['senha']);
        self::assertSame(['x' => '***'], $resultado['token']);
    }

    public function testMascaraTextoLivre(): void
    {
        $texto = LogMasker::mascararTexto('Authorization: eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.abcdefgh Client-Secret=GmKnp4-SlOMnQ3TE "senha":"minhasenha123"');

        self::assertStringNotContainsString('eyJzdWIiOiIxIn0', $texto);
        self::assertStringNotContainsString('GmKnp4-SlOMnQ3TE', $texto);
        self::assertStringNotContainsString('minhasenha123', $texto);
        self::assertStringContainsString('Client-Secret=', $texto);
    }
}
