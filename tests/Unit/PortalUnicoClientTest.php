<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
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

    public function testTestarConexaoComSucessoNaoExpoeTokens(): void
    {
        $this->http->queue(self::authResponse(expirationMs: ($this->clock->now()->getTimestamp() + 3600) * 1000));

        $resultado = $this->client()->testarConexao();

        self::assertTrue($resultado->sucesso);
        self::assertSame(Ambiente::Validacao, $resultado->ambiente);
        self::assertSame('Conectado ao Portal Único (Validação, perfil IMPEXP).', $resultado->mensagem);
        self::assertSame($this->clock->now()->getTimestamp() + 3600, $resultado->sessaoExpiraEm?->getTimestamp());
        self::assertNull($resultado->erro);
        self::assertStringNotContainsString('csrf-1', print_r($resultado, true));
    }

    public function testTestarConexaoReaproveitaSessao(): void
    {
        $this->http->queue(self::authResponse());
        $client = $this->client();

        $client->testarConexao();
        self::assertTrue($client->testarConexao()->sucesso);
        self::assertCount(1, $this->http->requests);
    }

    public function testTestarConexaoComChaveDeOutroAmbiente(): void
    {
        $this->http->queue(self::error(422, 'PLAT-ER8007', 'Par de chaves inválido ou inexistente'));

        $resultado = $this->client()->testarConexao();

        self::assertFalse($resultado->sucesso);
        self::assertInstanceOf(AuthenticationException::class, $resultado->erro);
        self::assertStringContainsString('ambiente de Validação', $resultado->mensagem);
        self::assertStringContainsString('PLAT-ER8007', $resultado->mensagem);
    }

    public function testTestarConexaoSemRede(): void
    {
        $this->http->queue(new ConnectException('DNS', new Request('POST', 'https://val.portalunico.siscomex.gov.br')));

        $resultado = $this->client()->testarConexao();

        self::assertFalse($resultado->sucesso);
        self::assertStringStartsWith('Não foi possível se comunicar', $resultado->mensagem);
    }
}
