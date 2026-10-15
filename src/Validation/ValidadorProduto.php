<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Validation;

use MarceloFecchio\Siscomex\PortalUnico\Enum\FormaPreenchimento;
use MarceloFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto\AtributosDaNcm;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;

/**
 * Validação local (antes do envio) de um produto contra os atributos da NCM.
 *
 * Verifica: atributos obrigatórios ausentes, atributos não vinculados à NCM/modalidade,
 * valores fora do domínio (listas estáticas e booleanos) e tamanho máximo de textos.
 * Não avalia atributos condicionados — a validação definitiva é sempre a do portal.
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

        $aplicaveis = $atributosDaNcm->filtrar($produto->modalidade, ObjetivoAtributo::Produto, $em);
        $informados = $produto->codigosAtributosInformados();
        $problemas = [];

        foreach ($aplicaveis->atributos as $atributo) {
            if ($atributo->obrigatorio === true && !\in_array($atributo->codigo, $informados, true)) {
                $problemas[] = new ProblemaValidacao($atributo->codigo, \sprintf(
                    'Atributo obrigatório não informado: %s (%s).',
                    $atributo->codigo,
                    $atributo->nomeApresentacao,
                ));
            }
        }

        foreach ($informados as $codigo) {
            if ($aplicaveis->porCodigo($codigo) === null) {
                $problemas[] = new ProblemaValidacao($codigo, \sprintf(
                    'Atributo %s não está vinculado à NCM %s para %s com objetivo Produto (verifique o ambiente: códigos diferem entre Validação e Produção).',
                    $codigo,
                    $produto->ncm->codigo,
                    $produto->modalidade->value,
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
