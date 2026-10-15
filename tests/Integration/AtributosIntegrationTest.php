<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Integration;

use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;

final class AtributosIntegrationTest extends IntegrationTestCase
{
    public function testAutenticaEReaproveitaSessao(): void
    {
        $s1 = $this->tolerandoIndisponibilidade(static fn() => self::client()->autenticar());
        $s2 = self::client()->autenticar();

        self::assertNotSame('', $s1->jwt);
        self::assertSame($s1->jwt, $s2->jwt, 'não deve autenticar de novo');
    }

    public function testConsultaAtributosPorNcm(): void
    {
        $ncm = self::env('SISCOMEX_NCM_TESTE', '84733049');

        $atributos = $this->tolerandoIndisponibilidade(static fn() => self::client()->atributos()->porNcm($ncm, modalidade: Modalidade::Importacao));

        self::assertSame($ncm, $atributos->ncm->codigo);
        self::assertGreaterThan(0, \count($atributos));
        foreach ($atributos as $atributo) {
            self::assertMatchesRegularExpression('/^ATT_\d+$/', $atributo->codigo);
        }
    }

    public function testConsultaAtributoPorCodigo(): void
    {
        $ncm = self::env('SISCOMEX_NCM_TESTE', '84733049');
        $primeiro = $this->tolerandoIndisponibilidade(static fn() => self::client()->atributos()->porNcm($ncm)->atributos[0] ?? null);
        self::assertNotNull($primeiro);

        $atributo = self::client()->atributos()->porCodigo($primeiro->codigo);

        self::assertNotNull($atributo);
        self::assertSame($primeiro->codigo, $atributo->codigo);
    }

    public function testDownloadPublico(): void
    {
        $ncm = self::env('SISCOMEX_NCM_TESTE', '84733049');

        $relacao = $this->tolerandoIndisponibilidade(static fn() => self::client()->atributosPublicos()->baixarPorNcm());

        self::assertNotNull($relacao->versao);
        self::assertTrue($relacao->possuiNcm($ncm));
    }
}
