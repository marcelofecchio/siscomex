<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

use MarceloFecchio\Siscomex\PortalUnico\ValueObject\CodigoPais;

/** País da tabela PAIS do Tabelas Comex (campos observados na Validação em 17/10/2026). */
final readonly class Pais
{
    public function __construct(
        public CodigoPais $codigo,
        public string $nome,
        public ?string $siglaIso3 = null,
        public ?string $nomeIngles = null,
        public ?string $nomeFrances = null,
        public ?int $codigoNumerico = null,
        public ?\DateTimeImmutable $inicioVigencia = null,
        public ?\DateTimeImmutable $fimVigencia = null,
    ) {}

    public static function fromRegistro(RegistroTabela $r): self
    {
        $numerico = $r->valor('CODIGO_NUMERICO');

        return new self(
            codigo: new CodigoPais((string) $r->valor('SIGLA_ISO2')),
            nome: (string) $r->valor('NOME'),
            siglaIso3: $r->valor('SIGLA_ISO3'),
            nomeIngles: $r->valor('NOME_INGLES'),
            nomeFrances: $r->valor('NOME_FRANCES'),
            codigoNumerico: $numerico !== null && is_numeric($numerico) ? (int) $numerico : null,
            inicioVigencia: $r->data('DATA_INICIO'),
            fimVigencia: $r->data('DATA_FIM'),
        );
    }
}
