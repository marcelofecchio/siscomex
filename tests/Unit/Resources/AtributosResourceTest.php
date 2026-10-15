<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Response;
use MFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class AtributosResourceTest extends TestCase
{
    public function testPorNcmMontaUrlEQuery(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, []));

        $this->client()->atributos()->porNcm(
            '8473.30.49',
            new \DateTimeImmutable('2026-10-15'),
            Modalidade::Importacao,
            [ObjetivoAtributo::Produto, ObjetivoAtributo::Duimp],
            ['ANVISA'],
        );

        $uri = $this->http->lastRequest()->getUri();
        self::assertSame('/cadatributos/api/ext/atributo-ncm/84733049', $uri->getPath());
        self::assertSame('data=2026-10-15&modalidade=IMPORTACAO&objetivos=PRODUTO&objetivos=DUIMP&orgaosDemandantes=ANVISA', $uri->getQuery());
    }

    public function testPorNcmInterpretaEstruturaDoArquivoDiario(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'atributos-por-ncm-amostra.json')));

        $atributos = $this->client()->atributos()->porNcm('84733049');

        self::assertSame('84733049', $atributos->ncm->codigo);
        self::assertSame('409', $atributos->versao);
        self::assertCount(13, $atributos);
        $anvisa = $atributos->porCodigo('ATT_18551');
        self::assertNotNull($anvisa);
        self::assertSame('Categoria de produto - Catálogo Anvisa', $anvisa->nomeApresentacao);
        self::assertSame(FormaPreenchimento::ListaEstatica, $anvisa->formaPreenchimento);
        self::assertSame(Modalidade::Importacao, $anvisa->modalidade);
        self::assertTrue($anvisa->obrigatorio);
        self::assertTrue($anvisa->temObjetivo(ObjetivoAtributo::Produto));
        self::assertTrue($anvisa->valorDominioValido('99'));
        self::assertFalse($anvisa->valorDominioValido('XX'));
        self::assertTrue($anvisa->atributoCondicionante);
        self::assertNotEmpty($anvisa->condicionados);
        self::assertSame('==', $anvisa->condicionados[0]->condicao?->operador);
    }

    public function testPorNcmComRespostaRealDeProducao(): void
    {
        // Resposta real de GET /cadatributos/api/ext/atributo-ncm/84733049?modalidade=IMPORTACAO (15/10/2026)
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('producao', 'atributo-ncm-84733049.json')));

        $atributos = $this->client()->atributos()->porNcm('84733049', modalidade: Modalidade::Importacao);

        self::assertCount(10, $atributos);
        self::assertNull($atributos->versao);
        $serie = $atributos->porCodigo('ATT_10888');
        self::assertNotNull($serie);
        self::assertTrue($serie->multivalorado, 'vínculo da NCM prevalece');
        self::assertSame(['ATT_14545'], $atributos->obrigatoriosParaProduto(Modalidade::Importacao, new \DateTimeImmutable('2026-10-15'))->codigos());
    }

    public function testPorNcmInterpretaListaDeAtributos(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, [
            ['codigo' => 'ATT_1', 'nomeApresentacao' => 'Peso', 'formaPreenchimento' => 'NUMERO_REAL', 'modalidade' => 'Exportação', 'obrigatorio' => true, 'multivalorado' => false, 'dataInicioVigencia' => '2019-01-01', 'atributoCondicionante' => false],
        ]));

        $atributos = $this->client()->atributos()->porNcm('02011000');

        self::assertSame(['ATT_1'], $atributos->codigos());
        self::assertSame(Modalidade::Exportacao, $atributos->atributos[0]->modalidade);
        self::assertSame(FormaPreenchimento::NumeroReal, $atributos->atributos[0]->formaPreenchimento);
    }

    public function testPorNcmInterpretaObjetoComListaAtributos(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, ['codigoNcm' => '0201.10.00', 'listaAtributos' => [['codigo' => 'ATT_2', 'nomeApresentacao' => 'X']]]));

        self::assertSame(['ATT_2'], $this->client()->atributos()->porNcm('02011000')->codigos());
    }

    public function testPorNcmRespostaVazia(): void
    {
        $this->http->queue(self::authResponse(), new Response(204));

        self::assertCount(0, $this->client()->atributos()->porNcm('02011000'));
    }

    public function testModalidadeAmbosRecusada(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->atributos()->porNcm('02011000', modalidade: Modalidade::Ambos);
    }

    public function testPorCodigosDivideEmLotesDe100(): void
    {
        $codigos = array_map(static fn(int $i): string => 'ATT_'.$i, range(1, 150));
        $this->http->queue(
            self::authResponse(),
            self::json(200, [['codigo' => 'ATT_1', 'nomeApresentacao' => 'A']]),
            self::json(200, [['codigo' => 'ATT_150', 'nomeApresentacao' => 'B']]),
        );

        $resultado = $this->client()->atributos()->porCodigos([...$codigos, 'ATT_1', ' '], new \DateTimeImmutable('2026-01-02'));

        self::assertSame(['ATT_1', 'ATT_150'], array_map(static fn($a): string => $a->codigo, $resultado));
        $primeiro = self::body($this->http->request(1));
        self::assertIsArray($primeiro['codigos']);
        self::assertCount(100, $primeiro['codigos']);
        self::assertSame('2026-01-02', $primeiro['data']);
        $segundo = self::body($this->http->request(2));
        self::assertIsArray($segundo['codigos']);
        self::assertCount(50, $segundo['codigos']);
        self::assertSame('/cadatributos/api/ext/atributo/consulta-codigo', $this->http->request(1)->getUri()->getPath());
    }

    public function testPorCodigoAceitaRespostaObjetoUnico(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, ['codigo' => 'ATT_7', 'nomeApresentacao' => 'Sete']));

        self::assertSame('Sete', $this->client()->atributos()->porCodigo('ATT_7')?->nomeApresentacao);
    }

    public function testPorCodigosVazioNaoFazRequisicao(): void
    {
        self::assertSame([], $this->client()->atributos()->porCodigos([]));
        self::assertSame([], $this->http->requests);
    }

    public function testPorNomes(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, [['codigo' => 'ATT_9', 'nome' => 'Peso']]));

        $resultado = $this->client()->atributos()->porNomes(['Peso']);

        self::assertSame('Peso', $resultado[0]->nome);
        self::assertSame('/cadatributos/api/ext/atributo/consulta-nome', $this->http->lastRequest()->getUri()->getPath());
        self::assertSame(['Peso'], self::body($this->http->lastRequest())['nomes']);
    }

    public function testDownloadPorNcmDescompactaZip(): void
    {
        $json = (string) file_get_contents(self::fixturePath('producao', 'atributos-por-ncm-amostra.json'));
        $this->http->queue(self::authResponse(), new Response(200, ['Content-Type' => 'application/zip'], self::zip('ATRIBUTOS_POR_NCM.json', $json)));

        $relacao = $this->client()->atributos()->downloadPorNcm(codigosNcm: ['84733049', '0201.10.00']);

        self::assertSame('359', $relacao->versao);
        self::assertTrue($relacao->possuiNcm('84733049'));
        self::assertSame('codigosNCM=84733049&codigosNCM=02011000', $this->http->lastRequest()->getUri()->getQuery());
        self::assertSame('/cadatributos/api/ext/atributo-ncm/download/json', $this->http->lastRequest()->getUri()->getPath());
    }

    public function testHistoricoVersoes(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, [[
            'numero' => 409,
            'dataVersao' => '2026-10-15',
            'alteracoes' => [
                'versao' => '409',
                'resumo' => 'Inclusão de atributos',
                'adicionado' => ['01011000'],
                'excluido' => [],
                'alterado' => [['codigoNcm' => '02011000', 'atributos' => []]],
            ],
        ]]));

        $historico = $this->client()->atributos()->historicoVersoesPorNcm([409]);

        self::assertSame(409, $historico[0]->numero);
        self::assertSame('2026-10-15', $historico[0]->dataVersao?->format('Y-m-d'));
        self::assertSame(['01011000'], $historico[0]->adicionados);
        self::assertSame(['02011000'], $historico[0]->alterados);
        self::assertSame('versao=409', $this->http->lastRequest()->getUri()->getQuery());
    }

    public function testHistoricoVersoesAtributos(): void
    {
        $this->http->queue(self::authResponse(), new Response(204));

        self::assertSame([], $this->client()->atributos()->historicoVersoesAtributos());
        self::assertSame('/cadatributos/api/ext/atributo/historico-versoes', $this->http->lastRequest()->getUri()->getPath());
    }
}
