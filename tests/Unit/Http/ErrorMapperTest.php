<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Unit\Http;

use GuzzleHttp\Psr7\Response;
use MFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationThrottledException;
use MFecchio\Siscomex\PortalUnico\Exceptions\CertificateException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ForbiddenException;
use MFecchio\Siscomex\PortalUnico\Exceptions\NotFoundException;
use MFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ServerException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MFecchio\Siscomex\PortalUnico\Exceptions\ValidationException;
use MFecchio\Siscomex\PortalUnico\Http\ErrorMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorMapperTest extends TestCase
{
    /** @return iterable<string, array{int, string, class-string<SiscomexException>}> */
    public static function codigos(): iterable
    {
        yield 'Role-Type ausente' => [422, 'PUCX-ER0101', AuthenticationException::class];
        yield 'Role-Type inválido' => [422, 'PUCX-ER0102', AuthenticationException::class];
        yield 'parada programada' => [503, 'PUCX-ER0401', ServiceUnavailableException::class];
        yield 'rate limit' => [422, 'PUCX-ER1001', RateLimitException::class];
        yield 'certificado inválido' => [422, 'PLAT-ER2001', CertificateException::class];
        yield 'erro SUCE' => [422, 'PLAT-ER2002', AuthenticationException::class];
        yield 'instabilidade SUCE' => [422, 'PLAT-ER2004', AuthenticationException::class];
        yield 'e-CNPJ em perfil e-CPF' => [422, 'PLAT-ER2008', AuthenticationException::class];
        yield 'sem representação' => [422, 'PLAT-ER2013', AuthenticationException::class];
        yield 'CNPJ sem habilitação' => [422, 'PLAT-ER2023', AuthenticationException::class];
        yield 'autenticação < 60 s' => [422, 'PLAT-ER2033', AuthenticationThrottledException::class];
        yield 'CPF irregular' => [422, 'PLAT-ER3005', AuthenticationException::class];
        yield 'certificado equipamento' => [422, 'PLAT-ER8001', CertificateException::class];
        yield 'certificado não habilitado' => [422, 'PLAT-ER8002', CertificateException::class];
        yield 'chave inválida' => [422, 'PLAT-ER8003', AuthenticationException::class];
        yield 'sistema inativo' => [422, 'PLAT-ER8004', AuthenticationException::class];
        yield 'par de chaves inválido' => [422, 'PLAT-ER8007', AuthenticationException::class];
        yield 'falha chave de acesso' => [422, 'PLAT-ER8008', AuthenticationException::class];
        yield '401 genérico' => [401, 'X', AuthenticationException::class];
        yield '403' => [403, 'X', ForbiddenException::class];
        yield '404' => [404, 'X', NotFoundException::class];
        yield '400' => [400, 'X', ValidationException::class];
        yield '422 negócio' => [422, 'CATP-ER0001', ValidationException::class];
        yield '500' => [500, 'X', ServerException::class];
        yield '503' => [503, 'X', ServiceUnavailableException::class];
        yield '409 desconhecido' => [409, 'X', SiscomexException::class];
    }

    /** @param class-string<SiscomexException> $classe */
    #[DataProvider('codigos')]
    public function testMapeiaCodigoParaExcecao(int $status, string $codigo, string $classe): void
    {
        $e = ErrorMapper::fromResponse(new Response($status, [], json_encode([
            'message' => 'Mensagem do portal',
            'code' => $codigo,
            'tag' => '[081454RXF]',
            'status' => $status,
            'severity' => 'ERROR',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame($classe, $e::class);
        self::assertSame($codigo, $e->codigoErro);
        self::assertSame('[081454RXF]', $e->tag);
        self::assertSame('ERROR', $e->severidade);
        self::assertSame($status, $e->httpStatus);
        self::assertSame($status, $e->getCode());
        self::assertSame('Mensagem do portal', $e->getMessage());
        self::assertStringContainsString('[081454RXF]', $e->resumoParaChamado());
    }

    public function testExtraiListaDeErrosDeValidacao(): void
    {
        $e = ErrorMapper::fromResponse(new Response(422, [], json_encode([
            'message' => 'Erros de validação',
            'code' => 'CATP-ER0100',
            'tag' => '[T]',
            'detail' => [
                ['message' => 'NCM inválida', 'code' => 'CATP-ER0101', 'field' => 'ncm'],
                'Denominação obrigatória',
            ],
        ], JSON_THROW_ON_ERROR)));

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertCount(2, $e->detalhes);
        self::assertSame('ncm', $e->detalhes[0]->campo);
        self::assertSame('CATP-ER0101', $e->detalhes[0]->codigo);
        self::assertSame(['NCM inválida', 'Denominação obrigatória'], $e->mensagens());
    }

    public function testValidationSemDetalhesUsaMensagemPrincipal(): void
    {
        $e = ErrorMapper::fromResponse(new Response(422, [], '{"message":"Falhou","code":"X"}'));

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertSame(['Falhou'], $e->mensagens());
    }

    public function testCorpoNaoJsonUsaTextoOuMensagemPadrao(): void
    {
        $html = ErrorMapper::fromResponse(new Response(503, [], '<html><body>Service Unavailable</body></html>'));
        self::assertSame('Service Unavailable', $html->getMessage());
        self::assertNull($html->codigoErro);

        $vazio = ErrorMapper::fromResponse(new Response(500));
        self::assertSame('Erro HTTP 500 retornado pelo Portal Único.', $vazio->getMessage());
    }

    public function testRateLimitExtraiHorarioDeLiberacao(): void
    {
        $agora = new \DateTimeImmutable('2026-10-15 14:45:00', new \DateTimeZone('America/Sao_Paulo'));
        $e = ErrorMapper::fromResponse(new Response(422, [], json_encode([
            'message' => 'Foi atingido o limite de 500 acessos permitidos à funcionalidade em uma hora. A funcionalidade estará disponível novamente após as 15:00:00.',
            'code' => 'PUCX-ER1001',
        ], JSON_THROW_ON_ERROR)), $agora);

        self::assertInstanceOf(RateLimitException::class, $e);
        self::assertSame(500, $e->limite);
        self::assertSame('2026-10-15 15:00:00', $e->liberadoEm?->format('Y-m-d H:i:s'));
    }

    public function testRateLimitComDataCompleta(): void
    {
        [$limite, $liberado] = ErrorMapper::extrairLiberacao('disponível novamente após 16/10/2026 01:00', new \DateTimeImmutable('2026-10-15 23:00'));

        self::assertNull($limite);
        self::assertSame('2026-10-16 01:00', $liberado?->format('Y-m-d H:i'));
    }

    public function testRateLimitSemHorario(): void
    {
        self::assertSame([null, null], ErrorMapper::extrairLiberacao('Limite atingido'));
    }
}
