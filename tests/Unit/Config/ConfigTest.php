<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Config;

use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testAmbientes(): void
    {
        self::assertSame('https://portalunico.siscomex.gov.br', Ambiente::Producao->baseUrl());
        self::assertSame('https://val.portalunico.siscomex.gov.br', Ambiente::Validacao->baseUrl());
        self::assertSame('val.portalunico.siscomex.gov.br', Ambiente::Validacao->host());
        self::assertTrue(Ambiente::Producao->isProducao());
        self::assertSame(Ambiente::Validacao, Ambiente::fromString('Treinamento'));
        self::assertSame(Ambiente::Producao, Ambiente::fromString('PROD'));

        $this->expectException(\InvalidArgumentException::class);
        Ambiente::fromString('homologacao');
    }

    public function testPerfis(): void
    {
        self::assertSame('Declarante Importador/Exportador', Perfil::IMPEXP->descricao());
        self::assertTrue(Perfil::IMPEXP->aceitaECpf());
        self::assertFalse(Perfil::IMPEXP->aceitaECnpj(), 'IMPEXP só aceita e-CPF');
        self::assertTrue(Perfil::DEPOSIT->aceitaECnpj());
        self::assertFalse(Perfil::TERCEIROS->aceitaECpf());
        foreach (Perfil::cases() as $perfil) {
            self::assertNotSame('', $perfil->descricao());
        }
    }

    public function testClientConfigValidaNumeros(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ClientConfig(tentativas503: -1);
    }

    public function testEnums(): void
    {
        self::assertSame(Modalidade::Importacao, Modalidade::fromDescricao('Importação'));
        self::assertSame(Modalidade::Exportacao, Modalidade::fromDescricao('EXPORTACAO'));
        self::assertSame(Modalidade::Ambos, Modalidade::fromDescricao('ambos'));
        self::assertNull(Modalidade::fromDescricao('x'));
        self::assertTrue(Modalidade::Ambos->abrange(Modalidade::Importacao));
        self::assertFalse(Modalidade::Exportacao->abrange(Modalidade::Importacao));

        self::assertSame([0, 1, 2], array_map(static fn(SituacaoProduto $s): int => $s->codigoConsulta(), SituacaoProduto::cases()));
        self::assertTrue(FormaPreenchimento::ListaEstatica->possuiDominio());
        self::assertFalse(FormaPreenchimento::Texto->possuiDominio());
        self::assertSame('Produto', ObjetivoAtributo::Produto->descricao());
        self::assertSame('Tratamento administrativo', ObjetivoAtributo::TratamentoAdministrativo->descricao());
        self::assertSame('Duimp', ObjetivoAtributo::Duimp->descricao());
    }

    /** @return iterable<string, array{Ambiente, string, bool}> */
    public static function janelas(): iterable
    {
        // 2026-10-19 é segunda-feira; 2026-10-17, sábado. Horários de Brasília.
        yield 'produção, parada das 01h' => [Ambiente::Producao, '2026-10-19 01:30', true];
        yield 'produção, 03h já disponível' => [Ambiente::Producao, '2026-10-19 03:00', false];
        yield 'produção, sábado à noite' => [Ambiente::Producao, '2026-10-17 22:00', false];
        yield 'validação, dia útil 10h' => [Ambiente::Validacao, '2026-10-19 10:00', false];
        yield 'validação, dia útil 18h' => [Ambiente::Validacao, '2026-10-19 18:00', true];
        yield 'validação, dia útil 06h59' => [Ambiente::Validacao, '2026-10-19 06:59', true];
        yield 'validação, sábado 14h' => [Ambiente::Validacao, '2026-10-17 14:00', true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('janelas')]
    public function testJanelaDeIndisponibilidade(Ambiente $ambiente, string $quando, bool $esperado): void
    {
        $em = new \DateTimeImmutable($quando, new \DateTimeZone('America/Sao_Paulo'));

        self::assertSame($esperado, $ambiente->provavelmenteIndisponivel($em));
        self::assertSame($esperado, $ambiente->janelaDeIndisponibilidade($em) !== null);
    }

    public function testJanelaConverteFuso(): void
    {
        // 04:30 UTC = 01:30 em Brasília (parada programada).
        self::assertStringContainsString('01h às 03h', (string) Ambiente::Producao->janelaDeIndisponibilidade(new \DateTimeImmutable('2026-10-19 04:30', new \DateTimeZone('UTC'))));
    }

    public function testSimularEscritaDesligadaPorPadrao(): void
    {
        self::assertFalse(new ClientConfig()->simularEscrita);
    }
}
