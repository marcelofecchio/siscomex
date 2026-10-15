<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit;

use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\Perfil;
use MFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use MFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class PortalUnicoClientTest extends TestCase
{
    public function testCreateComPadroes(): void
    {
        $client = PortalUnicoClient::create(credencial: self::chave());

        self::assertSame(Ambiente::Validacao, $client->config->ambiente);
        self::assertSame(Perfil::IMPEXP, $client->config->perfil);
        self::assertSame($client->atributos(), $client->atributos());
        self::assertSame($client->catalogo()->produtos(), $client->catalogo()->produtos());
        self::assertSame($client->atributosPublicos(), $client->atributosPublicos());
    }

    public function testCreateComAmbienteEPerfil(): void
    {
        $client = PortalUnicoClient::create(ambiente: Ambiente::Producao, perfil: Perfil::DEPOSIT);

        self::assertSame(Ambiente::Producao, $client->config->ambiente);
        self::assertSame(Perfil::DEPOSIT, $client->config->perfil);
    }

    public function testSemCredencialNaoAutentica(): void
    {
        $this->expectException(\LogicException::class);
        $this->client(semCredencial: true)->autenticar();
    }

    public function testEncerrarSessaoSemCredencial(): void
    {
        $this->expectException(\LogicException::class);
        $this->client(semCredencial: true)->encerrarSessao();
    }
}
