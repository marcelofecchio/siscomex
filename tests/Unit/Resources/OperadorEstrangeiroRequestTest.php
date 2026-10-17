<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class OperadorEstrangeiroRequestTest extends TestCase
{
    public function testSomenteObrigatoriosOmiteOpcionais(): void
    {
        $request = new OperadorEstrangeiroRequest('Fornecedor', 'Rua 1', 'Buenos Aires');

        self::assertSame(['nome' => 'Fornecedor', 'logradouro' => 'Rua 1', 'nomeCidade' => 'Buenos Aires', 'identificacoesAdicionais' => []], $request->toArray());
    }

    public function testMetodosComSaoImutaveis(): void
    {
        $original = new OperadorEstrangeiroRequest('Fornecedor', 'Rua 1', 'Buenos Aires');
        $alterado = $original->comEndereco('Av. 2', 'La Plata', '1900', 'ar-b')->comNome('Outro')->comEmail('a@b.com');

        self::assertSame('Rua 1', $original->logradouro);
        self::assertNull($original->email);
        self::assertSame('Av. 2', $alterado->logradouro);
        self::assertSame('La Plata', $alterado->nomeCidade);
        self::assertSame('1900', $alterado->cep);
        self::assertSame('AR-B', $alterado->codigoSubdivisaoPais);
        self::assertSame('Outro', $alterado->nome);
        self::assertNull($alterado->comEmail(null)->email);
    }

    /** @return iterable<string, array{\Closure(): mixed, string}> */
    public static function invalidos(): iterable
    {
        yield 'nome vazio' => [static fn() => new OperadorEstrangeiroRequest('  ', 'Rua', 'Cidade'), 'nome'];
        yield 'nome longo' => [static fn() => new OperadorEstrangeiroRequest(str_repeat('x', 151), 'Rua', 'Cidade'), 'nome'];
        yield 'logradouro longo' => [static fn() => new OperadorEstrangeiroRequest('N', str_repeat('x', 71), 'Cidade'), 'logradouro'];
        yield 'cidade longa' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', str_repeat('x', 36)), 'cidade'];
        yield 'tin longo' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade', tin: str_repeat('1', 36)), 'TIN'];
        yield 'tin vazio' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade')->comTin(''), 'TIN'];
        yield 'cep longo' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade')->comCep('1234567890'), 'postal'];
        yield 'email' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade')->comEmail('nao-e-email'), 'E-mail'];
        yield 'subdivisão' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade')->comSubdivisao('GD'), 'Subdivisão'];
        yield 'agência' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade')->comIdentificacaoAdicional('1234', 'X'), 'agência'];
        yield 'identificação' => [static fn() => new OperadorEstrangeiroRequest('N', 'Rua', 'Cidade')->comIdentificacaoAdicional('016', str_repeat('9', 36)), 'identificação'];
    }

    /** @param \Closure(): mixed $criar */
    #[DataProvider('invalidos')]
    public function testValidacoesDaSpec(\Closure $criar, string $trecho): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($trecho, '/').'/i');
        $criar();
    }

    public function testRespostaViraRequestParaNovaVersao(): void
    {
        $operador = OperadorEstrangeiro::fromArray(self::fixture('validacao', 'operador-estrangeiro-detalhe.json'));

        $request = $operador->paraRequest()->comEmail('novo@example.com');

        self::assertSame('SDK Teste Operador 174422', $request->nome);
        self::assertSame('CN-GD', $request->codigoSubdivisaoPais);
        self::assertSame('TIN174422', $request->tin);
        self::assertSame('novo@example.com', $request->toArray()['email']);
    }
}
