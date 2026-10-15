# SDK PHP — API Pública do Portal Único Siscomex

[![CI](https://github.com/marcelofecchio/siscomex/actions/workflows/ci.yml/badge.svg)](https://github.com/marcelofecchio/siscomex/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/php-%5E8.4-777bb4)
![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-green)

SDK PHP 8.4, agnóstico de framework, para a **API pública do Portal Único Siscomex** com o perfil **IMPEXP** (Declarante Importador/Exportador):

- **Autenticação** por **Chaves de Acesso** ou **certificado digital A1** (mTLS), com reaproveitamento de sessão, regra dos 60 s e renovação automática do `X-CSRF-Token`;
- **Cadastro de Atributos** — atributos por NCM, consulta por código/nome, downloads e histórico de versões, inclusive o download **público** sem autenticação;
- **Catálogo de Produtos** — incluir, consultar, detalhar, versionar, retificar, desativar, exportar e gerir códigos internos.

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
- [Coleção Postman](#coleção-postman)
- [Desenvolvimento e contribuição](#desenvolvimento-e-contribuição)
- [Links oficiais](#links-oficiais)

## Requisitos

- PHP **8.4+** com `ext-json`; `ext-zip` para downloads/exportações; `ext-curl` e `ext-openssl` para certificado A1;
- Um cliente HTTP PSR-18 — recomendado **Guzzle 7** (obrigatório para mTLS com certificado A1).

## Instalação

```bash
composer require mfecchio/siscomex-portalunico-sdk guzzlehttp/guzzle
```

> Enquanto o pacote não estiver no Packagist, adicione o repositório VCS:
> `composer config repositories.siscomex vcs https://github.com/marcelofecchio/siscomex` e depois
> `composer require mfecchio/siscomex-portalunico-sdk:dev-main`.

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
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\CertificadoA1;

$credencial = ChaveAcesso::fromEnv();                                    // SISCOMEX_CLIENT_ID / SISCOMEX_CLIENT_SECRET
$credencial = new ChaveAcesso($clientId, $clientSecret);
$credencial = new CertificadoA1('/seguro/ecpf.pfx', getenv('CERT_SENHA')); // mTLS via cURL (CURLOPT_SSLCERTTYPE=P12)
```

Com certificado A1, `PortalUnicoClient::create()` configura o Guzzle automaticamente. Para outro cliente PSR-18, use `$cert->opcoesCurl()` ou `$cert->paraPem()` (gera um PEM temporário com permissão 0600, apagado quando o objeto é destruído).

## Uso

### Criando o cliente

```php
use MFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\Perfil;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\FileTokenStore;

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
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\Config\PoliticaIntervaloAutenticacao;

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

### Cadastro de Atributos

```php
use MFecchio\Siscomex\PortalUnico\Enum\Modalidade;
use MFecchio\Siscomex\PortalUnico\Enum\ObjetivoAtributo;

// Atributos de uma NCM (aceita "84733049" ou "8473.30.49")
$atributos = $client->atributos()->porNcm('84733049', modalidade: Modalidade::Importacao);

foreach ($atributos as $atributo) {
    echo $atributo->codigo, ' ', $atributo->nomeApresentacao,
         $atributo->obrigatorio ? ' (obrigatório)' : '', PHP_EOL;
    // $atributo->formaPreenchimento (enum), ->dominio, ->condicionados, ->subatributos, ->multivalorado...
}

// Só os obrigatórios para cadastrar produto (objetivo "Produto", vigentes hoje)
$obrigatorios = $atributos->obrigatoriosParaProduto(Modalidade::Importacao);

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
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FiltroProdutos;
use MFecchio\Siscomex\PortalUnico\Enum\SituacaoProduto;

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

### Validação local antes de enviar

```php
use MFecchio\Siscomex\PortalUnico\Validation\ValidadorProduto;

$problemas = new ValidadorProduto()->validar($novo, $atributos);
foreach ($problemas as $p) {
    echo $p->atributo, ': ', $p->mensagem, PHP_EOL;
}
```

Verifica obrigatórios ausentes, atributos não vinculados à NCM/modalidade (inclusive código de **outro ambiente**), valores fora do domínio, booleanos, inteiros e tamanho máximo. Atributos condicionados não são avaliados — a validação definitiva é sempre a do portal.

### Evento `catp-prod-desativado`

Quando novos atributos obrigatórios passam a valer para uma NCM, o portal pode **desativar produtos por ofício** e emitir o evento push `catp-prod-desativado` (Notícia Siscomex Sistemas nº 004/2026). O SDK não implementa o recebimento de webhooks; ao ser notificado, use `detalhar()`/`historico()` e cadastre uma nova versão com os atributos exigidos.

### Endpoints ainda não cobertos

`$client->transport()->json('GET', '/catp/api/ext/operador-estrangeiro', [...])` executa qualquer chamada autenticada com as mesmas regras de sessão, CSRF, erros e retry.

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
} catch (RateLimitException $e) {
    echo 'Tente após ', $e->liberadoEm?->format('H:i');
} catch (SiscomexException $e) {
    error_log($e->resumoParaChamado()); // "HTTP 422 | código ... | tag [...] | mensagem"
}
```

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

**Os códigos de atributos são diferentes entre os ambientes.** Exemplo real (15/10/2026): "Categoria de produto - Catálogo Anvisa" é `ATT_14545` em Produção e `ATT_18551` em Validação. Nunca fixe códigos no código: obtenha-os de `porNcm()` ou do download público do ambiente em uso.

Proteção de produção: com `SISCOMEX_RUN_INTEGRATION=1` (ou `ClientConfig(bloquearEscritaEmProducao: true)`) qualquer POST/PUT/DELETE em produção é recusado com `ProductionWriteBlockedException` antes de sair da máquina.

## Integração com Laravel

Exemplo de service provider (não incluso no pacote):

```php
use Illuminate\Support\Facades\Cache;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\Psr16TokenStore;

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

## Coleção Postman

Em [`postman/`](postman/): coleção v2.1 com autenticação automática (respeitando os 60 s), captura de CSRF, testes, bloqueio de escrita em produção e a pasta **Fluxo completo**. Instruções (certificado, Newman) em [`postman/README.md`](postman/README.md).

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
- Catálogo de Produtos: <https://docs.portalunico.siscomex.gov.br/api/catp/> · Cadastro de Atributos: <https://docs.portalunico.siscomex.gov.br/api/cada/>
- Ambientes: <https://docs.portalunico.siscomex.gov.br/ambientes/> · Limites de acesso: <https://docs.portalunico.siscomex.gov.br/pages/limites-acesso/>
- Integre seu sistema: <https://www.gov.br/siscomex/pt-br/sistemas-de-comercio-exterior/integre-seu-sistema>
- Suporte técnico: Central Serpro de Atendimento, serviço "PORTAL ÚNICO SISCOMEX – PUCOMEX" (informe a `tag` do erro)

## Licença

[MIT](LICENSE).
