<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Support;

use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

/**
 * Extrai o conteúdo dos downloads/exportações do portal, que vêm compactados em ZIP.
 *
 * @internal
 */
final class Zip
{
    public static function isZip(string $bytes): bool
    {
        return str_starts_with($bytes, "PK\x03\x04");
    }

    /**
     * Retorna o conteúdo do primeiro arquivo do ZIP (ou os próprios bytes, se não for ZIP).
     */
    public static function primeiroArquivo(string $bytes): string
    {
        if (!self::isZip($bytes)) {
            return $bytes;
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new SiscomexException('A extensão ext-zip é necessária para ler arquivos compactados do portal.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'siscomex-zip-');
        if ($tmp === false) {
            throw new SiscomexException('Não foi possível criar arquivo temporário para o ZIP.');
        }

        try {
            file_put_contents($tmp, $bytes);
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new SiscomexException('Arquivo ZIP inválido recebido do portal.');
            }
            $conteudo = $zip->numFiles > 0 ? $zip->getFromIndex(0) : false;
            $zip->close();
            if ($conteudo === false) {
                throw new SiscomexException('Arquivo ZIP vazio recebido do portal.');
            }

            return $conteudo;
        } finally {
            @unlink($tmp);
        }
    }
}
