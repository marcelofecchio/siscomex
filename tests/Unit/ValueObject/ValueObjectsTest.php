<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\ValueObject;

use MFecchio\Siscomex\PortalUnico\ValueObject\CpfCnpjRaiz;
use MFecchio\Siscomex\PortalUnico\ValueObject\Ncm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testNcmAceitaFormasComESemPontuacao(): void
    {
        $ncm = new Ncm('8473.30.49');

        self::assertSame('84733049', $ncm->codigo);
        self::assertSame('8473.30.49', $ncm->formatado);
        self::assertSame('84', $ncm->capitulo);
        self::assertSame('84733049', (string) $ncm);
        self::assertTrue($ncm->equals('84733049'));
        self::assertSame($ncm, Ncm::of($ncm));
    }

    /** @return iterable<array{string}> */
    public static function ncmsInvalidas(): iterable
    {
        yield ['8473304'];
        yield ['847330491'];
        yield ['8473.30.4A'];
        yield [''];
    }

    #[DataProvider('ncmsInvalidas')]
    public function testNcmInvalida(string $valor): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Ncm($valor);
    }

    public function testNcmEhSomenteLeitura(): void
    {
        $ncm = new Ncm('84733049');

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line assign.propertyPrivateSet — validando a visibilidade assimétrica */
        $ncm->codigo = '00000000';
    }

    public function testCnpjRaiz(): void
    {
        $raiz = new CpfCnpjRaiz('12.345.678');

        self::assertSame('12345678', $raiz->valor);
        self::assertFalse($raiz->isCpf);
        self::assertSame('12345678', (string) CpfCnpjRaiz::deCnpj('12.345.678/0001-95'));
    }

    public function testCnpjRaizAlfanumerico(): void
    {
        self::assertSame('12ABC34S', new CpfCnpjRaiz('12.abc.34s')->valor);
        self::assertSame('12ABC34S', CpfCnpjRaiz::deCnpj('12.ABC.34S/01DE-35')->valor);
    }

    public function testCpfValido(): void
    {
        $cpf = new CpfCnpjRaiz('529.982.247-25');

        self::assertSame('52998224725', $cpf->valor);
        self::assertTrue($cpf->isCpf);
    }

    /** @return iterable<array{string}> */
    public static function identificacoesInvalidas(): iterable
    {
        yield 'CPF com DV errado' => ['529.982.247-24'];
        yield 'CPF repetido' => ['111.111.111-11'];
        yield 'curto' => ['1234567'];
        yield 'CNPJ completo' => ['12345678000195'];
        yield 'caracteres' => ['1234567#'];
    }

    #[DataProvider('identificacoesInvalidas')]
    public function testIdentificacaoInvalida(string $valor): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CpfCnpjRaiz($valor);
    }

    public function testCnpjCompletoInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CpfCnpjRaiz::deCnpj('123');
    }
}
