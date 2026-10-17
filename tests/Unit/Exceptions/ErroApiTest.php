<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Exceptions;

use GuzzleHttp\Psr7\Response;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\CodigosErro;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ErroApi;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Http\ErrorMapper;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class ErroApiTest extends TestCase
{
    public function testErro422RealDoCatalogo(): void
    {
        $e = ErrorMapper::fromResponse(new Response(422, [], (string) json_encode(self::fixture('validacao', 'erro-422-atributos-obrigatorios.json'))));

        $erro = $e->erro();

        self::assertSame(422, $erro->httpStatus);
        self::assertSame('PUCX-ER0123', $erro->codigo);
        self::assertSame('[CATP-AKYQPM3945]', $erro->tag);
        self::assertSame('ERROR', $erro->severidade);
        self::assertNull($erro->campo);
        self::assertSame(['PUCX-ER0123', 'CADA-ER5008'], $erro->codigos());
        self::assertTrue($erro->possuiCodigo('CADA-ER5008'));
        self::assertSame('O portal encontrou problemas nos dados enviados; veja os detalhes de cada campo.', $erro->mensagemAmigavel());
        self::assertSame(['O Atributo ATT_5426 para o NCM 84733049 é obrigatório.'], $erro->porCampo()['ATT_5426']);
        self::assertCount(4, $erro->porCampo());

        $array = $erro->toArray();
        self::assertSame('ATT_18551', $array['detalhes'][3]['campo']);
        self::assertStringNotContainsString('00000000000', (string) json_encode($array), 'info.usuario (CPF) não vaza');
    }

    public function testMensagemAmigavelDeCodigoConhecidoEDesconhecido(): void
    {
        $chaveDeOutroAmbiente = ErrorMapper::fromResponse(self::error(422, 'PLAT-ER8007', 'Par de chaves inválido ou inexistente'));
        self::assertStringContainsString('outro ambiente', $chaveDeOutroAmbiente->mensagemAmigavel());

        $desconhecido = ErrorMapper::fromResponse(self::error(422, 'CATP-ER9999', ' Mensagem do portal '));
        self::assertSame('Mensagem do portal', $desconhecido->mensagemAmigavel());
        self::assertSame(['' => [' Mensagem do portal ']], $desconhecido->erro()->porCampo(), 'porCampo traz a mensagem bruta');
    }

    public function testCampoNoNivelSuperior(): void
    {
        $e = ErrorMapper::fromResponse(self::json(422, ['message' => 'Valor inválido', 'code' => 'PUCX-ER0102', 'field' => 'ncm']));

        self::assertSame('ncm', $e->erro()->campo);
        self::assertSame(['ncm' => ['Valor inválido']], $e->erro()->porCampo());
    }

    public function testErroSemRespostaHttp(): void
    {
        $erro = new TransportException('Falha de rede: timeout')->erro();

        self::assertNull($erro->httpStatus);
        self::assertSame([], $erro->codigos());
        self::assertSame('HTTP - | código - | tag - | Falha de rede: timeout', $erro->resumoParaChamado());
        self::assertSame('Falha de rede: timeout', $erro->mensagemAmigavel());
    }

    public function testCodigosConhecidosTemDescricao(): void
    {
        foreach (CodigosErro::DESCRICOES as $codigo => $descricao) {
            self::assertMatchesRegularExpression('/^[A-Z]{4}-[A-Z]{2}\d{4}$/', $codigo);
            self::assertStringEndsWith('.', $descricao, $codigo);
        }
        self::assertNull(CodigosErro::descricao(null));
        self::assertSame('x', ErroApi::deExcecao(new TransportException('x'))->mensagem);
    }
}
