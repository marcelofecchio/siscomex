<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos;

use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributosDaNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\RelacaoAtributosNcm;
use MarceloFecchio\Siscomex\PortalUnico\Support\Zip;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

/**
 * Download público (sem autenticação) da relação de atributos por NCM,
 * atualizada diariamente à meia-noite:
 * GET {ambiente}/cadatributos/api/atributo-ncm/download/json?perfil=PUBLICO
 *
 * Útil para validar o payload de um produto antes de enviá-lo (ver ValidadorProduto).
 * O arquivo completo tem ~15 MB (JSON) — guarde-o em cache por dia.
 *
 * @see https://www.gov.br/siscomex/pt-br/sistemas-de-comercio-exterior/integre-seu-sistema
 */
final readonly class AtributosPublicos
{
    public function __construct(private Transport $transport) {}

    /** Conteúdo JSON bruto (já descompactado) — para gravar em cache local. */
    public function baixarJsonPorNcm(): string
    {
        $response = $this->transport->request(
            'GET',
            AtributosResource::PREFIXO.'/atributo-ncm/download/json',
            ['perfil' => 'PUBLICO'],
            headers: ['Accept' => 'application/zip, application/json'],
            authenticated: false,
        );

        return Zip::primeiroArquivo((string) $response->getBody());
    }

    public function baixarPorNcm(): RelacaoAtributosNcm
    {
        return RelacaoAtributosNcm::fromJson($this->baixarJsonPorNcm());
    }

    /** Atalho: baixa a relação e devolve os atributos de uma NCM (null se a NCM não existir). */
    public function atributosDaNcm(Ncm|string $ncm): ?AtributosDaNcm
    {
        return $this->baixarPorNcm()->atributosDaNcm($ncm);
    }
}
