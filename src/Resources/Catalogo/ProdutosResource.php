<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo;

use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ConsultaCodigoInterno;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\EventoHistoricoProduto;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Fabricante;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroProdutos;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Produto;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoComFabricantes;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoConsultaProdutos;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoInclusaoComFabricantes;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoLote;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Support\ClassificacaoErro;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\Support\Versao;
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

    private FabricantesResource $fabricantes;

    public function __construct(private Transport $transport, ?FabricantesResource $fabricantes = null)
    {
        $this->fabricantes = $fabricantes ?? new FabricantesResource($transport);
    }

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
     * Produtos que usam exatamente o código interno informado, em qualquer situação.
     *
     * O filtro `codigoInterno` do portal é parcial e não diferencia maiúsculas ("SKU" encontra
     * "sku-1"); aqui o resultado é restrito à igualdade exata. A consulta sem `situacao` já
     * inclui os desativados. Veja SituacaoCodigoInterno para as regras de reutilização.
     */
    public function buscarPorCodigoInterno(CpfCnpjRaiz|string $cpfCnpjRaiz, string $codigoInterno): ConsultaCodigoInterno
    {
        $codigoInterno = trim($codigoInterno);
        if ($codigoInterno === '') {
            throw new \InvalidArgumentException('Informe o código interno a buscar.');
        }
        $resultado = $this->consultar(new FiltroProdutos($cpfCnpjRaiz, codigoInterno: $codigoInterno));

        return new ConsultaCodigoInterno(
            $codigoInterno,
            array_values(array_filter($resultado->produtos, static fn(Produto $p): bool => \in_array($codigoInterno, $p->codigosInterno, true))),
            $resultado->parcial,
        );
    }

    /**
     * GET /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao} — detalha uma versão
     * ("1") ou uma retificação ("1.1") do produto.
     */
    public function detalhar(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao): ?Produto
    {
        $dados = $this->transport->json('GET', \sprintf('%s/%s/%d/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo, Versao::path($versao)));

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

    /**
     * Inclui o produto e, em seguida, vincula os fabricantes — a sequência que todo integrador
     * repete, já que o vínculo exige o código do produto.
     *
     * Erros na inclusão do produto são lançados como em incluir(). Depois que o produto existe,
     * nada é lançado: cada vínculo tem seu status e o resultado diz se ficou Completo ou
     * ProdutoSemVinculo. Nesse caso, reprocesse só os vínculos:
     * `fabricantes()->vincularTodos($cnpj, $resultado->paraReprocessar())`.
     *
     * @experimental
     *
     * @param list<Fabricante> $fabricantes
     */
    public function incluirComFabricantes(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        ProdutoRequest $produto,
        array $fabricantes,
        ?\DateTimeInterface $dataReferencia = null,
    ): ResultadoInclusaoComFabricantes {
        $incluido = $this->incluir($cpfCnpjRaiz, $produto, $dataReferencia);

        return new ResultadoInclusaoComFabricantes($incluido, $this->vincularAoProduto($cpfCnpjRaiz, $incluido, $fabricantes));
    }

    /**
     * Inclui vários produtos em sequência, sem repetir automaticamente nenhum envio.
     *
     * - Recusa do portal ao item (HTTP 400/404/422): registra Erro e segue para o próximo.
     * - PUCX-ER1001 (rate limit), 503 após os retries do Transport, 401/403: interrompe; o item
     *   atual e os seguintes ficam Pendente (veja ResultadoLote::retomarApos()).
     * - Falha de rede durante o POST: o item fica Incerto (pode ter sido incluído) e o lote para.
     * - Demais erros HTTP (ex.: 500): registra Erro e segue.
     *
     * Itens ProdutoComFabricantes têm os fabricantes vinculados logo após a inclusão; se algum
     * vínculo falhar o item fica IncluidoSemVinculo (reprocesse com
     * `fabricantes()->vincularTodos($cnpj, $resultado->vinculosParaReprocessar())`). Rate limit
     * ou falha de rede num vínculo também interrompem o lote.
     *
     * O callback recebe cada item processado; devolver false interrompe o lote (qualquer outro
     * retorno, inclusive void, continua).
     *
     * @experimental
     *
     * @param iterable<int|string, ProdutoRequest|ProdutoComFabricantes> $produtos    a chave identifica o item (ex.: linha da planilha)
     * @param (\Closure(ItemLote): mixed)|null                            $aoProgredir
     */
    public function incluirLote(CpfCnpjRaiz|string $cpfCnpjRaiz, iterable $produtos, ?\Closure $aoProgredir = null): ResultadoLote
    {
        $cpfCnpjRaiz = CpfCnpjRaiz::of($cpfCnpjRaiz);
        $itens = [];
        $interrupcao = null;

        foreach ($produtos as $chave => $entrada) {
            [$request, $fabricantes] = $entrada instanceof ProdutoComFabricantes ? [$entrada->produto, $entrada->fabricantes] : [$entrada, []];
            if ($interrupcao !== null) {
                $itens[] = new ItemLote($chave, $request, StatusItemLote::Pendente, fabricantes: $fabricantes);
                continue;
            }

            try {
                $produto = $this->incluir($cpfCnpjRaiz, $request);
                $vinculos = $this->vincularAoProduto($cpfCnpjRaiz, $produto, $fabricantes);
                $semVinculo = array_filter($vinculos, static fn(ResultadoVinculo $v): bool => !$v->vinculado()) !== [];
                $item = new ItemLote($chave, $request, $semVinculo ? StatusItemLote::IncluidoSemVinculo : StatusItemLote::Incluido, $produto, fabricantes: $fabricantes, vinculos: $vinculos);
                $interrupcao = ResultadoInclusaoComFabricantes::interrupcaoEm($vinculos);
            } catch (TransportException $e) {
                $item = new ItemLote($chave, $request, StatusItemLote::Incerto, erro: $e, fabricantes: $fabricantes);
                $interrupcao = $e;
            } catch (SiscomexException $e) {
                if (ClassificacaoErro::interrompeSequencia($e)) {
                    $itens[] = new ItemLote($chave, $request, StatusItemLote::Pendente, erro: $e, fabricantes: $fabricantes);
                    $interrupcao = $e;
                    continue;
                }
                $item = new ItemLote($chave, $request, StatusItemLote::Erro, erro: $e, fabricantes: $fabricantes);
            }

            $itens[] = $item;
            if ($aoProgredir !== null && $aoProgredir($item) === false && $interrupcao === null) {
                $interrupcao = new SiscomexException('Lote interrompido pelo callback de progresso.');
            }
        }

        return new ResultadoLote($itens, $interrupcao);
    }

    /**
     * PUT /ext/produto/{cpfCnpjRaiz}/{codigo} — inclui nova versão do produto.
     *
     * Também serve para reativar um produto desativado: a nova versão nasce ativa (Validação,
     * 17/10/2026). A versão substitui a anterior por inteiro, inclusive `codigosInterno`.
     */
    public function novaVersao(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, ProdutoRequest $produto): Produto
    {
        $path = \sprintf('%s/%s/%d', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo);

        return $this->produtoDaResposta($this->transport->json('PUT', $path, json: $produto->toArray()), 'nova versão');
    }

    /** PUT /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao} — retifica uma versão do produto. */
    public function retificar(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao, ProdutoRequest $produto): Produto
    {
        $path = \sprintf('%s/%s/%d/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo, Versao::path($versao));

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

        return array_map(Produto::fromArray(...), Data::listFromResponse(Transport::decodeArquivo($response)));
    }

    private function codigoInterno(string $method, CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigo, string $versao, string $codigoInterno): void
    {
        $path = \sprintf('%s/codigo-interno/%s/%d/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigo, Versao::path($versao));
        $this->transport->request($method, $path, json: ['codigoInterno' => $codigoInterno]);
    }

    /**
     * @param list<Fabricante> $fabricantes
     *
     * @return list<ResultadoVinculo>
     */
    private function vincularAoProduto(CpfCnpjRaiz|string $cpfCnpjRaiz, Produto $produto, array $fabricantes): array
    {
        if ($fabricantes === []) {
            return [];
        }
        if ($produto->codigo === null || $produto->codigo < 1) {
            // Só acontece com ClientConfig::$simularEscrita: o portal não gerou código.
            return array_map(static fn(Fabricante $f): ResultadoVinculo => new ResultadoVinculo($f, null, StatusVinculo::Pendente), $fabricantes);
        }
        $codigo = $produto->codigo;

        return $this->fabricantes->vincularTodos($cpfCnpjRaiz, array_map(static fn(Fabricante $f): FabricanteRequest => $f->paraProduto($codigo), $fabricantes));
    }

    private function produtoDaResposta(mixed $dados, string $operacao): Produto
    {
        if (!\is_array($dados) || $dados === []) {
            throw new SiscomexException(\sprintf('O portal não retornou o produto na operação "%s".', $operacao));
        }

        return Produto::fromArray($dados);
    }
}
