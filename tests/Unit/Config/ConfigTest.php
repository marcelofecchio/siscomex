<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Config;

use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\Config\Perfil;
use MFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;
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
}
