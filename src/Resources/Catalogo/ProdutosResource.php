<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo;

use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\EventoHistoricoProduto;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroProdutos;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Produto;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoConsultaProdutos;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\Support\Zip;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CpfCnpjRaiz;

/**
 * Catálogo de Produtos (CATP) — produtos.
 *
 * Base: {ambiente}/catp/api. Spec: docs/api/specs/catp.json.
 * Os atributos informados devem usar os códigos do ambiente em uso (Validação ≠ Produção)
 * e apenas atributos com objetivo "Produto".
 */
final readonly class ProdutosResource
{
    public const string PREFIXO = '/catp/api/ext/produto';

    public function __construct(private Transport $transport) {}

    /** GET /ext/produto — consulta produtos pelos filtros. */
    public function consultar(FiltroProdutos $filtro): ResultadoConsultaProdutos
    {
        $response = $this->transport->request('GET', self::PREFIXO, $filtro->toQuery());
        $dados = Transport::decode($response);

        return new ResultadoConsultaProdutos(
            array_map(Produto::fromArray(...), Data::listFromResponse($dados)),
            $response->getStatusCode() === 206,
        );
    }

    /**
     * GET /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao} — detalha uma versão
     * ("1") ou uma retificação ("1.1") do produto.
     */
    public function detalhar(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao): ?Produto
    {
        $dados = $this->transport->json('GET', \sprintf('%s/%s/%d/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo, rawurlencode(self::versao($versao))));

        return $dados === null ? null : Produto::fromArray(Data::object($dados));
    }

    /**
     * GET /ext/produto/historico/{cpfCnpjRaiz}/{codigo} — histórico de alterações.
     *
     * @return list<EventoHistoricoProduto>
     */
    public function historico(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo): array
    {
        $dados = $this->transport->json('GET', \sprintf('%s/historico/%s/%d', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo));

        return array_map(EventoHistoricoProduto::fromArray(...), Data::listFromResponse($dados));
    }

    /**
     * POST /ext/produto/{cpfCnpjRaiz} — inclui um produto (versão inicial).
     * Com $dataReferencia: POST /ext/produto/{cpfCnpjRaiz}/{dataReferencia} (vigência retroativa).
     */
    public function incluir(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        ProdutoRequest $produto,
        ?\DateTimeInterface $dataReferencia = null,
    ): Produto {
        $path = \sprintf('%s/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz));
        if ($dataReferencia !== null) {
            $path .= '/'.$dataReferencia->format('Y-m-d');
        }

        return $this->produtoDaResposta($this->transport->json('POST', $path, json: $produto->toArray()), 'incluir');
    }

    /** PUT /ext/produto/{cpfCnpjRaiz}/{codigo} — inclui nova versão do produto. */
    public function novaVersao(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, ProdutoRequest $produto): Produto
    {
        $path = \sprintf('%s/%s/%d', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo);

        return $this->produtoDaResposta($this->transport->json('PUT', $path, json: $produto->toArray()), 'nova versão');
    }

    /** PUT /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao} — retifica uma versão do produto. */
    public function retificar(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao, ProdutoRequest $produto): Produto
    {
        $path = \sprintf('%s/%s/%d/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo, rawurlencode(self::versao($versao)));

        return $this->produtoDaResposta($this->transport->json('PUT', $path, json: $produto->toArray()), 'retificar');
    }

    /**
     * PUT /ext/produto/desativar/{cpfCnpjRaiz}/{codigo} — desativa a versão mais recente.
     * Retorna o produto desativado ou null (HTTP 204).
     */
    public function desativar(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo): ?Produto
    {
        $dados = $this->transport->json('PUT', \sprintf('%s/desativar/%s/%d', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo));

        return \is_array($dados) && $dados !== [] ? Produto::fromArray($dados) : null;
    }

    /** POST /ext/produto/codigo-interno/{cpfCnpjRaiz}/{codigo}/{versao} — inclui código interno. */
    public function incluirCodigoInterno(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao, string $codigoInterno): void
    {
        $this->codigoInterno('POST', $cpfCnpjRaiz, $codigo, $versao, $codigoInterno);
    }

    /** DELETE /ext/produto/codigo-interno/{cpfCnpjRaiz}/{codigo}/{versao} — remove código interno. */
    public function removerCodigoInterno(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao, string $codigoInterno): void
    {
        $this->codigoInterno('DELETE', $cpfCnpjRaiz, $codigo, $versao, $codigoInterno);
    }

    /**
     * GET /ext/produto/exportar/{cpfCnpjRaiz}/{exibirDesativados} — exporta o catálogo (ZIP com JSON).
     *
     * @param list<int> $codigosProduto
     *
     * @return list<Produto>
     */
    public function exportar(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        bool $exibirDesativados = false,
        array $codigosProduto = [],
        ?\DateTimeInterface $inicioVigenciaDe = null,
        ?\DateTimeInterface $inicioVigenciaAte = null,
    ): array {
        $path = \sprintf('%s/exportar/%s/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $exibirDesativados ? 'true' : 'false');
        $response = $this->transport->request('GET', $path, [
            'codigosProduto' => $codigosProduto,
            'inicioVigenciaDe' => Data::formatDate($inicioVigenciaDe),
            'inicioVigenciaAte' => Data::formatDate($inicioVigenciaAte),
        ], headers: ['Accept' => 'application/zip, application/json']);

        if ($response->getStatusCode() === 204) {
            return [];
        }
        $json = Zip::primeiroArquivo((string) $response->getBody());
        $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

        return array_map(Produto::fromArray(...), Data::listFromResponse($dados));
    }

    private function codigoInterno(string $method, CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao, string $codigoInterno): void
    {
        $path = \sprintf('%s/codigo-interno/%s/%d/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo, rawurlencode(self::versao($versao)));
        $this->transport->request($method, $path, json: ['codigoInterno' => $codigoInterno]);
    }

    private function produtoDaResposta(mixed $dados, string $operacao): Produto
    {
        if (!\is_array($dados) || $dados === []) {
            throw new SiscomexException(\sprintf('O portal não retornou o produto na operação "%s".', $operacao));
        }

        return Produto::fromArray($dados);
    }

    private static function versao(string $versao): string
    {
        $versao = trim($versao);
        if (preg_match('/^\d+(\.\d+)?$/', $versao) !== 1 || \strlen($versao) > 8) {
            throw new \InvalidArgumentException(\sprintf('Versão inválida: "%s". Use "1" ou uma retificação "1.1".', $versao));
        }

        return $versao;
    }
}
