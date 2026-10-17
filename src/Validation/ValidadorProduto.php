<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Validation;

use MarceloFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributosDaNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;

/**
 * Validação local (antes do envio) de um produto contra os atributos da NCM.
 *
 * Verifica: atributos obrigatórios ausentes (inclusive condicionados cuja condição o produto
 * satisfaz), atributos não vinculados à NCM/modalidade, condicionados informados sem que a
 * condição se cumpra, valores fora do domínio (listas estáticas e booleanos) e tamanho máximo
 * de textos. A validação definitiva é sempre a do portal.
 */
final class ValidadorProduto
{
    /**
     * @return list<ProblemaValidacao>
     */
    public function validar(ProdutoRequest $produto, AtributosDaNcm $atributosDaNcm, ?\DateTimeInterface $em = null): array
    {
        if (!$atributosDaNcm->ncm->equals($produto->ncm)) {
            return [new ProblemaValidacao(null, \sprintf(
                'Os atributos informados são da NCM %s, mas o produto é da NCM %s.',
                $atributosDaNcm->ncm->codigo,
                $produto->ncm->codigo,
            ))];
        }

        $aplicaveis = $atributosDaNcm->aplicaveisPara($produto, $em);
        $informados = $produto->codigosAtributosInformados();
        $problemas = [];

        foreach ($aplicaveis->atributos as $atributo) {
            if ($atributo->obrigatorio === true && !\in_array($atributo->codigo, $informados, true)) {
                $condicionado = $atributosDaNcm->condicionadoPorCodigo($atributo->codigo, $produto->modalidade);
                $problemas[] = new ProblemaValidacao($atributo->codigo, \sprintf(
                    'Atributo obrigatório não informado: %s (%s)%s.',
                    $atributo->codigo,
                    $atributo->nomeApresentacao,
                    $condicionado?->descricaoCondicao === null ? '' : ', exigido porque '.$condicionado->descricaoCondicao,
                ));
            }
        }

        foreach ($informados as $codigo) {
            if ($aplicaveis->porCodigo($codigo) !== null) {
                continue;
            }
            $condicionado = $atributosDaNcm->condicionadoPorCodigo($codigo, $produto->modalidade);
            if ($condicionado === null) {
                $problemas[] = new ProblemaValidacao($codigo, \sprintf(
                    'Atributo %s não está vinculado à NCM %s para %s com objetivo Produto (verifique o ambiente: códigos diferem entre Validação e Produção).',
                    $codigo,
                    $produto->ncm->codigo,
                    $produto->modalidade->value,
                ));
            } elseif ($condicionado->satisfeitaPor($produto->valoresDoAtributo((string) $condicionado->codigoCondicionante)) === false) {
                // Condição indeterminada (null) não gera problema: o portal decide.
                $problemas[] = new ProblemaValidacao($codigo, \sprintf(
                    'Atributo %s é condicionado e só se aplica quando %s.',
                    $codigo,
                    $condicionado->descricaoCondicao ?? 'a condição do atributo '.$condicionado->codigoCondicionante.' é satisfeita',
                ));
            }
        }

        foreach ($produto->atributos as $valor) {
            $atributo = $aplicaveis->porCodigo($valor->atributo);
            if ($atributo === null) {
                continue;
            }
            if ($atributo->multivalorado === true) {
                $problemas[] = new ProblemaValidacao($valor->atributo, \sprintf('Atributo %s é multivalorado: informe-o em atributosMultivalorados.', $valor->atributo));
            }
            $problema = match ($atributo->formaPreenchimento) {
                FormaPreenchimento::ListaEstatica => $atributo->valorDominioValido($valor->valor) ? null : \sprintf('Valor "%s" fora do domínio do atributo %s.', $valor->valor, $valor->atributo),
                FormaPreenchimento::Booleano => \in_array($valor->valor, ['true', 'false'], true) ? null : \sprintf('Atributo %s é booleano: use "true" ou "false".', $valor->atributo),
                FormaPreenchimento::NumeroInteiro => preg_match('/^-?\d+$/', $valor->valor) === 1 ? null : \sprintf('Atributo %s exige número inteiro.', $valor->atributo),
                default => null,
            };
            if ($problema === null && $atributo->tamanhoMaximo !== null && $atributo->tamanhoMaximo > 0 && mb_strlen($valor->valor) > $atributo->tamanhoMaximo) {
                $problema = \sprintf('Valor do atributo %s excede %d caracteres.', $valor->atributo, $atributo->tamanhoMaximo);
            }
            if ($problema !== null) {
                $problemas[] = new ProblemaValidacao($valor->atributo, $problema);
            }
        }

        return $problemas;
    }
}
