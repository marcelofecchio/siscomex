<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Http;

use GuzzleHttp\Client;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\CertificadoA1;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\Credential;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use Psr\Http\Client\ClientInterface;

/**
 * Cria um cliente Guzzle 7 (PSR-18) configurado para o portal, incluindo o certificado
 * A1 no handshake quando a credencial exigir mTLS.
 */
final class GuzzleClientFactory
{
    public static function disponivel(): bool
    {
        return class_exists(Client::class);
    }

    /**
     * @param array<string, mixed> $opcoesExtras opções Guzzle adicionais (ex.: proxy)
     */
    public static function create(ClientConfig $config, ?Credential $credential = null, array $opcoesExtras = []): ClientInterface
    {
        if (!self::disponivel()) {
            throw new \LogicException('Instale guzzlehttp/guzzle (composer require guzzlehttp/guzzle) ou informe um cliente PSR-18 próprio.');
        }

        $opcoes = [
            'timeout' => $config->timeout,
            'connect_timeout' => min(30, $config->timeout),
            'http_errors' => false,
            'allow_redirects' => false,
            'verify' => true,
        ];

        if ($credential instanceof CertificadoA1) {
            if (!\extension_loaded('curl')) {
                throw new \LogicException('A autenticação mTLS com certificado A1 exige a extensão ext-curl.');
            }
            $opcoes['curl'] = $credential->opcoesCurl();
        }

        return new Client(array_replace($opcoes, $opcoesExtras));
    }
}
