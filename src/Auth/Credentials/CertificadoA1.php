<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Auth\Credentials;

use MFecchio\Siscomex\PortalUnico\Exceptions\CertificateException;

/**
 * Certificado digital ICP-Brasil A1 (arquivo .pfx/.p12 ou .pem) para autenticação mTLS
 * em POST /portal/api/autenticar.
 *
 * O perfil IMPEXP aceita apenas e-CPF. Certificados A3 (token/cartão) não são suportados.
 *
 * Como o PSR-18 não padroniza opções de TLS, o certificado precisa ser configurado no
 * próprio cliente HTTP. `PortalUnicoClient::create()` faz isso automaticamente com Guzzle;
 * para outros clientes use `opcoesCurl()` ou `paraPem()`.
 */
final readonly class CertificadoA1 implements Credential
{
    public string $caminho;

    public function __construct(
        string $caminho,
        #[\SensitiveParameter]
        private string $senha = '',
    ) {
        if (!is_file($caminho) || !is_readable($caminho)) {
            throw new CertificateException(\sprintf('Arquivo de certificado não encontrado ou ilegível: %s', $caminho));
        }
        $this->caminho = (string) realpath($caminho);
    }

    public function isPkcs12(): bool
    {
        return \in_array(strtolower(pathinfo($this->caminho, PATHINFO_EXTENSION)), ['pfx', 'p12'], true);
    }

    public function endpointAutenticacao(): string
    {
        return '/portal/api/autenticar';
    }

    public function headersAutenticacao(): array
    {
        return [];
    }

    public function exigeMtls(): bool
    {
        return true;
    }

    public function identificador(): string
    {
        return 'cert-'.substr(hash('sha256', $this->caminho), 0, 16);
    }

    /**
     * Opções nativas do cURL para apresentar o certificado no handshake.
     * Para .pfx/.p12 usa CURLOPT_SSLCERTTYPE=P12 (sem conversão em disco).
     *
     * @return array<int, string>
     */
    public function opcoesCurl(): array
    {
        $opcoes = [
            CURLOPT_SSLCERT => $this->caminho,
            CURLOPT_SSLCERTTYPE => $this->isPkcs12() ? 'P12' : 'PEM',
        ];
        if ($this->senha !== '') {
            $opcoes[CURLOPT_SSLCERTPASSWD] = $this->senha;
            if (!$this->isPkcs12()) {
                $opcoes[CURLOPT_SSLKEYPASSWD] = $this->senha;
            }
        }

        return $opcoes;
    }

    /**
     * Converte o .pfx em um PEM temporário (certificado + chave + cadeia) com permissão 0600.
     * O arquivo é apagado quando o objeto retornado é destruído.
     */
    public function paraPem(?string $diretorio = null): CertificadoPemTemporario
    {
        if (!$this->isPkcs12()) {
            return new CertificadoPemTemporario($this->caminho, apagarAoDestruir: false);
        }
        if (!\function_exists('openssl_pkcs12_read')) {
            throw new CertificateException('A extensão ext-openssl é necessária para converter o certificado .pfx.');
        }

        $conteudo = (string) file_get_contents($this->caminho);
        $certs = [];
        if (!openssl_pkcs12_read($conteudo, $certs, $this->senha) || !\is_array($certs)) {
            throw new CertificateException('Não foi possível ler o certificado .pfx (senha incorreta ou arquivo inválido): '.(openssl_error_string() ?: ''));
        }

        $pem = '';
        foreach ([$certs['cert'] ?? null, $certs['pkey'] ?? null] as $parte) {
            $pem .= \is_string($parte) ? $parte : '';
        }
        $extras = $certs['extracerts'] ?? [];
        foreach (\is_array($extras) ? $extras : [] as $extra) {
            $pem .= \is_string($extra) ? $extra : '';
        }

        $diretorio ??= sys_get_temp_dir();
        $arquivo = tempnam($diretorio, 'siscomex-');
        if ($arquivo === false) {
            throw new CertificateException('Não foi possível criar arquivo temporário para o certificado.');
        }
        chmod($arquivo, 0o600);
        file_put_contents($arquivo, $pem);

        return new CertificadoPemTemporario($arquivo, apagarAoDestruir: true);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['caminho' => $this->caminho, 'senha' => '***'];
    }
}
