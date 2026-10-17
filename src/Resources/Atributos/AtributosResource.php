<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos;

use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\Atributo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributosDaNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\RelacaoAtributosNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\VersaoRelacao;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;
use MarceloFecchio\Siscomex\PortalUnico\Support\SystemClock;
use MarceloFecchio\Siscomex\PortalUnico\Support\Zip;
use MarceloFecchio\Siscomex\PortalUnico\ValueObject\Ncm;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Cadastro de Atributos (CADA) — serviços autenticados.
 *
 * Base: {ambiente}/cadatributos/api. Spec: docs/api/specs/cadatributos.json.
 * Os códigos de atributos diferem entre Validação e Produção: nunca fixe códigos no código.
 */
final readonly class AtributosResource
{
    public const string PREFIXO = '/cadatributos/api';

    /** Limite de códigos/nomes por chamada de consulta-codigo/consulta-nome. */
    public const int MAX_POR_CONSULTA = 100;

    /**
     * @param CacheInterface|null $cache cache opcional das respostas de porNcm(); as entradas expiram
     *                                   à meia-noite (America/Sao_Paulo), quando o portal atualiza a relação
     */
    public function __construct(
        private Transport $transport,
        private ?CacheInterface $cache = null,
        private ClockInterface $clock = new SystemClock(),
    ) {}

    /**
     * GET /ext/atributo-ncm/{codigo-ncm} — atributos vinculados a uma NCM.
     *
     * @param list<ObjetivoAtributo> $objetivos
     * @param list<string>           $orgaosDemandantes
     */
    public function porNcm(
        Ncm|string $ncm,
        ?\DateTimeInterface $data = null,
        ?Modalidade $modalidade = null,
        array $objetivos = [],
        array $orgaosDemandantes = [],
    ): AtributosDaNcm {
        $ncm = Ncm::of($ncm);
        self::validarModalidade($modalidade);

        $path = self::PREFIXO.'/ext/atributo-ncm/'.$ncm->codigo;
        $query = [
            'data' => Data::formatDate($data),
            'modalidade' => $modalidade?->value,
            'objetivos' => array_map(static fn(ObjetivoAtributo $o): string => $o->value, $objetivos),
            'orgaosDemandantes' => $orgaosDemandantes,
        ];

        return AtributosDaNcm::fromResponse($ncm, $this->jsonEmCache($path, $query));
    }

    /**
     * POST /ext/atributo/consulta-codigo — dados de atributos pelo código (lotes de até 100).
     *
     * @param list<string> $codigos
     *
     * @return list<Atributo>
     */
    public function porCodigos(array $codigos, ?\DateTimeInterface $data = null): array
    {
        return $this->consultarEmLotes('/ext/atributo/consulta-codigo', 'codigos', $codigos, $data);
    }

    /**
     * POST /ext/atributo/consulta-nome — dados de atributos pelo nome (lotes de até 100).
     *
     * @param list<string> $nomes
     *
     * @return list<Atributo>
     */
    public function porNomes(array $nomes, ?\DateTimeInterface $data = null): array
    {
        return $this->consultarEmLotes('/ext/atributo/consulta-nome', 'nomes', $nomes, $data);
    }

    public function porCodigo(string $codigo, ?\DateTimeInterface $data = null): ?Atributo
    {
        foreach ($this->porCodigos([$codigo], $data) as $atributo) {
            if ($atributo->codigo === $codigo) {
                return $atributo;
            }
        }

        return null;
    }

    /**
     * GET /ext/atributo-ncm/download/json — relação de atributos por NCM (ZIP com JSON).
     *
     * @param list<ObjetivoAtributo> $objetivos
     * @param list<string>           $orgaosDemandantes
     * @param list<Ncm|string>       $codigosNcm
     */
    public function downloadPorNcm(
        ?\DateTimeInterface $data = null,
        ?Modalidade $modalidade = null,
        array $objetivos = [],
        array $orgaosDemandantes = [],
        array $codigosNcm = [],
    ): RelacaoAtributosNcm {
        self::validarModalidade($modalidade);

        $response = $this->transport->request('GET', self::PREFIXO.'/ext/atributo-ncm/download/json', [
            'data' => Data::formatDate($data),
            'modalidade' => $modalidade?->value,
            'objetivos' => array_map(static fn(ObjetivoAtributo $o): string => $o->value, $objetivos),
            'orgaosDemandantes' => $orgaosDemandantes,
            'codigosNCM' => array_map(static fn(Ncm|string $n): string => Ncm::of($n)->codigo, $codigosNcm),
        ], headers: ['Accept' => 'application/zip, application/json']);

        return RelacaoAtributosNcm::fromJson(Zip::primeiroArquivo((string) $response->getBody()));
    }

    /**
     * GET /ext/atributo-ncm/historico-versoes — histórico de versões da relação de atributos por NCM.
     *
     * @param list<int> $versoes vazio = todas
     *
     * @return list<VersaoRelacao>
     */
    public function historicoVersoesPorNcm(array $versoes = []): array
    {
        return $this->historico('/ext/atributo-ncm/historico-versoes', $versoes);
    }

    /**
     * GET /ext/atributo/historico-versoes — histórico de versões da relação de atributos.
     *
     * @param list<int> $versoes vazio = todas
     *
     * @return list<VersaoRelacao>
     */
    public function historicoVersoesAtributos(array $versoes = []): array
    {
        return $this->historico('/ext/atributo/historico-versoes', $versoes);
    }

    /**
     * @param list<string> $valores
     *
     * @return list<Atributo>
     */
    private function consultarEmLotes(string $path, string $campo, array $valores, ?\DateTimeInterface $data): array
    {
        $valores = array_values(array_unique(array_filter(array_map(trim(...), $valores), static fn(string $v): bool => $v !== '')));
        if ($valores === []) {
            return [];
        }
        $data ??= new \DateTimeImmutable('now', new \DateTimeZone(Data::TIMEZONE));

        $resultado = [];
        foreach (array_chunk($valores, self::MAX_POR_CONSULTA) as $lote) {
            $resposta = $this->transport->json('POST', self::PREFIXO.$path, json: [
                $campo => $lote,
                'data' => $data->format('Y-m-d'),
            ], readOnly: true);
            foreach (Data::listFromResponse($resposta) as $item) {
                $resultado[] = Atributo::fromArray($item);
            }
        }

        return $resultado;
    }

    /**
     * @param list<int> $versoes
     *
     * @return list<VersaoRelacao>
     */
    private function historico(string $path, array $versoes): array
    {
        $resposta = $this->transport->json('GET', self::PREFIXO.$path, ['versao' => $versoes]);

        return array_map(VersaoRelacao::fromArray(...), Data::listFromResponse($resposta));
    }

    /** @param array<string, scalar|list<scalar>|null> $query */
    private function jsonEmCache(string $path, array $query): mixed
    {
        if ($this->cache === null) {
            return $this->transport->json('GET', $path, $query);
        }

        $chave = 'siscomex_atributos_'.hash('sha256', $this->transport->config()->ambiente->value.'|'.$path.Transport::buildQuery($query));
        $emCache = $this->cache->get($chave);
        if (\is_array($emCache)) {
            return $emCache;
        }

        $resposta = $this->transport->json('GET', $path, $query);
        if (\is_array($resposta)) {
            $tz = new \DateTimeZone(Data::TIMEZONE);
            $agora = $this->clock->now()->setTimezone($tz);
            $this->cache->set($chave, $resposta, max(60, $agora->modify('tomorrow')->getTimestamp() - $agora->getTimestamp()));
        }

        return $resposta;
    }

    private static function validarModalidade(?Modalidade $modalidade): void
    {
        if ($modalidade === Modalidade::Ambos) {
            throw new \InvalidArgumentException('O Cadastro de Atributos aceita apenas IMPORTACAO ou EXPORTACAO; omita a modalidade para obter ambas.');
        }
    }
}
