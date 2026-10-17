<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Psr7\Response;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\FabricantesResource;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class FabricantesResourceTest extends TestCase
{
    /** Formato da resposta real de POST/PUT /ext/fabricante (Validação, 17/10/2026). */
    private const array VINCULO = [
        'cpfCnpjRaiz' => '12345678',
        'codigoOperadorEstrangeiro' => 'OPE_2',
        'conhecido' => true,
        'codigoProduto' => 4,
        'codigoPais' => 'CN',
    ];

    public function testVincularOperadorEstrangeiro(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::VINCULO));

        $vinculo = $this->fabricantes()->vincular('12345678', FabricanteRequest::operadorEstrangeiro(4, 'cn', 'OPE_2'));

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/catp/api/ext/fabricante/12345678', $request->getUri()->getPath());
        self::assertSame(['codigoProduto' => 4, 'codigoPais' => 'CN', 'conhecido' => true, 'codigoOperadorEstrangeiro' => 'OPE_2'], self::body($request));
        self::assertNotNull($vinculo);
        self::assertTrue($vinculo->conhecido);
        self::assertSame('OPE_2', $vinculo->codigoOperadorEstrangeiro);
        self::assertSame(4, $vinculo->codigoProduto);
    }

    public function testVincularDesconhecidoEFabricanteNacional(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, ['conhecido' => false, 'codigoProduto' => 4, 'codigoPais' => 'DE']), self::json(204, null));
        $fabricantes = $this->fabricantes();

        $desconhecido = $fabricantes->vincular('12345678', FabricanteRequest::desconhecido(4, 'DE'));
        self::assertSame(['codigoProduto' => 4, 'codigoPais' => 'DE', 'conhecido' => false], self::body($this->http->lastRequest()));
        self::assertFalse($desconhecido?->conhecido);

        self::assertNull($fabricantes->vincular('12345678', FabricanteRequest::fabricanteNacional(4, '12.345.678/0001-95', 'BR')));
        self::assertSame('12345678000195', self::body($this->http->lastRequest())['cpfCnpjFabricante']);
    }

    public function testDesvincularUsaPut(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::VINCULO));

        $this->fabricantes()->desvincular('12345678', FabricanteRequest::operadorEstrangeiro(4, 'CN', 'OPE_2'));

        self::assertSame('PUT /catp/api/ext/fabricante/12345678', $this->http->calls()[1]);
    }

    public function testConhecidoSemOperadorRecusadoPeloPortal(): void
    {
        // Corpo reconstruído a partir da resposta real: conhecido=true sem operador (Validação, 17/10/2026).
        $this->http->queue(self::authResponse(), self::json(422, [
            'message' => 'Problemas encontrados: ',
            'tag' => '[CATP-AEPOGY3945]',
            'status' => 422,
            'detail' => [
                ['error' => ['message' => 'Campo Fabricante/produtor estrangeiro é obrigatório.', 'code' => 'PUCX-ER0101']],
                ['error' => ['message' => 'Campo Fabricante/produtor estrangeiro está inválido.', 'code' => 'PUCX-ER0102']],
            ],
        ]));

        try {
            $this->fabricantes()->vincular('12345678', FabricanteRequest::desconhecido(4, 'FR'));
            self::fail('esperava ValidationException');
        } catch (ValidationException $e) {
            self::assertTrue($e->possuiCodigo('PUCX-ER0101'));
            self::assertSame(['' => ['Campo Fabricante/produtor estrangeiro é obrigatório.', 'Campo Fabricante/produtor estrangeiro está inválido.']], $e->mensagensPorCampo());
        }
    }

    public function testHistoricoComConhecidoSimNao(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::fixture('validacao', 'fabricante-historico.json')));

        $eventos = $this->fabricantes()->historico('12345678', 4);

        self::assertSame('/catp/api/ext/fabricante/historico/12345678/4', $this->http->lastRequest()->getUri()->getPath());
        self::assertCount(4, $eventos);
        self::assertFalse($eventos[0]->conhecido, '"Não"');
        self::assertFalse($eventos[0]->inclusao());
        self::assertTrue($eventos[3]->conhecido, '"Sim"');
        self::assertTrue($eventos[3]->inclusao());
        self::assertSame('OPE_2', $eventos[3]->codigo);
        self::assertSame('TIN174422', $eventos[3]->cpfCnpjTin);
        self::assertSame('2026-10-17 14:45:13', $eventos[3]->data?->format('Y-m-d H:i:s'));
    }

    public function testExportarComFiltros(): void
    {
        $item = self::VINCULO + ['seq' => 1, 'vincular' => true, 'dataReferencia' => '2026-10-01'];
        $this->http->queue(
            self::authResponse(),
            new Response(200, ['Content-Type' => 'application/zip'], self::zip('fabricantes.json', (string) json_encode([$item]))),
            self::json(200, $item),
            self::json(204, null),
        );
        $fabricantes = $this->fabricantes();

        $vinculos = $fabricantes->exportar('12345678', [4, 5], ['cn']);
        self::assertSame('codigosProduto=4&codigosProduto=5&paisesOrigem=CN', $this->http->lastRequest()->getUri()->getQuery());
        self::assertSame(1, $vinculos[0]->seq);
        self::assertTrue($vinculos[0]->vincular);
        self::assertSame('2026-10-01', $vinculos[0]->dataReferencia?->format('Y-m-d'));

        self::assertCount(1, $fabricantes->exportar('12345678'), 'objeto único, como na spec');
        self::assertSame([], $fabricantes->exportar('12345678'));
    }

    /** @return iterable<string, array{\Closure(): mixed}> */
    public static function requestsIncoerentes(): iterable
    {
        yield 'conhecido sem identificação' => [static fn() => new FabricanteRequest(4, 'CN', true)];
        yield 'conhecido com as duas identificações' => [static fn() => new FabricanteRequest(4, 'CN', true, 'OPE_2', '12345678901')];
        yield 'desconhecido com operador' => [static fn() => new FabricanteRequest(4, 'CN', false, 'OPE_2')];
        yield 'produto zero' => [static fn() => FabricanteRequest::desconhecido(0, 'CN')];
        yield 'país inválido' => [static fn() => FabricanteRequest::desconhecido(4, 'CHN')];
        yield 'cpf/cnpj inválido' => [static fn() => FabricanteRequest::fabricanteNacional(4, '123', 'BR')];
        yield 'operador longo' => [static fn() => FabricanteRequest::operadorEstrangeiro(4, 'CN', str_repeat('X', 36))];
    }

    /** @param \Closure(): mixed $criar */
    #[\PHPUnit\Framework\Attributes\DataProvider('requestsIncoerentes')]
    public function testRequestIncoerenteERecusado(\Closure $criar): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $criar();
    }

    public function testCnpjAlfanumericoECpf(): void
    {
        self::assertSame('12ABC34501DE35', FabricanteRequest::fabricanteNacional(1, '12.abc.345/01de-35', 'BR')->cpfCnpjFabricante);
        self::assertSame('52998224725', FabricanteRequest::fabricanteNacional(1, '529.982.247-25', 'BR')->cpfCnpjFabricante);
    }

    private function fabricantes(): FabricantesResource
    {
        return $this->client()->catalogo()->fabricantes();
    }
}
