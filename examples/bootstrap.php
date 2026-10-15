<?php

declare(strict_types=1);

/*
 * Bootstrap comum dos exemplos: lê .env (se existir) e cria o cliente a partir das variáveis:
 *
 *   SISCOMEX_AMBIENTE=validacao|producao
 *   SISCOMEX_CLIENT_ID / SISCOMEX_CLIENT_SECRET   (Chaves de Acesso)
 *   ou SISCOMEX_CERT_PATH / SISCOMEX_CERT_SENHA   (certificado A1 e-CPF)
 */

use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\CertificadoA1;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\FileTokenStore;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use Psr\Log\AbstractLogger;

require __DIR__.'/../vendor/autoload.php';

$envFile = __DIR__.'/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
        if (str_starts_with(trim($linha), '#') || !str_contains($linha, '=')) {
            continue;
        }
        [$nome, $valor] = explode('=', $linha, 2);
        if (getenv(trim($nome)) === false) {
            putenv(trim($nome).'='.trim($valor, " \t\"'"));
        }
    }
}

function env(string $nome, ?string $padrao = null): ?string
{
    $valor = getenv($nome);

    return \is_string($valor) && $valor !== '' ? $valor : $padrao;
}

function criarCliente(): PortalUnicoClient
{
    $certificado = env('SISCOMEX_CERT_PATH');
    $credencial = $certificado !== null
        ? new CertificadoA1($certificado, (string) env('SISCOMEX_CERT_SENHA', ''))
        : ChaveAcesso::fromEnv();

    $logger = new class extends AbstractLogger {
        public function log($level, string|Stringable $message, array $context = []): void
        {
            $replace = [];
            foreach ($context as $k => $v) {
                $replace['{'.$k.'}'] = \is_scalar($v) ? (string) $v : '';
            }
            fwrite(STDERR, \sprintf("[%s] %s\n", \is_string($level) ? $level : '?', strtr((string) $message, $replace)));
        }
    };

    return PortalUnicoClient::create(
        ambiente: Ambiente::fromString((string) env('SISCOMEX_AMBIENTE', 'validacao')),
        credencial: $credencial,
        cache: new FileTokenStore(__DIR__.'/../storage/tokens'),
        logger: $logger,
    );
}
