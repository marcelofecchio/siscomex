<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Auth;

use GuzzleHttp\Client;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\CertificadoA1;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\CertificateException;
use MarceloFecchio\Siscomex\PortalUnico\Http\GuzzleClientFactory;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    /** @var list<string> */
    private array $arquivos = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivos as $arquivo) {
            @unlink($arquivo);
        }
    }

    public function testChaveAcesso(): void
    {
        $chave = new ChaveAcesso('id', 'secret');

        self::assertSame('/portal/api/autenticar/chave-acesso', $chave->endpointAutenticacao());
        self::assertSame(['Client-Id' => 'id', 'Client-Secret' => 'secret'], $chave->headersAutenticacao());
        self::assertFalse($chave->exigeMtls());
        self::assertStringNotContainsString('secret', print_r($chave, true));
        self::assertSame($chave->identificador(), new ChaveAcesso('id', 'outro')->identificador());
    }

    public function testChaveAcessoVaziaEInvalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChaveAcesso('', 'x');
    }

    public function testChaveAcessoFromEnv(): void
    {
        putenv('TESTE_CLIENT_ID=abc');
        putenv('TESTE_CLIENT_SECRET=def');
        try {
            self::assertSame(['Client-Id' => 'abc', 'Client-Secret' => 'def'], ChaveAcesso::fromEnv('TESTE_')->headersAutenticacao());
        } finally {
            putenv('TESTE_CLIENT_ID');
            putenv('TESTE_CLIENT_SECRET');
        }

        $this->expectException(\InvalidArgumentException::class);
        ChaveAcesso::fromEnv('INEXISTENTE_');
    }

    public function testCertificadoInexistente(): void
    {
        $this->expectException(CertificateException::class);
        new CertificadoA1('/nao/existe.pfx', 'x');
    }

    public function testCertificadoPfxOpcoesCurlEConversaoParaPem(): void
    {
        $pfx = $this->gerarPfx('senha123');
        $cert = new CertificadoA1($pfx, 'senha123');

        self::assertTrue($cert->isPkcs12());
        self::assertTrue($cert->exigeMtls());
        self::assertSame('/portal/api/autenticar', $cert->endpointAutenticacao());
        self::assertSame([], $cert->headersAutenticacao());
        self::assertSame('P12', $cert->opcoesCurl()[CURLOPT_SSLCERTTYPE]);
        self::assertSame('senha123', $cert->opcoesCurl()[CURLOPT_SSLCERTPASSWD]);
        self::assertStringNotContainsString('senha123', print_r($cert, true));

        $pem = $cert->paraPem();
        $caminho = $pem->caminho;
        $conteudo = (string) file_get_contents($caminho);
        self::assertStringContainsString('BEGIN CERTIFICATE', $conteudo);
        self::assertStringContainsString('PRIVATE KEY', $conteudo);

        unset($pem);
        gc_collect_cycles();
        self::assertFileDoesNotExist($caminho, 'PEM temporário deve ser apagado');
    }

    public function testCertificadoPfxComSenhaErrada(): void
    {
        $cert = new CertificadoA1($this->gerarPfx('certa'), 'errada');

        $this->expectException(CertificateException::class);
        $cert->paraPem();
    }

    public function testCertificadoPemNaoConverte(): void
    {
        $pem = (string) tempnam(sys_get_temp_dir(), 'cert').'.pem';
        file_put_contents($pem, 'conteudo');
        $this->arquivos[] = $pem;

        $cert = new CertificadoA1($pem, 'x');
        self::assertFalse($cert->isPkcs12());
        self::assertSame('PEM', $cert->opcoesCurl()[CURLOPT_SSLCERTTYPE]);
        self::assertSame('x', $cert->opcoesCurl()[CURLOPT_SSLKEYPASSWD]);
        self::assertSame(realpath($pem), $cert->paraPem()->caminho);
        self::assertFileExists($pem, 'arquivo original não pode ser apagado');
    }

    public function testGuzzleFactoryConfiguraCertificado(): void
    {
        $cert = new CertificadoA1($this->gerarPfx('s'), 's');

        $client = GuzzleClientFactory::create(new ClientConfig(timeout: 30), $cert);

        self::assertInstanceOf(Client::class, $client);
        $config = new \ReflectionProperty($client, 'config')->getValue($client);
        self::assertIsArray($config);
        self::assertSame(30, $config['timeout']);
        self::assertFalse($config['http_errors']);
        self::assertIsArray($config['curl']);
        self::assertSame('P12', $config['curl'][CURLOPT_SSLCERTTYPE]);
    }

    private function gerarPfx(string $senha): string
    {
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = getenv('OPENSSL_CONF');
        $doPhp = \dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (\is_string($cnf) && $cnf !== '') {
            $config['config'] = $cnf;
        } elseif (is_file($doPhp)) {
            $config['config'] = $doPhp;
        }
        $key = @openssl_pkey_new($config);
        if ($key === false) {
            self::markTestSkipped('OpenSSL sem configuração para gerar chaves neste ambiente.');
        }
        $chaveCsr = $key; // openssl_csr_new recebe a chave por referência
        $csr = openssl_csr_new(['commonName' => 'TESTE:12345678909'], $chaveCsr, $config);
        if ($csr === false || $csr === true) {
            self::markTestSkipped('Não foi possível gerar CSR.');
        }
        $x509 = openssl_csr_sign($csr, null, $key, 1, $config);
        if ($x509 === false) {
            self::markTestSkipped('Não foi possível assinar certificado.');
        }
        $pfx = '';
        openssl_pkcs12_export($x509, $pfx, $key, $senha);
        $arquivo = (string) tempnam(sys_get_temp_dir(), 'cert').'.pfx';
        file_put_contents($arquivo, $pfx);
        $this->arquivos[] = $arquivo;

        return $arquivo;
    }
}
