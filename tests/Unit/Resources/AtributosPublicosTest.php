<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Response;
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\RelacaoAtributosNcm;
use MFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class AtributosPublicosTest extends TestCase
{
    public function testBaixaSemAutenticacaoEDescompacta(): void
    {
        $json = (string) file_get_contents(self::fixturePath('validacao', 'atributos-por-ncm-amostra.json'));
        $this->http->queue(new Response(200, ['Content-Type' => 'application/zip'], self::zip('ATRIBUTOS.json', $json)));

        $atributos = $this->client(semCredencial: true)->atributosPublicos()->atributosDaNcm('7318.15.00');

        $request = $this->http->lastRequest();
        self::assertSame('https://val.portalunico.siscomex.gov.br/cadatributos/api/atributo-ncm/download/json?perfil=PUBLICO', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertNotNull($atributos);
        self::assertContains('ATT_18551', $atributos->codigos());
    }

    public function testCodigosDiferemEntreAmbientes(): void
    {
        $prod = RelacaoAtributosNcm::fromArray(self::fixture('producao', 'atributos-por-ncm-amostra.json'));
        $val = RelacaoAtributosNcm::fromArray(self::fixture('validacao', 'atributos-por-ncm-amostra.json'));

        $nome = 'Categoria de produto - Catálogo Anvisa';
        $codigoProd = self::codigoPorNome($prod, $nome);
        $codigoVal = self::codigoPorNome($val, $nome);

        self::assertSame('ATT_14545', $codigoProd);
        self::assertSame('ATT_18551', $codigoVal);
    }

    public function testRelacaoCombinaVinculoComDetalhe(): void
    {
        $relacao = RelacaoAtributosNcm::fromArray(self::fixture('validacao', 'atributos-por-ncm-amostra.json'));

        self::assertSame(['02011000', '73181500', '84733049'], array_values(array_intersect(['02011000', '73181500', '84733049'], $relacao->ncms())));
        self::assertNull($relacao->atributosDaNcm('01011000'));
        self::assertNull($relacao->atributo('ATT_INEXISTENTE'));

        $ncm = $relacao->atributosDaNcm('84733049');
        self::assertNotNull($ncm);
        $obrigatorios = $ncm->obrigatoriosParaProduto(Modalidade::Importacao, new \DateTimeImmutable('2026-10-15'));
        self::assertSame(['ATT_5426', 'ATT_5333', 'ATT_7866', 'ATT_18551'], $obrigatorios->codigos());
        self::assertSame([], $ncm->obrigatoriosParaProduto(Modalidade::Exportacao, new \DateTimeImmutable('2026-10-15'))->codigos());
        self::assertSame(\count($ncm->atributos), iterator_count($ncm));
    }

    public function testRelacaoFromJsonInvalido(): void
    {
        $this->expectException(\JsonException::class);
        RelacaoAtributosNcm::fromJson('{x');
    }

    public function testListagensEVinculos(): void
    {
        $relacao = RelacaoAtributosNcm::fromArray(self::fixture('validacao', 'atributos-por-ncm-amostra.json'));

        self::assertContains('ATT_5333', $relacao->codigosAtributos());
        self::assertSame($relacao->atributo('ATT_5333'), $relacao->atributo('ATT_5333'), 'DTO reaproveitado');
        self::assertSame([], $relacao->buscarAtributosPorNome('inexistente xyz'));
        $vinculos = $relacao->vinculos('8473.30.49');
        self::assertCount(13, $vinculos);
        self::assertSame([], $relacao->vinculos('01011000'));
    }

    private static function codigoPorNome(RelacaoAtributosNcm $relacao, string $nome): ?string
    {
        foreach ($relacao->buscarAtributosPorNome($nome) as $atributo) {
            if ($atributo->nomeApresentacao === $nome) {
                return $atributo->codigo;
            }
        }

        return null;
    }
}
