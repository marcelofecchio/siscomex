# SDK PHP — API Pública do Portal Único Siscomex

[![CI](https://github.com/marcelofecchio/siscomex-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/marcelofecchio/siscomex-sdk/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/marcelofecchio/siscomex-sdk)](https://packagist.org/packages/marcelofecchio/siscomex-sdk)
![PHP](https://img.shields.io/badge/php-%5E8.4-777bb4)
![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-green)

SDK PHP 8.4, agnóstico de framework, para a **API pública do Portal Único Siscomex** com o perfil **IMPEXP** (Declarante Importador/Exportador):

- **Autenticação** por **Chaves de Acesso** ou **certificado digital A1** (mTLS), com reaproveitamento de sessão, regra dos 60 s e renovação automática do `X-CSRF-Token`;
- **Cadastro de Atributos** — atributos por NCM, consulta por código/nome, downloads e histórico de versões, inclusive o download **público** sem autenticação;
- **Catálogo de Produtos** — produtos (incluir, consultar, detalhar, versionar, retificar, desativar, exportar, códigos internos e inclusão em lote), **operadores estrangeiros** e vínculo de **fabricantes/produtores**, inclusive produto + fabricantes numa só chamada;
- **Tabelas Comex** — consultas paginadas às tabelas de apoio (países, portos, órgãos...);
- Erros normalizados com mensagens amigáveis, **simulação de escrita** para pré-validação e um **Portal Único falso** para os testes de quem usa o SDK.

Inclui coleção **Postman** ([`postman/`](postman/)), exemplos ([`examples/`](examples/)) e validação local do produto contra os atributos da NCM.

> Projeto independente, sem vínculo com a Receita Federal, a SECEX ou o Serpro.

---

## Sumário

- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Pré-requisitos no Siscomex](#pré-requisitos-no-siscomex)
- [Configuração de credenciais](#configuração-de-credenciais)
- [Uso](#uso)
- [Tratamento de erros](#tratamento-de-erros)
- [Sessão, regra dos 60 s e CSRF](#sessão-regra-dos-60-s-e-csrf)
- [Limites de acesso (rate limit)](#limites-de-acesso-rate-limit)
- [Ambientes e códigos de atributos](#ambientes-e-códigos-de-atributos)
- [Integração com Laravel](#integração-com-laravel)
- [Testes de quem usa o SDK](#testes-de-quem-usa-o-sdk)
- [Coleção Postman](#coleção-postman)
- [Versionamento e estabilidade](#versionamento-e-estabilidade)
- [Desenvolvimento e contribuição](#desenvolvimento-e-contribuição)
- [Links oficiais](#links-oficiais)

## Requisitos

- PHP **8.4+** com `ext-json`; `ext-zip` para downloads/exportações; `ext-curl` e `ext-openssl` para certificado A1;
- Um cliente HTTP PSR-18 — recomendado **Guzzle 7** (obrigatório para mTLS com certificado A1).

## Instalação

```bash
composer require marcelofecchio/siscomex-sdk guzzlehttp/guzzle
```

Versão pré-1.0: até a 1.0 a API pública ainda pode mudar entre versões menores (`^0.3` aceita apenas correções `0.3.x`). As mudanças de cada versão estão no [CHANGELOG](CHANGELOG.md).

## Pré-requisitos no Siscomex

1. O usuário (pessoa física) precisa estar **habilitado** e ter **representação no perfil IMPEXP** para a empresa (CNPJ raiz), conforme as regras de habilitação da Receita Federal.
2. **IMPEXP só aceita e-CPF** (o e-CNPJ é recusado com `PLAT-ER2008`).
3. Escolha a credencial:
   - **Chaves de Acesso** (recomendado para sistemas): gere logado com certificado em *Perfil do usuário → Chaves de acesso*, restringindo ao perfil IMPEXP e definindo o tempo de expiração. **As chaves pertencem ao ambiente em que foram geradas** — chaves de Produção são recusadas em Validação (`PLAT-ER8007`) e vice-versa. Não exigem certificado no handshake.
   - **Certificado A1** (.pfx/.p12) e-CPF. **A3 (token/cartão) não é suportado** em servidores nem no Postman.

## Configuração de credenciais

Nunca coloque segredos no código ou no Git. Use variáveis de ambiente (veja [`.env.example`](.env.example)):

```dotenv
SISCOMEX_AMBIENTE=validacao
SISCOMEX_CLIENT_ID=...
SISCOMEX_CLIENT_SECRET=...
```

```php
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\CertificadoA1;

$credencial = ChaveAcesso::fromEnv();                                    // SISCOMEX_CLIENT_ID / SISCOMEX_CLIENT_SECRET
$credencial = new ChaveAcesso($clientId, $clientSecret);
$credencial = new CertificadoA1('/seguro/ecpf.pfx', getenv('CERT_SENHA')); // mTLS via cURL (CURLOPT_SSLCERTTYPE=P12)

// Credenciais guardadas pela aplicação (ex.: banco de dados, cofre de segredos)
$credencial = new ChaveAcesso($empresa->siscomexClientId, $empresa->siscomexClientSecret);
$credencial = CertificadoA1::fromString($empresa->certificadoPfx, $empresa->senhaCertificado);
```

Com certificado A1, `PortalUnicoClient::create()` configura o Guzzle automaticamente. Para outro cliente PSR-18, use `$cert->opcoesCurl()` ou `$cert->paraPem()` (gera um PEM temporário com permissão 0600, apagado quando o objeto é destruído). `CertificadoA1::fromString()` grava o conteúdo (PKCS#12 ou PEM, detectado automaticamente) num arquivo temporário 0600 que é apagado junto com a credencial; a sessão em cache é identificada pelo conteúdo, então continua sendo reaproveitada entre processos.

## Uso

### Criando o cliente

```php
use MarceloFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\FileTokenStore;

$client = PortalUnicoClient::create(
    ambiente: Ambiente::Validacao,              // ou Ambiente::Producao
    credencial: ChaveAcesso::fromEnv(),
    perfil: Perfil::IMPEXP,                     // padrão
    cache: new FileTokenStore(__DIR__.'/storage/siscomex'), // compartilha a sessão entre processos
    logger: $psr3Logger,                        // opcional; tokens e chaves são mascarados
);
```

Opções avançadas via `ClientConfig` (timeout, tentativas em 503, política dos 60 s, bloqueio de escrita em produção):

```php
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Config\PoliticaIntervaloAutenticacao;

$client = PortalUnicoClient::create(
    credencial: $credencial,
    config: new ClientConfig(
        ambiente: Ambiente::Producao,
        politicaIntervalo: PoliticaIntervaloAutenticacao::LancarExcecao, // em vez de aguardar
        timeout: 30,
        tentativas503: 2,
    ),
);
```

Para um botão "Testar conexão", `testarConexao()` nunca lança exceção de API e devolve uma mensagem pronta para o usuário (reaproveita a sessão em cache, se houver):

```php
$teste = $client->testarConexao();
echo $teste->sucesso ? 'OK' : 'Falhou', ' — ', $teste->mensagem;
// $teste->ambiente, ->perfil, ->autenticadoEm, ->sessaoExpiraEm, ->erro (SiscomexException|null)
```

### Cadastro de Atributos

```php
use MarceloFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MarceloFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;

// Atributos de uma NCM (aceita "84733049" ou "8473.30.49")
$atributos = $client->atributos()->porNcm('84733049', modalidade: Modalidade::Importacao);

foreach ($atributos as $atributo) {
    echo $atributo->codigo, ' ', $atributo->nomeApresentacao,
         $atributo->obrigatorio ? ' (obrigatório)' : '', PHP_EOL;
    // $atributo->formaPreenchimento (enum), ->dominio, ->condicionados, ->subatributos, ->multivalorado...
}

// Só os obrigatórios incondicionais para cadastrar produto (objetivo "Produto", vigentes hoje)
$obrigatorios = $atributos->obrigatoriosParaProduto(Modalidade::Importacao);

// Condicionados: todos os possíveis (para montar formulário/planilha) e os exigidos pelos valores já informados
foreach ($atributos->condicionadosParaProduto(Modalidade::Importacao) as $c) {
    echo $c->atributo->codigo, ' se ', $c->descricaoCondicao, $c->obrigatorio ? ' (obrigatório)' : '', PHP_EOL;
    // ex.: "ATT_10008 se 'ATT_5333' Igual 'true' (obrigatório)"
}
$exigidos = $atributos->obrigatoriosPara($produtoRequest);   // incondicionais + condicionados satisfeitos, em cascata
$aplicaveis = $atributos->aplicaveisPara($produtoRequest);   // idem, incluindo os opcionais

// Filtros da API: data de referência, objetivos e órgãos
$client->atributos()->porNcm('84733049', new DateTimeImmutable('2026-12-01'), Modalidade::Exportacao, [ObjetivoAtributo::Produto], ['ANVISA']);

// Detalhes por código ou nome (lotes de 100 automáticos)
$lista = $client->atributos()->porCodigos(['ATT_14545', 'ATT_14783']);
$um    = $client->atributos()->porCodigo('ATT_14545');
$busca = $client->atributos()->porNomes(['Material constitutivo']);

// Relação completa por NCM (ZIP) e histórico de versões
$relacao   = $client->atributos()->downloadPorNcm(codigosNcm: ['84733049']);
$historico = $client->atributos()->historicoVersoesPorNcm();
```

As condições usam os operadores encontrados nas relações do portal (`==`, `!=`, `>`, `>=`, `<=`) encadeados por `||` ou `&&`; quando não é possível avaliar (operador desconhecido), o condicionado não é exigido e a decisão fica com o portal.

Cache opcional (PSR-16) das respostas de `porNcm()`, separado por ambiente e parâmetros; as entradas expiram à meia-noite (horário de Brasília), quando o portal atualiza a relação:

```php
$client = PortalUnicoClient::create(credencial: $credencial, cacheAtributos: $psr16Cache);
```

#### Download público (sem credencial)

```php
$publico = PortalUnicoClient::create(ambiente: Ambiente::Producao)->atributosPublicos();

$relacao = $publico->baixarPorNcm();          // ~15 MB; guarde em cache por dia (atualiza à meia-noite)
$relacao->versao;                              // ex.: "359"
$relacao->atributosDaNcm('84733049');          // AtributosDaNcm|null
$relacao->buscarAtributosPorNome('Anvisa');    // descobrir códigos em cada ambiente
file_put_contents('atributos.json', $publico->baixarJsonPorNcm()); // JSON bruto
```

### Catálogo de Produtos

```php
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroProdutos;
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;

$produtos = $client->catalogo()->produtos();
$cnpjRaiz = '12345678'; // ou CpfCnpjRaiz::deCnpj('12.345.678/0001-95')

// Montagem imutável do produto
$novo = new ProdutoRequest('Placa-mãe para servidor', Modalidade::Importacao, '84733049')
    ->comDescricao('Placa-mãe com 2 soquetes, para servidores de rack')
    ->comAtributo('ATT_14545', '99')                       // códigos do ambiente em uso!
    ->comAtributoMultivalorado('ATT_10888', ['SN1', 'SN2'])
    ->comAtributoComposto('ATT_XXXX', ['ATT_XXXX_1' => 'a', 'ATT_XXXX_2' => 'b'])
    ->comCodigoInterno('SKU-123');

$produto = $produtos->incluir($cnpjRaiz, $novo);                         // versão inicial
$produto = $produtos->incluir($cnpjRaiz, $novo, new DateTimeImmutable('2026-01-01')); // retroativo
echo $produto->codigo, ' v', $produto->versao, ' ', $produto->situacao->value;

$detalhe = $produtos->detalhar($cnpjRaiz, $produto->codigo, '1');        // ou retificação "1.1"
$v2      = $produtos->novaVersao($cnpjRaiz, $produto->codigo, $detalhe->paraRequest()->comDescricao('Nova descrição'));
$v1_1    = $produtos->retificar($cnpjRaiz, $produto->codigo, '1', $novo);
$eventos = $produtos->historico($cnpjRaiz, $produto->codigo);
$produtos->desativar($cnpjRaiz, $produto->codigo);                       // versão mais recente

$produtos->incluirCodigoInterno($cnpjRaiz, $produto->codigo, '2', 'SKU-456');
$produtos->removerCodigoInterno($cnpjRaiz, $produto->codigo, '2', 'SKU-456');

// Consulta com filtros (HTTP 206 = resultado parcial)
$resultado = $produtos->consultar(new FiltroProdutos($cnpjRaiz, ncm: '84733049', situacao: SituacaoProduto::Ativado));
if ($resultado->parcial) { /* refine os filtros ou use exportar() */ }

// Exportação completa (ZIP → lista de Produto)
$todos = $produtos->exportar($cnpjRaiz, exibirDesativados: false);
```

A API não pagina: um HTTP 206 significa que o portal truncou o resultado (`$resultado->parcial`). Refine os filtros ou use a exportação.

#### Código interno

```php
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoCodigoInterno;

$busca = $produtos->buscarPorCodigoInterno($cnpjRaiz, 'SKU-123');
match ($busca->situacao()) {
    SituacaoCodigoInterno::Livre      => $produtos->incluir($cnpjRaiz, $novo),
    SituacaoCodigoInterno::Ativo      => $produtos->novaVersao($cnpjRaiz, $busca->maisRecente()->codigo, $novo),
    SituacaoCodigoInterno::Desativado => $produtos->incluir($cnpjRaiz, $novo), // ou reativar com novaVersao()
};
$busca->duplicado(); // mais de um produto ativo com o mesmo código
```

Regras observadas no ambiente de Validação (17/10/2026):

- o filtro `codigoInterno` do portal é **parcial e não diferencia maiúsculas** (`SKU` encontra `sku-1`); `buscarPorCodigoInterno()` devolve só a igualdade exata;
- a consulta inclui produtos **desativados** e considera apenas a **versão vigente** (um código retirado numa nova versão deixa de ser encontrado);
- o portal **não impõe unicidade**: aceita o mesmo código interno em dois produtos ativos — evitar duplicidade é responsabilidade de quem integra;
- o código interno de um produto desativado pode ser **reutilizado** num produto novo ou incluído em outro produto;
- **`novaVersao()` reativa** um produto desativado; a nova versão substitui a anterior por inteiro, inclusive os códigos internos.

#### Inclusão em lote

```php
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ItemLote;

$resultado = $produtos->incluirLote($cnpjRaiz, $requestsPorLinha, static function (ItemLote $item): void {
    echo $item->chave, ': ', $item->status->value, ' ', implode('; ', $item->mensagens()), PHP_EOL;
    // devolva false para interromper
});

foreach ($resultado->erros() as $item) { /* 4xx do portal: corrija a linha $item->chave */ }
if ($resultado->interrompido()) {
    // PUCX-ER1001, 503, 401/403 ou falha de rede: retome depois com os pendentes
    $quando = $resultado->retomarApos();               // horário de liberação do rate limit, se houver
    $produtos->incluirLote($cnpjRaiz, $resultado->pendentes());
}
```

Nenhum envio é repetido automaticamente. Recusas do portal ao item (400/404/422) e erros 500 ficam como `Erro` e o lote segue; rate limit, indisponibilidade e acesso negado interrompem o lote (o item fica `Pendente`). Se a rede cair durante o POST, o item fica `Incerto` — o portal pode tê-lo incluído; confira com `buscarPorCodigoInterno()` antes de reenviar.

Cada item pode levar fabricantes (`ProdutoComFabricantes`): eles são vinculados logo após a inclusão. Se algum vínculo falhar, o item fica `IncluidoSemVinculo` — **não** reenvie o produto, reprocesse só os vínculos:

```php
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\Fabricante;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoComFabricantes;

$resultado = $produtos->incluirLote($cnpjRaiz, [
    'linha-2' => new ProdutoComFabricantes($produtoA, [Fabricante::operadorEstrangeiro('CN', 'OPE_2')]),
    'linha-3' => $produtoB,                                  // sem fabricantes
]);
$client->catalogo()->fabricantes()->vincularTodos($cnpjRaiz, $resultado->vinculosParaReprocessar());
```

#### Produto com fabricantes

Como o vínculo exige o código do produto, `incluirComFabricantes()` faz a sequência inteira e devolve o estado de cada vínculo:

```php
use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoInclusaoComFabricantes;

$resultado = $produtos->incluirComFabricantes($cnpjRaiz, $novo, [
    Fabricante::operadorEstrangeiro('CN', 'OPE_2'),
    Fabricante::nacional('12.345.678/0001-95', 'BR'),
    Fabricante::desconhecido('DE'),
]);

$resultado->produto->codigo;                           // o produto já existe
if ($resultado->situacao() === SituacaoInclusaoComFabricantes::ProdutoSemVinculo) {
    foreach ($resultado->vinculos as $v) { echo $v->status->value, ' ', $v->erro?->mensagemAmigavel(), PHP_EOL; }
    $client->catalogo()->fabricantes()->vincularTodos($cnpjRaiz, $resultado->paraReprocessar()); // depois de corrigir/aguardar
}
```

Erros na inclusão do **produto** são lançados como em `incluir()`. Depois que ele existe, nada é lançado: vínculo recusado → `Erro` (os demais seguem); rate limit/indisponibilidade → `Pendente` (veja `retomarApos()`); queda de rede → `Incerto` (confira com `fabricantes()->historico()` antes de reenviar; não entra em `paraReprocessar()`).

### Operadores estrangeiros

```php
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroOperadoresEstrangeiros;

$operadores = $client->catalogo()->operadoresEstrangeiros();

$fornecedor = new OperadorEstrangeiroRequest('Shenzhen Parts Co., Ltd.', 'No. 1 Keji Road, Nanshan', 'Shenzhen')
    ->comSubdivisao('CN-GD')                  // ISO 3166-2, do mesmo país
    ->comTin('91440300MA5XXXXXXX')
    ->comEmail('export@example.com')
    ->comCodigoInterno('FORN-001')
    ->comIdentificacaoAdicional('016', '123456789'); // agência UN/EDIFACT 3055 + número

$op = $operadores->incluir($cnpjRaiz, 'CN', $fornecedor);  // país ISO 3166-1 no path
echo $op->codigo, ' v', $op->versao;                        // ex.: "OPE_2 v1"

$op = $operadores->novaVersao($cnpjRaiz, 'CN', $op->codigo, $fornecedor->comEmail('novo@example.com'));
$op = $operadores->retificar($cnpjRaiz, 'CN', $op->codigo, $op->versao, $op->paraRequest()->comCep('518000'));
$operadores->detalhar($cnpjRaiz, 'CN', 'OPE_2', '2.1');
$operadores->historico($cnpjRaiz, 'CN', 'OPE_2');
$operadores->desativar($cnpjRaiz, 'CN', 'OPE_2', '2.1');

// Sem exibirDesativados: true, a consulta omite os desativados (HTTP 206 = parcial)
$lista = $operadores->consultar(new FiltroOperadoresEstrangeiros($cnpjRaiz, nome: 'Shenzhen', paisOrigem: 'CN', exibirDesativados: true));
$todos = $operadores->exportar($cnpjRaiz, exibirDesativados: true);
```

Retificar com dados idênticos aos da versão é recusado com `CATP-ER5013`. Na Validação, a **exportação** veio vazia logo após a inclusão, enquanto `consultar()` já encontrava o operador — para o estado imediato, use a consulta.

### Fabricantes/produtores

O fabricante não faz parte do `ProdutoRequest`: o vínculo é uma operação própria, feita depois de o produto (e, se estrangeiro, o operador) existir.

```php
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;

$fabricantes = $client->catalogo()->fabricantes();

$fabricantes->vincular($cnpjRaiz, FabricanteRequest::operadorEstrangeiro($produto->codigo, 'CN', 'OPE_2'));
$fabricantes->vincular($cnpjRaiz, FabricanteRequest::fabricanteNacional($produto->codigo, '12.345.678/0001-95', 'BR'));
$fabricantes->vincular($cnpjRaiz, FabricanteRequest::desconhecido($produto->codigo, 'DE')); // só o país de origem

$fabricantes->desvincular($cnpjRaiz, FabricanteRequest::operadorEstrangeiro($produto->codigo, 'CN', 'OPE_2'));
$eventos = $fabricantes->historico($cnpjRaiz, $produto->codigo);   // inclusões e exclusões, imediato
$vinculos = $fabricantes->exportar($cnpjRaiz, codigosProduto: [$produto->codigo], paisesOrigem: ['CN']);
```

`conhecido = true` significa fabricante conhecido (a descrição da spec está invertida; confirmado na Validação: `conhecido=true` sem operador é recusado com "Campo Fabricante/produtor estrangeiro é obrigatório"). Os construtores nomeados garantem a combinação coerente dos campos. No histórico, o portal devolve `conhecido` como "Sim"/"Não", convertido para `bool`. Como na exportação de operadores, a de vínculos veio vazia logo após o vínculo.

### Validação local antes de enviar

```php
use MarceloFecchio\Siscomex\PortalUnico\Validation\ValidadorProduto;

$problemas = new ValidadorProduto()->validar($novo, $atributos);
foreach ($problemas as $p) {
    echo $p->atributo, ': ', $p->mensagem, PHP_EOL;
}
```

Verifica obrigatórios ausentes — inclusive **condicionados** cuja condição os valores informados satisfazem —, atributos não vinculados à NCM/modalidade (inclusive código de **outro ambiente**), condicionados informados sem que a condição se cumpra, valores fora do domínio, booleanos, inteiros e tamanho máximo. A validação definitiva é sempre a do portal.

### Evento `catp-prod-desativado`

Quando novos atributos obrigatórios passam a valer para uma NCM, o portal pode **desativar produtos por ofício** e emitir o evento push `catp-prod-desativado` (Notícia Siscomex Sistemas nº 004/2026). O SDK não implementa o recebimento de webhooks; ao ser notificado, use `detalhar()`/`historico()` e cadastre uma nova versão com os atributos exigidos.

### Tabelas Comex (países e outras tabelas de apoio)

A API TABX expõe as tabelas do comércio exterior (`PAIS`, `PORTO`, `ORGAO_ANUENTE`, `TIPO_CONTEINER`...). Exige autenticação (o perfil IMPEXP consegue ler) e o SDK cobre só as consultas. Não há tabela de unidades de medida.

```php
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto\FiltroTabela;

$tabelas = $client->tabelas();

$paises = $tabelas->paises();                  // list<Pais> vigentes, por nome — para o select do operador estrangeiro
echo $paises[0]->codigo, ' ', $paises[0]->nome; // CodigoPais ISO 3166-1, nome em português

$tabelas->listar();                            // tabelas disponíveis
$tabelas->metadados('PORTO');                  // campos, tipos, domínios e chave de negócio

$pagina = $tabelas->consultar('PAIS', new FiltroTabela()->onde('SIGLA_ISO2', 'CN', 'DE')->retornando('SIGLA_ISO2', 'NOME'));
$pagina->registros[0]->valor('NOME');          // "China, República Popular"

foreach ($tabelas->todos('PORTO') as $porto) { /* percorre todas as páginas sob demanda */ }
```

Diferente do Catálogo, a TABX **pagina**: até 500 registros por página, `offset` a partir de 1, faixa e total no header `Content-Range` (`PaginaTabela::$inicio/$fim/$total`, `temMais()`, `proximoOffset()`) e **HTTP 206 enquanto houver mais páginas**. Filtros são por igualdade; sem resultado, o portal responde 404 sem corpo e o SDK devolve uma página vazia. Guarde os países em cache: são ~250 registros que mudam raramente.

### Simulação de escrita (pré-validação)

```php
$simulador = PortalUnicoClient::create(credencial: $credencial, config: new ClientConfig(simularEscrita: true));

$resultado = $simulador->catalogo()->produtos()->incluirLote($cnpjRaiz, $requestsDaPlanilha);
foreach ($simulador->escritasSimuladas() as $e) {
    echo $e->metodo, ' ', $e->url, ' ', json_encode($e->corpo), PHP_EOL;  // o que seria enviado
}
```

Com `simularEscrita`, POST/PUT/DELETE **não saem da máquina** (nem autenticam): a requisição é registrada e a resposta é o próprio corpo enviado — portanto sem os códigos que o portal geraria (`Produto::$codigo` fica `null`, e os vínculos de fabricante ficam `Pendente`). Consultas continuam indo ao portal. Combine com o `ValidadorProduto` para validar uma planilha inteira antes do envio real. A simulação tem prioridade sobre o bloqueio de escrita em produção.

### Endpoints ainda não cobertos

`$client->transport()->json('GET', '/cadatributos/api/ext/atributo/historico-versoes', [...])` executa qualquer chamada autenticada com as mesmas regras de sessão, CSRF, erros e retry.

## Tratamento de erros

Todas as exceções estendem `SiscomexException` e preservam `httpStatus`, `codigoErro`, `tag` (para chamados à Central Serpro), `severidade`, `detalhes` e `corpo`.

| Exceção | Quando |
|---|---|
| `AuthenticationException` | HTTP 401, `PUCX-ER0101/0102`, `PLAT-ER2002/2004/2008/2013/2023/3005/8003/8004/8007/8008` |
| `AuthenticationThrottledException` | `PLAT-ER2033` ou nova autenticação local antes de 60 s (`podeAutenticarEm`) |
| `CertificateException` | `PLAT-ER2001/8001/8002`, falha no handshake ou ao ler o .pfx |
| `ValidationException` | HTTP 400/422 de negócio — `mensagens()` e `detalhes` (lista `detail`) |
| `ForbiddenException` / `NotFoundException` | HTTP 403 / 404 |
| `RateLimitException` | `PUCX-ER1001` — `liberadoEm` e `limite`; **nunca** repetido automaticamente |
| `ServiceUnavailableException` | HTTP 503 (após as tentativas) e `PUCX-ER0401` (parada programada 01h–03h) |
| `ServerException` | HTTP 500 |
| `TransportException` | Falha de rede |
| `ProductionWriteBlockedException` | Escrita em produção com a proteção ativa |

```php
try {
    $produtos->incluir($cnpjRaiz, $novo);
} catch (ValidationException $e) {
    foreach ($e->mensagens() as $msg) { echo $msg, PHP_EOL; }
    $porCampo = $e->mensagensPorCampo();          // ['ATT_5426' => ['O Atributo ATT_5426 ... é obrigatório.'], ...]
    $e->possuiCodigo('CATP-ER2021');              // código no erro ou em algum detalhe
} catch (RateLimitException $e) {
    echo 'Tente após ', $e->liberadoEm?->format('H:i');
} catch (SiscomexException $e) {
    error_log($e->resumoParaChamado()); // "HTTP 422 | código ... | tag [...] | mensagem"
}
```

Toda exceção também expõe o erro em formato estável — `ErroApi`, igual para qualquer tipo de exceção, pronto para a UI ou para JSON (sem o corpo bruto, que pode conter o CPF do usuário):

```php
$erro = $e->erro();
$erro->codigo; $erro->tag; $erro->severidade; $erro->campo; $erro->detalhes; // list<ErroDetalhe>
$erro->mensagemAmigavel();   // ex.: PLAT-ER8007 → "Chaves de acesso inválidas ou geradas em outro ambiente (...)"
$erro->porCampo();           // ['ATT_5426' => ['O Atributo ATT_5426 ... é obrigatório.']]
$erro->codigos();            // código principal + códigos dos detalhes
json_encode($erro->toArray());
```

As mensagens amigáveis (`CodigosErro::DESCRICOES`) cobrem apenas códigos documentados nas specs ou observados no portal; para os demais, `mensagemAmigavel()` devolve a mensagem do portal.

## Sessão, regra dos 60 s e CSRF

- O JWT (`Set-Token`) e o `X-CSRF-Token` ficam no `TokenStore` e são **reaproveitados** até o CSRF expirar (60 min, com 60 s de folga). O SDK nunca autentica por requisição.
- O `X-CSRF-Token` renovado devolvido em qualquer resposta substitui o anterior.
- O portal **não aceita autenticações em intervalo inferior a 60 s** (Comunicados Siscomex Sistemas nº 014/2024 e 010/2025). O SDK registra a última autenticação e, se precisar autenticar antes, **aguarda** (padrão) ou lança `AuthenticationThrottledException` (`PoliticaIntervaloAutenticacao::LancarExcecao`, recomendado em requisições web).
- Em HTTP 401 o SDK reautentica **uma única vez** e repete a requisição.
- Autenticações concorrentes são serializadas por lock (`flock` no `FileTokenStore`).
- Implementações: `InMemoryTokenStore` (padrão, por processo), `FileTokenStore` (arquivos 0600 + `flock`) e `Psr16TokenStore` (qualquer cache PSR-16; informe um `lock` para exclusão mútua entre servidores).
- Retry com backoff exponencial **apenas** para HTTP 503 (exceto parada programada) e erros de rede em GET.

## Limites de acesso (rate limit)

O portal limita execuções por funcionalidade em janelas de 1 hora; ao exceder, bloqueia o restante da janela (+1 h na 2ª reincidência, +2 h a partir da 3ª). Os bloqueios zeram à meia-noite. O SDK converte `PUCX-ER1001` em `RateLimitException` com o horário de liberação e **não** repete a chamada.

## Ambientes e códigos de atributos

| `Ambiente` | URL | Uso |
|---|---|---|
| `Producao` | `https://portalunico.siscomex.gov.br` | Operação real |
| `Validacao` | `https://val.portalunico.siscomex.gov.br` | Desenvolvimento e testes — operações não têm efeito em produção. Pode ficar indisponível seg–sex 18h–7h e nos fins de semana |

Para a UI avisar o usuário (sem bloquear chamadas — a Validação muitas vezes responde dentro dessas janelas):

```php
Ambiente::Validacao->janelaDeIndisponibilidade(new DateTimeImmutable());
// "Janela de manutenção do ambiente de Validação (dias úteis das 18h às 7h e fins de semana)." ou null
Ambiente::Producao->provavelmenteIndisponivel(new DateTimeImmutable('02:00')); // true — parada programada diária 01h–03h
```

**Os códigos de atributos são diferentes entre os ambientes.** Exemplo real (15/10/2026): "Categoria de produto - Catálogo Anvisa" é `ATT_14545` em Produção e `ATT_18551` em Validação. Nunca fixe códigos no código: obtenha-os de `porNcm()` ou do download público do ambiente em uso.

Proteção de produção: com `SISCOMEX_RUN_INTEGRATION=1` (ou `ClientConfig(bloquearEscritaEmProducao: true)`) qualquer POST/PUT/DELETE em produção é recusado com `ProductionWriteBlockedException` antes de sair da máquina.

## Integração com Laravel

Exemplo de service provider (não incluso no pacote):

```php
use Illuminate\Support\Facades\Cache;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\Psr16TokenStore;

$this->app->singleton(PortalUnicoClient::class, fn () => PortalUnicoClient::create(
    ambiente: Ambiente::fromString(config('services.siscomex.ambiente')),
    credencial: new ChaveAcesso(config('services.siscomex.client_id'), config('services.siscomex.client_secret')),
    cache: new Psr16TokenStore(
        Cache::store('redis'),
        lock: fn (string $key, callable $cb) => Cache::lock($key, 90)->block(90, $cb),
    ),
    logger: logger(),
    config: new ClientConfig(politicaIntervalo: PoliticaIntervaloAutenticacao::LancarExcecao),
));
```

## Testes de quem usa o SDK

`Testing\PortalUnicoFake` é um Portal Único falso (cliente PSR-18) com respostas nos formatos observados no portal real, autenticação automática e registro das requisições — sem rede e sem esperas:

```php
use MarceloFecchio\Siscomex\PortalUnico\Testing\PortalUnicoFake;

$fake = new PortalUnicoFake()
    ->codigoInternoLivre()                       // também: codigoInternoAtivo/Desativado/Duplicado
    ->produtoIncluido(codigo: 123)
    ->erroValidacao(['ATT_5426' => 'O Atributo ATT_5426 para o NCM 84733049 é obrigatório.'])
    ->rateLimit(liberadoAs: '15:00')             // também: indisponivel(), falhaDeRede(), operadorIncluido(), fabricanteVinculado()
    ->responder(200, $qualquerJson);             // resposta livre; erro($status, $codigo, $mensagem, $detalhes)

$client = $fake->cliente();                      // PortalUnicoClient ligado ao fake (injete no seu código)
// ... exercite o seu código ...
$fake->chamadas();                               // ['GET /catp/api/ext/produto', 'POST /catp/api/ext/produto/12345678', ...]
$fake->corpoDaUltima();                          // JSON enviado na última requisição
$fake->respostasPendentes();                     // respostas enfileiradas e não usadas
```

## Coleção Postman

Em [`postman/`](postman/): coleção v2.1 com autenticação automática (respeitando os 60 s), captura de CSRF, testes, bloqueio de escrita em produção, pastas de operadores estrangeiros, fabricantes e Tabelas Comex e os fluxos **completo** e **operador e fabricante**. Instruções (certificado, Postman CLI/Newman) em [`postman/README.md`](postman/README.md).

## Versionamento e estabilidade

O projeto segue [SemVer](https://semver.org/lang/pt-BR/) e registra cada mudança no [CHANGELOG](CHANGELOG.md). Enquanto estiver em `0.x`, uma versão **minor** (`0.2` → `0.3`) pode trazer mudanças incompatíveis, sempre descritas na seção "Alterado" do CHANGELOG; versões **patch** (`0.3.0` → `0.3.1`) só corrigem. Use `^0.3` para receber apenas correções.

APIs marcadas como `@experimental` nos docblocks ainda podem mudar de forma, mesmo após a 1.0, até serem estabilizadas: `incluirLote()`, `incluirComFabricantes()`, `fabricantes()->vincularTodos()`, `ClientConfig::$simularEscrita`, `tabelas()` e `Testing\PortalUnicoFake`. Critérios para a 1.0: as APIs experimentais validadas em uso real pelo painel e uma bateria de escrita controlada em Produção feita e documentada pelo responsável (a regra nº 1 do projeto proíbe escrita em Produção em desenvolvimento e testes automatizados).

## Desenvolvimento e contribuição

```bash
composer install
composer qa                 # estilo (PHP-CS-Fixer) + PHPStan nível max + testes unitários
composer test               # só testes unitários
composer test:coverage      # cobertura (requer pcov ou xdebug)
composer cs:fix             # corrige estilo

# Integração — somente Validação e com credenciais de Validação no .env
SISCOMEX_RUN_INTEGRATION=1 SISCOMEX_AMBIENTE=validacao composer test:integration
```

- Commits no padrão Conventional Commits (em português), PRs com `composer qa` verde.
- Antes de alterar um recurso, consulte as specs em [`docs/api/specs/`](docs/api/specs/) e o [mapa de endpoints](docs/api/MAPA-ENDPOINTS.md); não invente campos ou códigos.
- Convenções e regras em [`CLAUDE.md`](CLAUDE.md); andamento em [`docs/PROGRESSO.md`](docs/PROGRESSO.md).

## Links oficiais

- Documentação da API: <https://docs.portalunico.siscomex.gov.br/> (próxima release: <https://docs-proxima.portalunico.siscomex.gov.br/>)
- Autenticação: <https://docs.portalunico.siscomex.gov.br/api/plat/> · Chaves de acesso: <https://docs.portalunico.siscomex.gov.br/pages/chaves-acesso/>
- Catálogo de Produtos: <https://docs.portalunico.siscomex.gov.br/api/catp/> · Cadastro de Atributos: <https://docs.portalunico.siscomex.gov.br/api/cada/> · Tabelas Comex: <https://docs.portalunico.siscomex.gov.br/api/tabx/>
- Ambientes: <https://docs.portalunico.siscomex.gov.br/ambientes/> · Limites de acesso: <https://docs.portalunico.siscomex.gov.br/pages/limites-acesso/>
- Integre seu sistema: <https://www.gov.br/siscomex/pt-br/sistemas-de-comercio-exterior/integre-seu-sistema>
- Suporte técnico: Central Serpro de Atendimento, serviço "PORTAL ÚNICO SISCOMEX – PUCOMEX" (informe a `tag` do erro)

## Licença

[MIT](LICENSE).
