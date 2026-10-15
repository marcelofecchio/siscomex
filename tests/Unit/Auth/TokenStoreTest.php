<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Auth;

use MFecchio\Siscomex\PortalUnico\Auth\Session;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\FileTokenStore;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\InMemoryTokenStore;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\Psr16TokenStore;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\TokenStore;
use MFecchio\Siscomex\PortalUnico\Tests\Support\ArrayCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenStoreTest extends TestCase
{
    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir().'/siscomex-tests-'.bin2hex(random_bytes(4));
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir(self::$dir);
    }

    /** @return iterable<string, array{\Closure(): TokenStore}> */
    public static function stores(): iterable
    {
        yield 'memória' => [static fn(): TokenStore => new InMemoryTokenStore()];
        yield 'arquivo' => [static fn(): TokenStore => new FileTokenStore(self::$dir.'/'.bin2hex(random_bytes(3)))];
        yield 'psr-16' => [static fn(): TokenStore => new Psr16TokenStore(new ArrayCache())];
    }

    /** @param \Closure(): TokenStore $factory */
    #[DataProvider('stores')]
    public function testContratoDoTokenStore(\Closure $factory): void
    {
        $store = $factory();
        $agora = new \DateTimeImmutable('@'.time());
        $session = new Session('jwt', 'csrf', $agora->modify('+1 hour'), $agora);

        self::assertNull($store->getSession('k'));
        self::assertNull($store->getLastAuthentication('k'));

        $store->saveSession('k', $session);
        $store->saveLastAuthentication('k', $agora);

        self::assertSame($session->toArray(), $store->getSession('k')?->toArray());
        self::assertSame($agora->getTimestamp(), $store->getLastAuthentication('k')?->getTimestamp());
        self::assertNull($store->getSession('outra'));

        $store->deleteSession('k');
        self::assertNull($store->getSession('k'));
        self::assertNotNull($store->getLastAuthentication('k'), 'apagar a sessão mantém o registro dos 60 s');

        $executou = false;
        $store->withLock('k', static function () use (&$executou): void {
            $executou = true;
        });
        self::assertTrue($executou);
    }

    public function testFileTokenStoreCompartilhaEntreInstanciasEUsaLock(): void
    {
        $dir = self::$dir.'/compartilhado';
        $agora = new \DateTimeImmutable('@'.time());
        new FileTokenStore($dir)->saveSession('k', new Session('jwt', 'csrf', $agora->modify('+1 hour'), $agora));

        $outra = new FileTokenStore($dir);
        self::assertSame('jwt', $outra->getSession('k')?->jwt);

        $outra->withLock('k', static fn(): null => null);
        self::assertNotEmpty(glob($dir.'/*.lock'));
        self::assertEmpty(glob($dir.'/*.tmp'));
    }

    public function testFileTokenStoreIgnoraArquivoCorrompido(): void
    {
        $dir = self::$dir.'/corrompido';
        $store = new FileTokenStore($dir);
        $store->saveLastAuthentication('k', new \DateTimeImmutable());
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            file_put_contents($file, '{corrompido');
        }

        self::assertNull($store->getSession('k'));
        self::assertNull($store->getLastAuthentication('k'));
    }

    public function testPsr16UsaTtlAteExpiracaoEChavesValidas(): void
    {
        $cache = new ArrayCache();
        $store = new Psr16TokenStore($cache, 'app_');
        $agora = new \DateTimeImmutable('@'.time());

        $store->saveSession('validacao|IMPEXP|chave-abc', new Session('jwt', 'csrf', $agora->modify('+1800 seconds'), $agora));

        $chave = (string) array_key_first($cache->data);
        self::assertMatchesRegularExpression('/^app_session_[a-f0-9]{32}$/', $chave);
        self::assertEqualsWithDelta(1800, $cache->ttls[$chave], 2);
    }

    public function testPsr16UsaLockInformado(): void
    {
        $chamadas = [];
        $store = new Psr16TokenStore(new ArrayCache(), lock: static function (string $key, callable $cb) use (&$chamadas): mixed {
            $chamadas[] = $key;

            return $cb();
        });

        $store->withLock('k', static fn(): null => null);
        self::assertCount(1, $chamadas);
    }
}
