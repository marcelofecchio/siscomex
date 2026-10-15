<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit;

use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

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
