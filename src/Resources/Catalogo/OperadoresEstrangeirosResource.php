<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo;

use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\EventoHistoricoOperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroOperadoresEstrangeiros;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ResultadoConsultaOperadoresEstrangeiros;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\Support\Versao;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CodigoPais;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CpfCnpjRaiz;

/**
 * Catálogo de Produtos (CATP) — operadores estrangeiros (fornecedores, fabricantes e produtores no exterior).
 *
 * Base: {ambiente}/catp/api. Spec: docs/api/specs/catp.json.
 * Um operador é identificado por (cpfCnpjRaiz, codigoPais, codigo) e versionado como o produto.
 */
final readonly class OperadoresEstrangeirosResource
{
    public const string PREFIXO = '/catp/api/ext/operador-estrangeiro';

    public const int MAX_CODIGO = 35;

    public function __construct(private Transport $transport) {}

    /** GET /ext/operador-estrangeiro — consulta operadores pelos filtros. */
    public function consultar(FiltroOperadoresEstrangeiros $filtro): ResultadoConsultaOperadoresEstrangeiros
    {
        $response = $this->transport->request('GET', self::PREFIXO, $filtro->toQuery());

        return new ResultadoConsultaOperadoresEstrangeiros(
            array_map(OperadorEstrangeiro::fromArray(...), Data::listFromResponse(Transport::decode($response))),
            $response->getStatusCode() === 206,
        );
    }

    /** GET /ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao} — detalha uma versão ou retificação. */
    public function detalhar(CpfCnpjRaiz|string $cpfCnpjRaiz, CodigoPais|string $codigoPais, string $codigo, string $versao): ?OperadorEstrangeiro
    {
        $dados = $this->transport->json('GET', self::path($cpfCnpjRaiz, $codigoPais, $codigo).'/'.Versao::path($versao));

        return $dados === null ? null : OperadorEstrangeiro::fromArray(Data::object($dados));
    }

    /**
     * GET /ext/operador-estrangeiro/historico/{cpfCnpjRaiz}/{codigoPais}/{codigo} — histórico de alterações.
     *
     * @return list<EventoHistoricoOperadorEstrangeiro>
     */
    public function historico(CpfCnpjRaiz|string $cpfCnpjRaiz, CodigoPais|string $codigoPais, string $codigo): array
    {
        $dados = $this->transport->json('GET', self::path($cpfCnpjRaiz, $codigoPais, $codigo, '/historico'));

        return array_map(EventoHistoricoOperadorEstrangeiro::fromArray(...), Data::listFromResponse($dados));
    }

    /**
     * POST /ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais} — inclui um operador (versão inicial).
     * Com $dataReferencia: POST .../{codigoPais}/{dataReferencia} (vigência retroativa).
     */
    public function incluir(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        CodigoPais|string $codigoPais,
        OperadorEstrangeiroRequest $operador,
        ?\DateTimeInterface $dataReferencia = null,
    ): OperadorEstrangeiro {
        $codigoPais = self::paisCompativel($codigoPais, $operador);
        $path = \sprintf('%s/%s/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $codigoPais);
        if ($dataReferencia !== null) {
            $path .= '/'.$dataReferencia->format('Y-m-d');
        }

        return $this->operadorDaResposta($this->transport->json('POST', $path, json: $operador->toArray()), 'incluir');
    }

    /**
     * PUT /ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo} — inclui nova versão.
     * Com $dataReferencia: POST .../{codigo}/{dataReferencia} (nova versão retroativa).
     */
    public function novaVersao(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        CodigoPais|string $codigoPais,
        string $codigo,
        OperadorEstrangeiroRequest $operador,
        ?\DateTimeInterface $dataReferencia = null,
    ): OperadorEstrangeiro {
        $path = self::path($cpfCnpjRaiz, self::paisCompativel($codigoPais, $operador), $codigo);
        $resposta = $dataReferencia === null
            ? $this->transport->json('PUT', $path, json: $operador->toArray())
            : $this->transport->json('POST', $path.'/'.$dataReferencia->format('Y-m-d'), json: $operador->toArray());

        return $this->operadorDaResposta($resposta, 'nova versão');
    }

    /** PUT /ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao} — retifica uma versão. */
    public function retificar(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        CodigoPais|string $codigoPais,
        string $codigo,
        string $versao,
        OperadorEstrangeiroRequest $operador,
    ): OperadorEstrangeiro {
        $path = self::path($cpfCnpjRaiz, self::paisCompativel($codigoPais, $operador), $codigo).'/'.Versao::path($versao);

        return $this->operadorDaResposta($this->transport->json('PUT', $path, json: $operador->toArray()), 'retificar');
    }

    /**
     * PUT /ext/operador-estrangeiro/desativar/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao} — desativa uma versão.
     * Retorna o operador desativado ou null (HTTP 204).
     */
    public function desativar(CpfCnpjRaiz|string $cpfCnpjRaiz, CodigoPais|string $codigoPais, string $codigo, string $versao): ?OperadorEstrangeiro
    {
        $dados = $this->transport->json('PUT', self::path($cpfCnpjRaiz, $codigoPais, $codigo, '/desativar').'/'.Versao::path($versao));

        return \is_array($dados) && $dados !== [] ? OperadorEstrangeiro::fromArray($dados) : null;
    }

    /**
     * GET /ext/operador-estrangeiro/exportar/{cpfCnpjRaiz}/{exibirDesativados} — exporta os operadores.
     *
     * A spec indica uma lista JSON; a exportação de produtos vem em ZIP — aceitamos os dois.
     * Na Validação (17/10/2026) a exportação voltou vazia (204) logo após a inclusão, enquanto
     * consultar() já encontrava o operador. TODO(confirmar) se a exportação é gerada D+1.
     *
     * @param list<string> $codigosOperador
     *
     * @return list<OperadorEstrangeiro>
     */
    public function exportar(
        CpfCnpjRaiz|string $cpfCnpjRaiz,
        bool $exibirDesativados = false,
        array $codigosOperador = [],
        ?\DateTimeInterface $inicioVigenciaDe = null,
        ?\DateTimeInterface $inicioVigenciaAte = null,
    ): array {
        $path = \sprintf('%s/exportar/%s/%s', self::PREFIXO, CpfCnpjRaiz::of($cpfCnpjRaiz), $exibirDesativados ? 'true' : 'false');
        $response = $this->transport->request('GET', $path, [
            'codigosOperador' => $codigosOperador,
            'inicioVigenciaDe' => Data::formatDate($inicioVigenciaDe),
            'inicioVigenciaAte' => Data::formatDate($inicioVigenciaAte),
        ], headers: ['Accept' => 'application/json, application/zip']);

        return array_map(OperadorEstrangeiro::fromArray(...), Data::listFromResponse(Transport::decodeArquivo($response)));
    }

    /** Monta {prefixo}{acao}/{cpfCnpjRaiz}/{codigoPais}/{codigo}. */
    private static function path(CpfCnpjRaiz|string $cpfCnpjRaiz, CodigoPais|string $codigoPais, string $codigo, string $acao = ''): string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || \strlen($codigo) > self::MAX_CODIGO) {
            throw new \InvalidArgumentException(\sprintf('Código do operador estrangeiro deve ter entre 1 e %d caracteres.', self::MAX_CODIGO));
        }

        return \sprintf('%s%s/%s/%s/%s', self::PREFIXO, $acao, CpfCnpjRaiz::of($cpfCnpjRaiz), CodigoPais::of($codigoPais), rawurlencode($codigo));
    }

    /** A subdivisão ISO 3166-2 sempre começa pelo código do país (ex.: CN-GD → CN). */
    private static function paisCompativel(CodigoPais|string $codigoPais, OperadorEstrangeiroRequest $operador): CodigoPais
    {
        $codigoPais = CodigoPais::of($codigoPais);
        $subdivisao = $operador->codigoSubdivisaoPais;
        if ($subdivisao !== null && !str_starts_with($subdivisao, $codigoPais->codigo.'-')) {
            throw new \InvalidArgumentException(\sprintf('A subdivisão %s não pertence ao país %s.', $subdivisao, $codigoPais));
        }

        return $codigoPais;
    }

    private function operadorDaResposta(mixed $dados, string $operacao): OperadorEstrangeiro
    {
        if (!\is_array($dados) || $dados === []) {
            throw new SiscomexException(\sprintf('O portal não retornou o operador estrangeiro na operação "%s".', $operacao));
        }

        return OperadorEstrangeiro::fromArray($dados);
    }
}
