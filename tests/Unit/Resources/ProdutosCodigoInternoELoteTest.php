<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Unit\Resources;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoCodigoInterno;
use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\TransportException;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Tests\Support\TestCase;

final class ProdutosCodigoInternoELoteTest extends TestCase
{
    public function testBuscaPorCodigoInternoFiltraIgualdadeExata(): void
    {
        // Resposta real (produtos 4 e 5 desativados, versão vigente com SKU-SDK-1). Para simular
        // duplicidade ativa, o 5 e uma cópia do 4 (código 6) viram ativos; o 99 tem código só parecido,
        // como a busca parcial do portal devolveria.
        [$quatro, $cinco] = self::fixture('validacao', 'produtos-por-codigo-interno.json');
        self::assertIsArray($quatro);
        self::assertIsArray($cinco);
        $cinco['situacao'] = 'Ativado';
        $seis = ['codigo' => 6, 'situacao' => 'Ativado'] + $quatro;
        $parecido = ['codigo' => 99, 'situacao' => 'Ativado', 'codigosInterno' => ['SKU-SDK-10']] + $quatro;
        $this->http->queue(self::authResponse(), self::json(200, [$quatro, $cinco, $seis, $parecido]));

        $consulta = $this->client()->catalogo()->produtos()->buscarPorCodigoInterno('12345678', ' SKU-SDK-1 ');

        parse_str($this->http->lastRequest()->getUri()->getQuery(), $query);
        self::assertSame(['cpfCnpjRaiz' => '12345678', 'codigoInterno' => 'SKU-SDK-1'], $query, 'sem filtro de situação: inclui desativados');
        self::assertSame([4, 5, 6], array_map(static fn($p) => $p->codigo, $consulta->produtos));
        self::assertSame(SituacaoCodigoInterno::Ativo, $consulta->situacao());
        self::assertTrue($consulta->duplicado());
        self::assertSame([4], array_map(static fn($p) => $p->codigo, $consulta->inativos()));
        self::assertSame(5, $consulta->maisRecente()?->codigo, 'ativo com início de vigência mais novo');
    }

    public function testCodigoInternoLivreEDesativado(): void
    {
        $this->http->queue(self::authResponse(), self::json(204, null), self::json(200, self::fixture('validacao', 'produtos-por-codigo-interno.json')));
        $produtos = $this->client()->catalogo()->produtos();

        $livre = $produtos->buscarPorCodigoInterno('12345678', 'NOVO');
        self::assertSame(SituacaoCodigoInterno::Livre, $livre->situacao());
        self::assertNull($livre->maisRecente());

        $inativo = $produtos->buscarPorCodigoInterno('12345678', 'SKU-SDK-1');
        self::assertSame(SituacaoCodigoInterno::Desativado, $inativo->situacao());
        self::assertFalse($inativo->duplicado());
        self::assertSame(5, $inativo->maisRecente()?->codigo);
    }

    public function testCodigoInternoVazio(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->catalogo()->produtos()->buscarPorCodigoInterno('12345678', '  ');
    }

    public function testLoteSegueAposErroDoItem(): void
    {
        $this->http->queue(
            self::authResponse(),
            self::json(200, self::produto(10)),
            self::json(422, self::fixture('validacao', 'erro-422-atributos-obrigatorios.json')),
            // PUCX-ER0101 é mapeado como autenticação, mas num 422 é erro do item.
            self::error(422, 'PUCX-ER0101', 'Campo obrigatório.'),
            self::json(200, self::produto(11)),
        );
        $progresso = [];

        $resultado = $this->client()->catalogo()->produtos()->incluirLote('12345678', [
            'linha-2' => self::request('A'),
            'linha-3' => self::request('B'),
            'linha-4' => self::request('C'),
            'linha-5' => self::request('D'),
        ], static function (ItemLote $item) use (&$progresso): void {
            $progresso[] = $item->chave.':'.$item->status->value;
        });

        self::assertSame(['linha-2:INCLUIDO', 'linha-3:ERRO', 'linha-4:ERRO', 'linha-5:INCLUIDO'], $progresso);
        self::assertFalse($resultado->interrompido());
        self::assertCount(2, $resultado->incluidos());
        self::assertSame(10, $resultado->incluidos()[0]->produto?->codigo);
        self::assertSame('O Atributo ATT_5426 para o NCM 84733049 é obrigatório.', $resultado->erros()[0]->mensagens()[0] ?? null, 'a mensagem já cita o campo: sem prefixo');
        self::assertCount(4, $resultado->erros()[0]->mensagens());
        self::assertSame(['Campo obrigatório.'], $resultado->erros()[1]->mensagens());
        self::assertSame([], iterator_to_array($resultado->pendentes()));
    }

    public function testLoteParaNoRateLimitEPermiteRetomar(): void
    {
        $this->http->queue(
            self::authResponse(),
            self::json(200, self::produto(10)),
            self::error(429, 'PUCX-ER1001', 'Limite de 1000 acessos por hora excedido. Tente novamente após as 15:00.'),
        );

        $resultado = $this->client()->catalogo()->produtos()->incluirLote('12345678', ['a' => self::request('A'), 'b' => self::request('B'), 'c' => self::request('C')]);

        self::assertTrue($resultado->interrompido());
        self::assertInstanceOf(RateLimitException::class, $resultado->interrupcao);
        self::assertSame('15:00', $resultado->retomarApos()?->format('H:i'));
        self::assertSame([StatusItemLote::Incluido, StatusItemLote::Pendente, StatusItemLote::Pendente], array_map(static fn(ItemLote $i) => $i->status, $resultado->itens));
        self::assertSame(['b', 'c'], array_keys(iterator_to_array($resultado->pendentes())));
        self::assertSame(0, $this->http->pending(), 'o item b não é reenviado automaticamente');
        self::assertCount(3, $this->http->requests);
    }

    public function testFalhaDeRedeNoPostDeixaItemIncerto(): void
    {
        $this->http->queue(self::authResponse(), new ConnectException('Connection reset', new Request('POST', 'https://val.portalunico.siscomex.gov.br')));

        $resultado = $this->client()->catalogo()->produtos()->incluirLote('12345678', [self::request('A'), self::request('B')]);

        self::assertSame(StatusItemLote::Incerto, $resultado->itens[0]->status);
        self::assertInstanceOf(TransportException::class, $resultado->itens[0]->erro);
        self::assertSame(StatusItemLote::Pendente, $resultado->itens[1]->status);
        self::assertSame([], $resultado->porStatus(StatusItemLote::Erro));
    }

    public function testCallbackPodeInterromper(): void
    {
        $this->http->queue(self::authResponse(), self::json(200, self::produto(10)));

        $resultado = $this->client()->catalogo()->produtos()->incluirLote(
            '12345678',
            (static function () {
                yield 7 => self::request('A');
                yield 8 => self::request('B');
            })(),
            static fn(ItemLote $item): bool => false,
        );

        self::assertTrue($resultado->interrompido());
        self::assertCount(2, $resultado);
        self::assertSame([8], array_keys(iterator_to_array($resultado->pendentes())));
        self::assertNull($resultado->retomarApos());
    }

    /** @return array<string, mixed> */
    private static function produto(int $codigo): array
    {
        return ['codigo' => $codigo, 'versao' => '1', 'situacao' => 'ATIVADO', 'modalidade' => 'IMPORTACAO', 'ncm' => '84733049', 'denominacao' => 'P'];
    }

    private static function request(string $denominacao): ProdutoRequest
    {
        return new ProdutoRequest($denominacao, Modalidade::Importacao, '84733049');
    }
}
