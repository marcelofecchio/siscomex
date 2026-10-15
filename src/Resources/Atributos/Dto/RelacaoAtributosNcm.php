<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;
use MFecchio\Siscomex\PortalUnico\ValueObject\Ncm;

/**
 * Relação completa de atributos por NCM (arquivo diário ATRIBUTOS_POR_NCM_AAAA_MM_DD.json):
 * {versao, listaNcm: [{codigoNcm, listaAtributos: [vínculos]}], detalhesAtributos: [atributos]}.
 *
 * O arquivo completo tem ~10 mil NCMs e ~15 MB; para caber em memory_limit=128M os DTOs
 * são criados sob demanda (e reaproveitados) em vez de todos de uma vez.
 */
final class RelacaoAtributosNcm
{
    /** @var array<string, Atributo> */
    private array $cacheAtributos = [];

    /**
     * @param array<string, list<array<mixed>>> $vinculosBrutos chave = NCM com 8 dígitos
     * @param array<string, array<mixed>>       $atributosBrutos chave = código do atributo
     */
    private function __construct(
        public readonly ?string $versao,
        private readonly array $vinculosBrutos,
        private readonly array $atributosBrutos,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $vinculos = [];
        foreach (Data::listOfArrays($a, 'listaNcm') as $item) {
            $codigo = preg_replace('/\D/', '', Data::string($item, 'codigoNcm')) ?? '';
            if ($codigo !== '') {
                $vinculos[$codigo] = Data::listOfArrays($item, 'listaAtributos');
            }
        }

        $atributos = [];
        foreach (Data::listOfArrays($a, 'detalhesAtributos') as $item) {
            $codigo = Data::string($item, 'codigo');
            if ($codigo !== '') {
                $atributos[$codigo] = $item;
            }
        }

        return new self(Data::stringOrNull($a, 'versao'), $vinculos, $atributos);
    }

    public static function fromJson(string $json): self
    {
        $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray(\is_array($dados) ? $dados : []);
    }

    public function possuiNcm(Ncm|string $ncm): bool
    {
        return isset($this->vinculosBrutos[Ncm::of($ncm)->codigo]);
    }

    /** @return list<string> NCMs (8 dígitos) presentes na relação */
    public function ncms(): array
    {
        return array_map(strval(...), array_keys($this->vinculosBrutos));
    }

    /** @return list<string> */
    public function codigosAtributos(): array
    {
        return array_map(strval(...), array_keys($this->atributosBrutos));
    }

    public function atributo(string $codigo): ?Atributo
    {
        if (isset($this->cacheAtributos[$codigo])) {
            return $this->cacheAtributos[$codigo];
        }
        $bruto = $this->atributosBrutos[$codigo] ?? null;

        return $bruto === null ? null : ($this->cacheAtributos[$codigo] = Atributo::fromArray($bruto));
    }

    /**
     * Atributos cujo nome de apresentação ou nome contém o texto (sem diferenciar maiúsculas).
     * Útil para descobrir o código de um atributo em cada ambiente.
     *
     * @return list<Atributo>
     */
    public function buscarAtributosPorNome(string $texto): array
    {
        $resultado = [];
        foreach ($this->atributosBrutos as $codigo => $bruto) {
            $nomes = Data::string($bruto, 'nomeApresentacao').' '.Data::string($bruto, 'nome');
            if (mb_stripos($nomes, $texto) !== false) {
                $atributo = $this->atributo((string) $codigo);
                if ($atributo !== null) {
                    $resultado[] = $atributo;
                }
            }
        }

        return $resultado;
    }

    /** @return list<VinculoAtributoNcm> */
    public function vinculos(Ncm|string $ncm): array
    {
        return array_map(VinculoAtributoNcm::fromArray(...), $this->vinculosBrutos[Ncm::of($ncm)->codigo] ?? []);
    }

    /**
     * Atributos da NCM combinados com seus detalhes; null se a NCM não constar da relação.
     */
    public function atributosDaNcm(Ncm|string $ncm): ?AtributosDaNcm
    {
        $ncm = Ncm::of($ncm);
        if (!isset($this->vinculosBrutos[$ncm->codigo])) {
            return null;
        }

        $atributos = [];
        foreach ($this->vinculos($ncm) as $vinculo) {
            $detalhe = $this->atributo($vinculo->codigo) ?? new Atributo($vinculo->codigo);
            $atributos[] = $detalhe->comVinculo($vinculo);
        }

        return new AtributosDaNcm($ncm, $atributos, $this->versao);
    }
}
