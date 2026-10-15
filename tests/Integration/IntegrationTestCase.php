<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Integration;

use MFecchio\Siscomex\PortalUnico\Auth\Credentials\CertificadoA1;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\FileTokenStore;
use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use PHPUnit\Framework\TestCase;

/**
 * Testes contra o portal real. Só rodam com SISCOMEX_RUN_INTEGRATION=1 e
 * SISCOMEX_AMBIENTE=validacao (nunca em produção). A sessão fica em storage/ e é
 * compartilhada entre os testes para respeitar a regra dos 60 s.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static ?PortalUnicoClient $client = null;

    protected function setUp(): void
    {
        if (getenv('SISCOMEX_RUN_INTEGRATION') !== '1') {
            self::markTestSkipped('Defina SISCOMEX_RUN_INTEGRATION=1 para rodar os testes de integração.');
        }
        if (getenv('SISCOMEX_AMBIENTE') !== 'validacao') {
            self::markTestSkipped('Testes de integração só rodam com SISCOMEX_AMBIENTE=validacao.');
        }
    }

    protected static function client(): PortalUnicoClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $cert = getenv('SISCOMEX_CERT_PATH');
        $credencial = \is_string($cert) && $cert !== ''
            ? new CertificadoA1($cert, (string) getenv('SISCOMEX_CERT_SENHA'))
            : ChaveAcesso::fromEnv();

        return self::$client = PortalUnicoClient::create(
            credencial: $credencial,
            cache: new FileTokenStore(__DIR__.'/../../storage/tokens'),
            config: new ClientConfig(ambiente: Ambiente::Validacao),
        );
    }

    /**
     * Executa a chamada tratando indisponibilidade do ambiente de Validação
     * (manutenções seg–sex 18h–7h e fins de semana) como teste pulado.
     *
     * @template T
     *
     * @param \Closure(): T $chamada
     *
     * @return T
     */
    protected function tolerandoIndisponibilidade(\Closure $chamada): mixed
    {
        try {
            return $chamada();
        } catch (ServiceUnavailableException $e) {
            self::markTestSkipped('Ambiente de Validação indisponível: '.$e->resumoParaChamado());
        }
    }

    protected static function env(string $nome, string $padrao = ''): string
    {
        $valor = getenv($nome);

        return \is_string($valor) && $valor !== '' ? $valor : $padrao;
    }
}
