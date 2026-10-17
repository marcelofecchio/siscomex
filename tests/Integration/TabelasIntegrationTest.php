<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Integration;

use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\FiltroTabela;

/** Tabelas Comex (TABX), somente leitura, na Validação. */
final class TabelasIntegrationTest extends IntegrationTestCase
{
    public function testPaisesEPaginacao(): void
    {
        $tabelas = self::client()->tabelas();

        $nomes = array_map(static fn($t) => $t->nome, $this->tolerandoIndisponibilidade(static fn() => $tabelas->listar()));
        self::assertContains('PAIS', $nomes);

        self::assertSame(['SIGLA_ISO2'], $tabelas->metadados('PAIS')->chaveNegocio());

        $filtrada = $tabelas->consultar('PAIS', new FiltroTabela()->onde('SIGLA_ISO2', 'CN', 'DE'));
        self::assertSame(2, $filtrada->total);

        $paises = $tabelas->paises();
        self::assertGreaterThan(200, \count($paises));
        self::assertContains('CN', array_map(static fn($p) => $p->codigo->codigo, $paises));

        self::assertCount(0, $tabelas->consultar('PAIS', new FiltroTabela()->onde('NOME', 'Atlântida SDK')));

        // PORTO tem mais de uma página (500 registros por página).
        $pagina = $tabelas->consultar('PORTO');
        self::assertTrue($pagina->temMais());
        self::assertSame($pagina->total, iterator_count($tabelas->todos('PORTO')));
    }
}
