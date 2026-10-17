<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas;

use MarceloFecchio\Siscomex\PortalUnico\Exceptions\NotFoundException;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\FiltroTabela;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\MetadadosTabela;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\PaginaTabela;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\Pais;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\RegistroTabela;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\Tabela;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Tabelas Comex (TABX) — somente consultas (as operações de inclusão/alteração/exclusão são
 * para os órgãos que mantêm as tabelas).
 *
 * Base: {ambiente}/tabx/api. Spec: docs/api/specs/tabx.json. Requer autenticação (o perfil
 * IMPEXP consegue ler — verificado na Validação em 17/10/2026). Tabelas observadas: PAIS, PORTO,
 * ORGAO_ANUENTE, ENQUADRAMENTO, TIPO_CONTEINER, entre outras; não há tabela de unidades de medida.
 *
 * @experimental
 */
final readonly class TabelasResource
{
    public const string PREFIXO = '/tabx/api/ext/tabela';

    public function __construct(private Transport $transport) {}

    /**
     * GET /ext/tabela — tabelas disponíveis.
     *
     * @return list<Tabela>
     */
    public function listar(): array
    {
        return array_map(Tabela::fromArray(...), Data::listFromResponse($this->transport->json('GET', self::PREFIXO)));
    }

    /** GET /ext/tabela/{nomeTabela}/metadado — campos, tipos e domínios. Tabela inexistente: 422 TABX-IN0003. */
    public function metadados(string $nomeTabela): MetadadosTabela
    {
        return MetadadosTabela::fromArray(Data::object($this->transport->json('GET', self::path($nomeTabela).'/metadado')));
    }

    /**
     * GET /ext/tabela/{nomeTabela} — uma página de registros (até 500; offset começa em 1).
     * 206 = há mais páginas (diferente do Catálogo, onde 206 é resultado truncado). O portal
     * responde 404 sem corpo quando nada atende ao filtro: devolvemos página vazia.
     */
    public function consultar(string $nomeTabela, ?FiltroTabela $filtro = null, ?int $offset = null): PaginaTabela
    {
        if ($offset !== null && $offset < 1) {
            throw new \InvalidArgumentException('O offset do Tabelas Comex começa em 1.');
        }

        try {
            $response = $this->transport->request('GET', self::path($nomeTabela), ($filtro ?? new FiltroTabela())->toQuery($offset), headers: ['Accept' => 'application/json']);
        } catch (NotFoundException $e) {
            if ($e->codigoErro === null) {
                return new PaginaTabela($nomeTabela, [], total: 0);
            }
            throw $e;
        }

        $dados = Data::object(Transport::decode($response));
        $faixa = preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*\/\s*(\d+)\s*$/', $response->getHeaderLine('Content-Range'), $m) === 1 ? $m : null;

        return new PaginaTabela(
            Data::string($dados, 'nomeTabela', $nomeTabela),
            array_map(RegistroTabela::fromArray(...), Data::listOfArrays($dados, 'dados')),
            $faixa === null ? null : (int) $faixa[1],
            $faixa === null ? null : (int) $faixa[2],
            $faixa === null ? null : (int) $faixa[3],
            $response->getStatusCode() === 206,
        );
    }

    /**
     * Percorre todas as páginas (uma requisição por página, sob demanda).
     *
     * @return \Generator<int, RegistroTabela>
     */
    public function todos(string $nomeTabela, ?FiltroTabela $filtro = null): \Generator
    {
        $offset = null;
        do {
            $pagina = $this->consultar($nomeTabela, $filtro, $offset);
            yield from $pagina->registros;
            $offset = $pagina->proximoOffset();
        } while ($offset !== null);
    }

    /**
     * Países da tabela PAIS (ISO 3166), ordenados por nome. Por padrão só os vigentes hoje.
     * Útil para selects de país do operador estrangeiro; guarde em cache (≈250 registros).
     *
     * @return list<Pais>
     */
    public function paises(bool $apenasVigentes = true): array
    {
        $hoje = new \DateTimeImmutable('now', new \DateTimeZone(Data::TIMEZONE));
        $paises = [];
        foreach ($this->todos('PAIS', new FiltroTabela()->ordenadoPor('NOME')) as $registro) {
            if ($registro->valor('SIGLA_ISO2') !== null && (!$apenasVigentes || $registro->vigenteEm($hoje))) {
                $paises[] = Pais::fromRegistro($registro);
            }
        }

        return $paises;
    }

    private static function path(string $nomeTabela): string
    {
        $nomeTabela = trim($nomeTabela);
        if ($nomeTabela === '' || \strlen($nomeTabela) > 50) {
            throw new \InvalidArgumentException('Nome da tabela deve ter entre 1 e 50 caracteres.');
        }

        return self::PREFIXO.'/'.rawurlencode($nomeTabela);
    }
}
