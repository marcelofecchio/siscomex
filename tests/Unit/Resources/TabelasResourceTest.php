<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Response;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\FiltroTabela;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class TabelasResourceTest extends TestCase
{
    public function testListarEMetadados(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'tabx-tabelas.json')), self::json(200, self::fixture('validacao', 'tabx-pais-metadado.json')));
        $tabelas = $this->client()->tabelas();

        $lista = $tabelas->listar();
        self::assertContains('PAIS', array_map(static fn($t) => $t->nome, $lista));

        $pais = $tabelas->metadados('PAIS');
        self::assertSame('/tabx/api/ext/tabela/PAIS/metadado', $this->http->lastRequest()->getUri()->getPath());
        self::assertSame(['SIGLA_ISO2'], $pais->chaveNegocio());
        self::assertSame('INTEIRO', $pais->campo('CODIGO_NUMERICO')?->tipo);
        self::assertSame("yyyy-MM-dd'T'HH:mm:ss.SSSZ", $pais->campo('DATA_INICIO')?->formato);
        self::assertNull($pais->campo('NAO_EXISTE'));
    }

    public function testConsultarComFiltroEContentRange(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'tabx-pais-cn-de-xx.json'), ['Content-Range' => '1 - 3/3', 'Accept-Ranges' => 'dado 500']));

        $pagina = $this->client()->tabelas()->consultar('PAIS', new FiltroTabela()->onde('SIGLA_ISO2', 'CN', 'DE', 'XX')->retornando('SIGLA_ISO2', 'NOME')->ordenadoPor('NOME', decrescente: true));

        parse_str($this->http->lastRequest()->getUri()->getQuery(), $query);
        self::assertSame([
            'filtros' => '[{"nome":"SIGLA_ISO2","valores":["CN","DE","XX"]}]',
            'camposRetorno' => '[{"nome":"SIGLA_ISO2"},{"nome":"NOME"}]',
            'camposOrdenacao' => '[{"nome":"NOME","tipoOrdenacao":"DESC"}]',
        ], $query);
        self::assertCount(3, $pagina);
        self::assertSame([1, 3, 3], [$pagina->inicio, $pagina->fim, $pagina->total]);
        self::assertFalse($pagina->temMais());
        $china = array_values(array_filter($pagina->registros, static fn($r) => $r->valor('SIGLA_ISO2') === 'CN'))[0] ?? self::fail('CN ausente');
        self::assertSame('China, República Popular', $china->valor('NOME'));
        self::assertNull($china->valor('DATA_FIM'), '"" vira null');
        self::assertTrue($china->vigenteEm(new \DateTimeImmutable('2026-10-17')));
    }

    public function testSemResultadoVem404SemCorpo(): void
    {
        $this->http->queue(self::authResponse(), new Response(404));

        $pagina = $this->client()->tabelas()->consultar('PAIS', new FiltroTabela()->onde('NOME', 'Atlântida'));

        self::assertCount(0, $pagina);
        self::assertSame(0, $pagina->total);
    }

    public function testTabelaInexistente(): void
    {
        $this->http->queue(self::authResponse(), self::error(422, 'TABX-IN0003', 'A tabela NAO_EXISTE não foi encontrada.'));

        try {
            $this->client()->tabelas()->metadados('NAO_EXISTE');
            self::fail('esperava ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('Tabela não encontrada.', $e->mensagemAmigavel());
        }
    }

    public function testTodosPercorrePaginasPeloContentRange(): void
    {
        $registro = static fn(string $sigla): array => ['campos' => [['nome' => 'SIGLA_ISO2', 'valor' => $sigla, 'dadosTabelaEstrangeira' => null], ['nome' => 'NOME', 'valor' => 'País '.$sigla, 'dadosTabelaEstrangeira' => null]]];
        // Como no portal (PORTO): 206 nas páginas intermediárias, 200 na última.
        $this->http->queue(
            self::authResponse(),
            self::json(206, ['nomeTabela' => 'PAIS', 'dados' => [$registro('AA'), $registro('BB')]], ['Content-Range' => '1 - 2/3']),
            self::json(200, ['nomeTabela' => 'PAIS', 'dados' => [$registro('CC')]], ['Content-Range' => '3 - 3/3']),
        );

        $siglas = array_map(static fn($r) => $r->valor('SIGLA_ISO2'), iterator_to_array($this->client()->tabelas()->todos('PAIS'), false));

        self::assertSame(['AA', 'BB', 'CC'], $siglas);
        self::assertSame('offset=3', $this->http->lastRequest()->getUri()->getQuery());
    }

    public function testSemContentRangeUsaO206(): void
    {
        $registro = ['campos' => [['nome' => 'SIGLA_ISO2', 'valor' => 'AA']]];
        $this->http->queue(self::authResponse(), self::json(206, ['dados' => [$registro, $registro]]), self::json(200, ['dados' => [$registro]]));

        $tabelas = $this->client()->tabelas();
        $primeira = $tabelas->consultar('PAIS');

        self::assertTrue($primeira->parcial);
        self::assertTrue($primeira->temMais());
        self::assertSame(3, $primeira->proximoOffset());
        self::assertFalse($tabelas->consultar('PAIS', offset: 3)->temMais());
    }

    public function testPaisesVigentes(): void
    {
        $fixture = self::fixture('validacao', 'tabx-pais-cn-de-xx.json');
        $registros = $fixture['dados'] ?? null;
        self::assertIsArray($registros);
        $registros[] = ['campos' => [
            ['nome' => 'SIGLA_ISO2', 'valor' => 'YU'], ['nome' => 'NOME', 'valor' => 'Iugoslávia'],
            ['nome' => 'DATA_INICIO', 'valor' => '1990-01-01T00:00:00.000-0300'], ['nome' => 'DATA_FIM', 'valor' => '2003-02-04T00:00:00.000-0300'],
        ]];
        $dados = ['nomeTabela' => 'PAIS', 'dados' => $registros];
        $this->http->queue(self::authResponse(), self::json(200, $dados, ['Content-Range' => '1 - 4/4']), self::json(200, $dados, ['Content-Range' => '1 - 4/4']));
        $tabelas = $this->client()->tabelas();

        $paises = $tabelas->paises();
        self::assertSame('camposOrdenacao=%5B%7B%22nome%22%3A%22NOME%22%2C%22tipoOrdenacao%22%3A%22ASC%22%7D%5D', $this->http->lastRequest()->getUri()->getQuery());
        self::assertNotContains('YU', array_map(static fn($p) => $p->codigo->codigo, $paises), 'vigência encerrada');
        $china = array_values(array_filter($paises, static fn($p) => $p->codigo->codigo === 'CN'))[0];
        self::assertSame('CHN', $china->siglaIso3);
        self::assertSame('CHINA', $china->nomeIngles);
        self::assertIsInt($china->codigoNumerico);

        self::assertCount(4, $tabelas->paises(apenasVigentes: false));
    }

    public function testValidacoes(): void
    {
        $tabelas = $this->client()->tabelas();

        try {
            $tabelas->consultar('PAIS', offset: 0);
            self::fail('offset 0');
        } catch (\InvalidArgumentException) {
        }
        try {
            new FiltroTabela()->onde('SIGLA_ISO2');
            self::fail('sem valores');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $tabelas->metadados(' ');
    }
}
