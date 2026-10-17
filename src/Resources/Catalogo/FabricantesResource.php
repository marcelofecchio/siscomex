<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo;

use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\EventoHistoricoFabricante;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoVinculo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\VinculoFabricante;
use MarceloFecchio\Siscomex\PortalUnico\Support\ClassificacaoErro;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CodigoPais;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CpfCnpjRaiz;

/**
 * Catálogo de Produtos (CATP) — vínculo de fabricantes/produtores a produtos.
 *
 * Base: {ambiente}/catp/api. Spec: docs/api/specs/catp.json.
 * O fabricante estrangeiro precisa existir antes como operador estrangeiro
 * (`catalogo()->operadoresEstrangeiros()->incluir()`).
 */
final readonly class FabricantesResource
{
    public const string PREFIXO = '/catp/api/ext/fabricante';

    public function __construct(private Transport $transport) {}

    /**
     * POST /ext/fabricante/{cpfCnpjRaiz} — vincula fabricante/produtor ao produto.
     * Retorna o vínculo criado ou null (HTTP 204).
     */
    public function vincular(CpfCnpjRaiz|string $cpfCnpjRaiz, FabricanteRequest $fabricante): ?VinculoFabricante
    {
        return self::vinculo($this->transport->json('POST', self::PREFIXO.'/'.CpfCnpjRaiz::of($cpfCnpjRaiz), json: $fabricante->toArray()));
    }

    /**
     * Vincula vários fabricantes em sequência, sem repetir nenhum envio — use também para
     * reprocessar `ResultadoInclusaoComFabricantes::paraReprocessar()`.
     *
     * Recusa do portal (4xx, 500) → Erro e segue; rate limit, 503 e 401/403 → o atual e os
     * seguintes ficam Pendente; falha de rede → Incerto e os seguintes Pendente.
     *
     * @experimental
     *
     * @param list<FabricanteRequest> $fabricantes
     *
     * @return list<ResultadoVinculo>
     */
    public function vincularTodos(CpfCnpjRaiz|string $cpfCnpjRaiz, array $fabricantes): array
    {
        $cpfCnpjRaiz = CpfCnpjRaiz::of($cpfCnpjRaiz);
        $resultados = [];
        $interrupcao = null;

        foreach ($fabricantes as $request) {
            if ($interrupcao !== null) {
                $resultados[] = new ResultadoVinculo($request->fabricante, $request->codigoProduto, StatusVinculo::Pendente);
                continue;
            }

            try {
                $resultados[] = new ResultadoVinculo($request->fabricante, $request->codigoProduto, StatusVinculo::Vinculado, $this->vincular($cpfCnpjRaiz, $request));
            } catch (TransportException $e) {
                $resultados[] = new ResultadoVinculo($request->fabricante, $request->codigoProduto, StatusVinculo::Incerto, erro: $e);
                $interrupcao = $e;
            } catch (SiscomexException $e) {
                $interrompe = ClassificacaoErro::interrompeSequencia($e);
                $resultados[] = new ResultadoVinculo($request->fabricante, $request->codigoProduto, $interrompe ? StatusVinculo::Pendente : StatusVinculo::Erro, erro: $e);
                $interrupcao = $interrompe ? $e : null;
            }
        }

        return $resultados;
    }

    /**
     * PUT /ext/fabricante/{cpfCnpjRaiz} — desvincula fabricante/produtor do produto.
     * Não é possível desvincular retroativamente. Retorna o vínculo removido ou null (HTTP 204).
     */
    public function desvincular(CpfCnpjRaiz|string $cpfCnpjRaiz, FabricanteRequest $fabricante): ?VinculoFabricante
    {
        return self::vinculo($this->transport->json('PUT', self::PREFIXO.'/'.CpfCnpjRaiz::of($cpfCnpjRaiz), json: $fabricante->toArray()));
    }

    /**
     * GET /ext/fabricante/exportar/{cpfCnpjRaiz} — vínculos de fabricantes, filtráveis por produto e país.
     *
     * A spec indica um único objeto; aceitamos também lista e ZIP. Na Validação (17/10/2026) a
     * exportação voltou vazia (204) logo após o vínculo, enquanto o histórico já o mostrava:
     * para o estado imediato, use historico(). TODO(confirmar) se a exportação é gerada D+1.
     *
     * @param list<int>               $codigosProduto
     * @param list<CodigoPais|string> $paisesOrigem
     *
     * @return list<VinculoFabricante>
     */
    public function exportar(CpfCnpjRaiz|string $cpfCnpjRaiz, array $codigosProduto = [], array $paisesOrigem = []): array
    {
        $response = $this->transport->request('GET', self::PREFIXO.'/exportar/'.CpfCnpjRaiz::of($cpfCnpjRaiz), [
            'codigosProduto' => $codigosProduto,
            'paisesOrigem' => array_map(static fn(CodigoPais|string $p): string => CodigoPais::of($p)->codigo, $paisesOrigem),
        ], headers: ['Accept' => 'application/json, application/zip']);

        return array_map(VinculoFabricante::fromArray(...), Data::listFromResponse(Transport::decodeArquivo($response)));
    }

    /**
     * GET /ext/fabricante/historico/{cpfCnpjRaiz}/{codigo} — histórico de vínculos do produto
     * (inclusões e exclusões, mais recentes primeiro).
     *
     * @return list<EventoHistoricoFabricante>
     */
    public function historico(CpfCnpjRaiz|string $cpfCnpjRaiz, int $codigoProduto): array
    {
        $dados = $this->transport->json('GET', \sprintf('%s/historico/%s/%d', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigoProduto));

        return array_map(EventoHistoricoFabricante::fromArray(...), Data::listFromResponse($dados));
    }

    private static function vinculo(mixed $dados): ?VinculoFabricante
    {
        return \is_array($dados) && $dados !== [] ? VinculoFabricante::fromArray($dados) : null;
    }
}
