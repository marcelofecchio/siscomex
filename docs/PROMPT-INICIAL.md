# PROMPT — SDK PHP 8.4 para a API Pública do Portal Único Siscomex
### Escopo: Cadastro de Atributos + Catálogo de Produtos (perfil IMPEXP)

> **Como usar**
> - **Projeto novo:** copie este arquivo para um repositório Git vazio e peça ao agente (ex.: Claude Code): *"Leia PROMPT-INICIAL.md e execute a Fase 0."*
> - **Este repositório (já executado):** *"Leia CLAUDE.md e docs/PROGRESSO.md e continue de onde parou."* O estado atual, as decisões e os achados ficam em `docs/PROGRESSO.md`.

> **Revisões**
> - 15/10/2026 — versão original, escrita antes da implementação (fatos levantados na documentação).
> - 16/10/2026 — revisado após a v0.1.0: parâmetros da seção 2 definidos, hipóteses das seções 3 e 4 confirmadas ou corrigidas contra as specs e o portal real (marcadas com ✅ ou ⚠️), estrutura e exemplos alinhados ao código.

---

## 1. Papel e objetivo

Você é um engenheiro PHP sênior especialista em SDKs, integrações REST com autenticação mTLS e publicação de pacotes no Packagist.

Construa um **pacote Composer do tipo SDK**, reutilizável e agnóstico de framework, que torne simples para qualquer aplicação PHP 8.4:

1. Autenticar no Portal Único Siscomex com o perfil **IMPEXP** (Declarante Importador/Exportador);
2. Consultar o **Cadastro de Atributos** (atributos por NCM, domínios, obrigatoriedade, condicionantes);
3. Operar o **Catálogo de Produtos** (incluir, consultar, versionar, retificar e desativar produtos).

Entregáveis obrigatórios:

- Código do SDK (`src/`) + testes automatizados (`tests/`);
- Coleção **Postman** com ambientes (`postman/`);
- `README.md` com exemplos de uso;
- `CLAUDE.md` versionado na raiz (stack, convenções, comandos, regras);
- `docs/PROGRESSO.md` (feito / falta / decisões);
- `CHANGELOG.md`, `LICENSE`, `.env.example`, `.gitignore`, CI.

**Não crie nenhuma aplicação final** (sem telas, sem painel, sem API própria). Apenas o SDK, seus testes, exemplos mínimos em `examples/` e a coleção Postman.

---

## 2. Parâmetros do projeto

Em um projeto novo, confirme cada item com o usuário na Fase 0 e registre em `docs/PROGRESSO.md` → "Decisões". Valores definidos neste repositório:

| Parâmetro | Valor definido | Observação |
|---|---|---|
| Nome no Packagist (`vendor/pacote`) | `marcelofecchio/siscomex` | Alinhado aos pacotes existentes da conta (`marcelofecchio/correios-api`, `marcelofecchio/safe2pay-api`). Antes de publicar, confira o vendor já usado pelo usuário: o nome fica permanente. |
| Namespace raiz | `MarceloFecchio\Siscomex\PortalUnico` | Mesmo padrão `MarceloFecchio\<Pacote>` dos outros pacotes |
| Versão mínima do PHP | `^8.4` | CI também roda 8.5 (*allow-failure*) |
| Cliente HTTP | PSR-18/PSR-17 com `php-http/discovery`; Guzzle 7 recomendado | Guzzle é obrigatório para mTLS (o PSR-18 não padroniza TLS) |
| Cache de tokens | Interface `TokenStore`: memória, arquivo (`flock`) e adaptador PSR-16 | O PSR-16 não tem lock: o adaptador aceita um lock injetável |
| Licença | MIT | |
| Método de autenticação | **Chaves de Acesso** (o usuário possui) + certificado A1 suportado | |
| Tipo de certificado | A1 apenas | A3 não funciona em servidor nem no Postman |
| Repositório remoto | `github.com/marcelofecchio/siscomex` (público) | |
| Fabricantes e Operadores Estrangeiros | v1.1 | Já acessíveis via `transport()` |

---

## 3. Fatos sobre a API

Ponto de partida, verificado contra as specs OpenAPI (`docs/api/specs/`, baixadas em 15/10/2026) e contra o portal real em 15/10/2026. **Reconfirme a cada release** do portal (ver "Próxima release" em `https://docs-proxima.portalunico.siscomex.gov.br/`).

### 3.1 Ambientes
| Ambiente | URL base | Acesso |
|---|---|---|
| Produção | `https://portalunico.siscomex.gov.br` | público geral |
| Treinamento / Validação | `https://val.portalunico.siscomex.gov.br` | público geral |
| Homologação | `https://hom.pucomex.serpro.gov.br` | restrito (Serpro) — **não usar** |

- O ambiente de Validação é destinado ao desenvolvimento e aos testes de novas integrações; os cadastros são, em sua maioria, os mesmos de produção, e as operações feitas lá não têm efeito em produção.
- Pode haver manutenção de segunda a sexta, entre 18h e 7h, e nos fins de semana → os testes de integração devem tratar indisponibilidade (503) como teste pulado, sem derrubar a suíte.
- Parada programada diária entre 01h e 03h (`PUCX-ER0401`): não repetir a requisição.
- ✅ **Os códigos de atributos diferem entre Validação e Produção.** Exemplo real: "Categoria de produto - Catálogo Anvisa" é `ATT_14545` em Produção e `ATT_18551` em Validação. Nunca fixe códigos no código; separe as fixtures por ambiente.

### 3.2 Autenticação (spec `plat-auth.json`, base `{ambiente}/portal`)
- **Certificado (mTLS):** `POST /portal/api/autenticar` com header `Role-Type`, apresentando o certificado ICP-Brasil (A1 ou A3) no handshake.
- ✅ **Chaves de Acesso:** `POST /portal/api/autenticar/chave-acesso` com os headers `Role-Type`, `Client-Id` e `Client-Secret`, **sem certificado**. As chaves são geradas pelo usuário (logado com certificado) em *Perfil do usuário → Chaves de acesso*; podem ser PF ou PJ, restritas a perfis, com expiração e revogáveis.
- ✅ **As chaves valem só para o ambiente em que foram geradas:** chaves de Produção são recusadas em Validação com `PLAT-ER8007` ("Par de chaves inválido ou inexistente", código não documentado).
- **IMPEXP só aceita e-CPF** (o e-CNPJ é recusado com `PLAT-ER2008`).
- Resposta de sucesso: headers `Set-Token` (JWT), `X-CSRF-Token` e `X-CSRF-Expiration` (epoch em ms, ✅ presente na prática); o corpo `{token}` repete o CSRF.
- As requisições seguintes exigem `Authorization: <valor de Set-Token>` e `X-CSRF-Token: <valor>`. O `Role-Type` só é exigido na autenticação.
- O `X-CSRF-Token` vale 60 minutos. A documentação diz que ele é renovado a cada resposta, mas ⚠️ na prática as respostas do Catálogo trazem o token renovado e as do Cadastro de Atributos não → capture-o sempre que vier e mantenha o anterior quando não vier.
- **Não são aceitas autenticações sucessivas em intervalo inferior a 60 segundos** (`PLAT-ER2033`; Comunicados Siscomex Sistemas nº 014/2024 e nº 010/2025). Reutilize o token válido e nunca autentique por requisição.
- ⚠️ Indício observado: uma nova autenticação com a **mesma chave** (em outro cliente) invalidou a sessão anterior, que passou a receber 401. Não compartilhe a mesma chave entre sistemas que mantêm sessões próprias.
- Códigos de erro da autenticação: `PUCX-ER0101`/`PUCX-ER0102` (Role-Type ausente/inválido), `PUCX-ER0401` (parada programada), `PLAT-ER2001` (certificado inválido), `PLAT-ER2002`, `PLAT-ER2004` (instabilidade do SUCE), `PLAT-ER2008`, `PLAT-ER2013` (sem representação no perfil), `PLAT-ER2023` (CNPJ sem habilitação), `PLAT-ER2033`, `PLAT-ER3005` (CPF irregular), `PLAT-ER8001`/`PLAT-ER8002` (certificado de equipamento / não habilitado), `PLAT-ER8003` (chave inválida), `PLAT-ER8004`, `PLAT-ER8008` e, observado, `PLAT-ER8007`.

### 3.3 Formato de erro e status
Documentado:
```json
{ "message": "...", "code": "PUCX-ER0101", "tag": "[081454RXF]", "status": 422, "severity": "ERROR" }
```
✅ Observado (422 do Catálogo, ao incluir um produto sem os atributos obrigatórios):
```json
{
  "message": "Problemas encontrados: ", "code": "PUCX-ER0123", "tag": "[CATP-...]", "status": 422,
  "field": null, "path": null, "date": "2026-10-15T18:27:01", "severity": "ERROR",
  "detail": [ { "error": { "message": "O Atributo ATT_5426 para o NCM 84733049 é obrigatório.", "code": "CADA-ER5008", "field": "ATT_5426" } } ],
  "info": { "sistema": "Catálogo de Produtos", "ambiente": "TRE", "usuario": "<CPF do usuário>", "trackerId": "..." }
}
```
- Os itens de `detail` vêm no envelope `error`: exponha-os como lista de erros na `ValidationException`.
- **`info.usuario` traz o CPF do usuário:** não registre o corpo bruto em logs e anonimize-o nas fixtures.
- Status: 200, 201, 204, 206 (consulta parcial do Catálogo), 400, 401, 403, 404, 422 (validação de negócio), 500 e 503. Preserve a `tag` nas exceções: ela é usada em chamados à Central Serpro.

### 3.4 Limites de acesso (rate limit)
- Limite de execuções por funcionalidade em janelas de 1 hora; ao exceder, o bloqueio é progressivo (resto da janela → +1 h → +2 h). Os bloqueios zeram à meia-noite.
- Erro `PUCX-ER1001`, com o horário de liberação na mensagem → mapear para `RateLimitException` expondo o horário de desbloqueio. **Nunca** repetir automaticamente nesse caso.

### 3.5 Catálogo de Produtos (spec `catp.json`, ✅ base `{ambiente}/catp/api`)
- `GET /ext/produto?cpfCnpjRaiz=...` — consultar (lista com `seq`; 206 = resultado parcial)
- `GET /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao}` — detalhar a versão `"1"` ou a retificação `"1.1"`
- `GET /ext/produto/historico/{cpfCnpjRaiz}/{codigo}` — histórico (lista de `{data, versao, situacao, usuario, observacao}`)
- `GET /ext/produto/exportar/{cpfCnpjRaiz}/{exibirDesativados}` — exportação (ZIP)
- `POST /ext/produto/{cpfCnpjRaiz}` — incluir (versão inicial)
- `POST /ext/produto/{cpfCnpjRaiz}/{dataReferencia}` — incluir com vigência retroativa
- `PUT /ext/produto/{cpfCnpjRaiz}/{codigo}` — nova versão
- `PUT /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao}` — retificar a versão (⚠️ corpo idêntico ao da versão → `CATP-ER2021`)
- `PUT /ext/produto/desativar/{cpfCnpjRaiz}/{codigo}` — desativar a versão mais recente
- `POST`/`DELETE /ext/produto/codigo-interno/{cpfCnpjRaiz}/{codigo}/{versao}` — incluir/remover código interno (200 sem corpo)
- `/ext/fabricante/...` e `/ext/operador-estrangeiro/...` — vínculos de fabricante e operador estrangeiro (v1.1)
- `cpfCnpjRaiz` = 8 primeiros caracteres do CNPJ **ou** CPF de 11 dígitos. Aceite também a raiz alfanumérica (CNPJ alfanumérico, vigente desde julho/2026), embora a spec ainda diga `NNNNNNNN`.
- Evento push `catp-prod-desativado` (produto desativado por ofício quando surgem novos atributos obrigatórios; Notícia Siscomex Sistemas nº 004/2026): documentar no README; webhooks ficam **fora do escopo**.

### 3.6 Cadastro de Atributos (spec `cadatributos.json`, ✅ base `{ambiente}/cadatributos/api`)
- `GET /ext/atributo-ncm/{codigo-ncm}` — atributos da NCM; filtros `data`, `modalidade`, `objetivos[]` (PRODUTO, TRATAMENTO_ADMINISTRATIVO, DUIMP) e `orgaosDemandantes[]`. ⚠️ A spec não define o schema da resposta; ✅ observado: `{listaNcm: [{codigoNcm, listaAtributos}], detalhesAtributos: [...]}`, a mesma estrutura do arquivo diário.
- `POST /ext/atributo/consulta-codigo` e `/consulta-nome` — até 100 itens por chamada; ⚠️ a resposta é uma **lista** (a spec diz objeto). São consultas apesar do POST: não as bloqueie como escrita.
- `GET /ext/atributo-ncm/download/{tipo}`, `/ext/atributo-ncm/historico-versoes` e `/ext/atributo/historico-versoes`.
- Arrays de query usam `collectionFormat=multi` (chave repetida: `codigosNCM=1&codigosNCM=2`).
- ✅ Download público, sem autenticação e sem `/ext`: `GET {base}/cadatributos/api/atributo-ncm/download/json?perfil=PUBLICO` (ZIP, ~15 MB de JSON, atualizado diariamente à meia-noite). Oferecido como `atributosPublicos()->baixarPorNcm()` para validar o payload **antes** do envio. Hidrate os DTOs sob demanda: criá-los todos de uma vez estoura `memory_limit=128M`.
- Para o Catálogo, considere só os atributos com objetivo **Produto**, na modalidade e vigência do produto.

### 3.7 Links de referência
- Índice: `https://docs.portalunico.siscomex.gov.br/`
- Introdução/Autenticação: `https://docs.portalunico.siscomex.gov.br/introducao-api-publica/` e `/api/plat/` (spec `plat-auth.json`)
- Catálogo de Produtos: `/api/catp/` (spec `catp.json`)
- Cadastro de Atributos: `/api/cada/` (spec `cadatributos.json`)
- Ambientes, Perfis, Chaves e Limites: `/ambientes/`, `/pages/perfis-acesso/`, `/pages/chaves-acesso/`, `/pages/limites-acesso/`
- Tabelas de domínio: `/pages/tabelas_dominio/`
- Integre seu sistema (downloads de NCM/atributos): `https://www.gov.br/siscomex/pt-br/sistemas-de-comercio-exterior/integre-seu-sistema`

> Observação técnica: o site da documentação às vezes entra em loop de redirecionamento sem a barra final; acrescentar `index.html` à URL resolve. As páginas Swagger carregam a spec via JavaScript — os arquivos ficam ao lado da página (ex.: `https://docs.portalunico.siscomex.gov.br/api/catp/catp.json`); veja `docs/api/specs/README.md`.

---

## 4. Credenciais no ambiente de Validação

O ambiente de Validação é público, mas **não é um sandbox com credenciais fictícias**: usa as habilitações reais do usuário (e-CPF do responsável habilitado no perfil IMPEXP para o CNPJ raiz).

Respostas confirmadas em 15/10/2026 (em um projeto novo, reconfirme e anote em `docs/PROGRESSO.md`):
1. Login manual em Validação com certificado — não foi necessário: com chaves de acesso geradas em Validação, a autenticação IMPEXP funcionou.
2. ✅ As Chaves de Acesso precisam ser geradas **dentro** do ambiente de Validação; as de Produção são recusadas (`PLAT-ER8007`).
3. ✅ No modo Chaves de Acesso, **não** é exigido certificado no handshake TLS.
4. Se surgir dúvida remanescente: orientar o usuário a abrir chamado na **Central Serpro de Atendimento** (serviço "PORTAL ÚNICO SISCOMEX – PUCOMEX"), informando a `tag` do erro.

Regras de segurança inegociáveis:
- **Nunca** executar operações de escrita (POST/PUT/DELETE) contra **produção** durante o desenvolvimento ou os testes. Leituras em produção são permitidas.
- Testes de integração reais só rodam com `SISCOMEX_RUN_INTEGRATION=1` **e** `SISCOMEX_AMBIENTE=validacao`; com a flag ativa, o SDK recusa escrita em produção.
- Certificados, senhas, chaves e dados pessoais (CPF) **nunca** entram no Git (`.gitignore` para `credentials.txt`, `*.pfx`, `*.p12`, `*.pem`, `*.key`, `.env`, `storage/`). Antes de cada commit, verifique com `git grep --cached` que nenhum segredo foi incluído.

---

## 5. Arquitetura do SDK

### 5.1 Estrutura (como implementada)
```
src/
  PortalUnicoClient.php          # fachada: atributos(), catalogo()->produtos(), atributosPublicos(), autenticar()
  Config/ (Ambiente, Perfil, ClientConfig, PoliticaIntervaloAutenticacao)
  Auth/
    Credentials/ (Credential, ChaveAcesso, CertificadoA1, CertificadoPemTemporario)
    Authenticator.php            # 60 s, lock, reautenticação
    Session.php                  # JWT + CSRF + expiração
    TokenStore/ (TokenStore, InMemoryTokenStore, FileTokenStore, Psr16TokenStore)
  Http/
    Transport.php                # PSR-18 + PSR-17; headers; captura do CSRF; 401; retry; bloqueio de escrita
    ErrorMapper.php, LogMasker.php, GuzzleClientFactory.php
  Resources/
    Atributos/ (AtributosResource, AtributosPublicos + Dto/)
    Catalogo/  (CatalogoResource, ProdutosResource + Dto/)   # Fabricantes e Operadores Estrangeiros: v1.1
  Validation/ (ValidadorProduto)
  Enum/ ValueObject/ (Ncm, CpfCnpjRaiz) Support/
  Exceptions/ (SiscomexException, AuthenticationException, AuthenticationThrottledException,
               CertificateException, ForbiddenException, NotFoundException, ValidationException,
               RateLimitException, ServiceUnavailableException, ServerException, TransportException,
               ProductionWriteBlockedException)
tests/ (Unit/, Integration/, Fixtures/{validacao,producao}/)
examples/
postman/ (gerado por postman/gerar-colecao.php)
docs/ (PROGRESSO.md, PROMPT-INICIAL.md, api/MAPA-ENDPOINTS.md, api/specs/)
```

### 5.2 Requisitos técnicos
- `declare(strict_types=1)` em todos os arquivos; PSR-4; PER Coding Style.
- Use recursos do PHP 8.4 onde fizer sentido: classes `readonly`, enums com métodos, *property hooks* e visibilidade assimétrica (`public private(set)`) nos DTOs, `new` sem parênteses em encadeamento.
- DTOs imutáveis com `fromArray()`/`toArray()` baseados na spec OpenAPI, tolerantes às divergências observadas (seção 3); nada de arrays soltos na API pública do SDK.
- Datas como `DateTimeImmutable`; NCM (8 dígitos, aceita a forma pontuada) e CPF/CNPJ raiz (CNPJ raiz de 8 posições ou CPF com dígito verificador) como value objects validados.
- **mTLS**: aceitar `.pfx` + senha via opções nativas do cURL (`CURLOPT_SSLCERTTYPE=P12`) ou converter para um PEM temporário com permissão 0600, apagado após o uso. Documentar ambos.
- **Gestão de token**:
  - reaproveitar a sessão válida do cache;
  - substituir o `X-CSRF-Token` sempre que uma resposta trouxer um novo;
  - antes de reautenticar, respeitar o intervalo mínimo de 60 s (aguardar ou lançar exceção, configurável);
  - em 401, reautenticar **uma única vez** e repetir a requisição;
  - lock contra autenticações concorrentes (ex.: `flock` no store de arquivo).
- Retry com backoff apenas para 503 (exceto `PUCX-ER0401`) e para erros de rede em GET (repetir um POST pode duplicar uma inclusão); nunca para 4xx nem para `PUCX-ER1001`.
- Logger PSR-3 opcional, com **mascaramento** de tokens, senhas e chaves.
- Sem dependência de framework. Opcional: seção no README mostrando a integração com Laravel (service provider de exemplo, não incluso no pacote).

### 5.3 Exemplo da API pública (como implementada)
```php
$client = PortalUnicoClient::create(
    ambiente: Ambiente::Validacao,
    credencial: ChaveAcesso::fromEnv(),          // ou new CertificadoA1('/caminho/cert.pfx', getenv('CERT_SENHA'))
    perfil: Perfil::IMPEXP,
    cache: new FileTokenStore(__DIR__.'/storage'),
);

$atributos = $client->atributos()->porNcm('84733049', modalidade: Modalidade::Importacao);

$novo = new ProdutoRequest('Placa-mãe', Modalidade::Importacao, '84733049')
    ->comAtributo('ATT_XXXX', '99');            // códigos do ambiente em uso

$produto = $client->catalogo()->produtos()->incluir(cpfCnpjRaiz: '12345678', produto: $novo);
```

---

## 6. Qualidade e testes

- PHPUnit (versão estável atual compatível com PHP 8.4), PHPStan no nível máximo, PHP-CS-Fixer; Rector opcional.
- **Testes unitários** com cliente HTTP mockado e fixtures extraídas da spec e de respostas reais anonimizadas (cobertura ≥ 85% em `src/`).
- **Testes de integração** em `tests/Integration`, desligados por padrão (ver seção 4), somente contra Validação; indisponibilidade = teste pulado.
- Testes obrigatórios: renovação do CSRF, intervalo mínimo de 60 s, reautenticação em 401, mapeamento de cada código de erro (inclusive o envelope `detail[].error`), rate limit, mascaramento de log, validação de NCM/CPF/CNPJ.
- `composer.json` com os scripts `test`, `test:integration`, `analyse`, `cs:check`, `cs:fix` e `qa` (todos juntos).
- GitHub Actions: matriz PHP 8.4 (e 8.5 como *allow-failure*), rodando `composer validate --strict` e `composer qa`.

---

## 7. Coleção Postman

Gerar em `postman/` (de preferência a partir de um script versionado, ex.: `postman/gerar-colecao.php`):
- `Siscomex-PortalUnico.postman_collection.json` (formato v2.1) com as pastas **Autenticação**, **Cadastro de Atributos**, **Catálogo de Produtos** e **Fluxo completo**.
- `Validacao.postman_environment.json` e `Producao.postman_environment.json` (produção só com consultas; requisições de escrita marcadas com **[ESCRITA]** no nome).
- Variáveis: `baseUrl`, `roleType=IMPEXP`, `authMode` (`chave`|`certificado`), `clientId`/`clientSecret` (*secret*), `cnpjRaiz`, `ncm`, `modalidade`, `codigoProduto`, `versao`, `permitirEscrita`, `jwt`, `csrfToken`, `csrfExpiration`, `lastAuthAt`.
- **Pre-request script em nível de coleção**: autentica apenas se não houver token válido; respeita 60 s desde `lastAuthAt`; com `permitirEscrita != true`, **pula** as requisições de escrita. ⚠️ No Newman, um erro lançado no pre-request **não** impede o envio: use `pm.execution.skipRequest()` e, por garantia, troque a URL por um host `.invalid`. Teste o bloqueio contra um endereço inalcançável.
- **Test script em nível de coleção**: captura `Set-Token`, `X-CSRF-Token` e `X-CSRF-Expiration` de toda resposta e atualiza o ambiente; valida o formato de erro e registra a `tag`.
- Cada requisição com testes (status esperado, presença de campos-chave, formato de erro) e exemplos de body documentados.
- A pasta "Fluxo completo" encadeia: buscar os atributos da NCM (monta o produto com os obrigatórios) → incluir → detalhar → nova versão → histórico → desativar.
- `postman/README.md` explicando: preencher as chaves em **Current value** (não sincronizado); configurar o certificado em *Settings → Certificates* (host do ambiente, arquivo PFX + senha); que o **certificado A3 não funciona no Postman**; e como rodar via Newman ou Postman CLI.

---

## 8. Documentação

### README.md
Instalação via Composer, requisitos (PHP 8.4, ext-json, ext-zip, ext-curl e ext-openssl para certificado), pré-requisitos no Siscomex (habilitação, perfil IMPEXP, e-CPF, chaves por ambiente), configuração de credenciais (Chaves de Acesso e certificado A1), exemplos para **cada** operação implementada, tratamento de erros, cache de token e regra dos 60 s, rate limit, ambientes e a diferença de códigos de atributos entre eles, uso da coleção Postman, como contribuir e links oficiais. Em português.

### CLAUDE.md (raiz, versionado)
Deve conter, de forma enxuta e prática:
- Visão geral do projeto e escopo atual (e o que está fora do escopo);
- Stack e versões; estrutura de pastas;
- Comandos (`composer install`, `composer qa`, `composer test`, como rodar a integração, Newman);
- Convenções de código e de nomes (domínio em português, como na API; infraestrutura técnica em inglês — decisão registrada), commits (Conventional Commits em português) e branches;
- Regras inegociáveis (seção 4 deste prompt, segredos fora do Git, não inventar endpoints, consultar `docs/api/specs/` antes de alterar um recurso);
- **Ritual de sessão**: no início → `git pull`, ler `CLAUDE.md` e `docs/PROGRESSO.md`; no fim → atualizar `docs/PROGRESSO.md`, commit e `git push`.

### docs/PROGRESSO.md
Modelo:
```
# Progresso
Última atualização: AAAA-MM-DD HH:MM — máquina: <casa|trabalho>
## Status atual (1 parágrafo)
## Próximo passo exato
## Feito
- [x] ...
## Falta
- [ ] ...
## Decisões (data — decisão — motivo)
## Dúvidas em aberto / bloqueios
## Pontos verificados na documentação (com link e data)
```

### Outros
`CHANGELOG.md` (Keep a Changelog), `LICENSE`, `.env.example` (sem valores reais), `docs/api/specs/` com as specs OpenAPI baixadas e a data do download, `docs/api/MAPA-ENDPOINTS.md`.

---

## 9. Publicação no Packagist (última fase)

1. Validar o `composer.json` (`name`, `description`, `type: library`, `license`, `authors`, `keywords`, `autoload`, `require`, `suggest` para Guzzle/Symfony Cache, `support`); conferir se o vendor é o que o usuário já usa no Packagist;
2. `composer validate --strict`;
3. Tag semântica `v0.1.0` (pré-1.0 enquanto a API pública puder mudar) e release no GitHub com as notas do CHANGELOG;
4. Orientar o usuário a cadastrar o repositório em packagist.org e ativar a atualização automática (integração do GitHub ou webhook `https://packagist.org/api/github?username=<usuário>` com o API Token como *secret*) — exige o login do usuário;
5. Atualizar o README com o badge e o comando de instalação.

---

## 10. Ordem de execução (com checkpoints)

Ao fim de **cada** fase: atualizar `docs/PROGRESSO.md`, fazer commit e informar o usuário. Pare e pergunte sempre que algo na spec contradisser este prompt.

- **Fase 0 — Preparação:** confirmar os parâmetros da seção 2 com o usuário; `git init`; criar `CLAUDE.md`, `docs/PROGRESSO.md` e `.gitignore`. Baixar as specs OpenAPI (plat, catp, cada) para `docs/api/specs/`. Se a rede bloquear, pedir ao usuário para baixá-las e anexar.
- **Fase 1 — Levantamento:** a partir das specs, produzir `docs/api/MAPA-ENDPOINTS.md` (método, path, parâmetros, body, respostas, erros, perfil exigido) apenas para Autenticação, Atributos e Catálogo. Revisar com o usuário o que entra na v1.
- **Fase 2 — Núcleo:** config, enums, credenciais, transporte HTTP, autenticação, token store, mapeamento de erros + testes unitários.
- **Fase 3 — Cadastro de Atributos** + testes.
- **Fase 4 — Catálogo de Produtos** + testes.
- **Fase 5 — Coleção Postman** + README do Postman.
- **Fase 6 — Validação real:** somente após confirmar a seção 4 (chaves geradas em Validação e CNPJ raiz de teste); rodar o Postman e os testes de integração em Validação; registrar os resultados e os formatos de resposta observados.
- **Fase 7 — Documentação final:** README, exemplos, CHANGELOG.
- **Fase 8 — CI e Packagist.**

---

## 11. Regras gerais para o agente

- **Não invente** endpoints, campos ou códigos de erro: tudo deve vir das specs, da documentação oficial ou de respostas reais observadas, com a fonte anotada em `docs/PROGRESSO.md`. O que não estiver documentado vira `TODO(confirmar)`, com pergunta ao usuário.
- Faça perguntas objetivas quando houver ambiguidade que mude o design ou decisões difíceis de reverter (ex.: o nome do pacote no Packagist); para o resto, escolha o padrão mais convencional e registre a decisão.
- Commits pequenos e frequentes; nunca commitar segredos nem dados pessoais.
- Linguagem de toda a documentação: português do Brasil.

## 12. Critérios de aceite

Estado em 16/10/2026:

- [x] `composer qa` passa sem erros (testes, PHPStan no nível máximo, estilo).
- [x] Autenticação IMPEXP funcionando em Validação com o método de credencial escolhido (Chaves de Acesso).
- [x] Consulta de atributos por NCM e ciclo completo do produto (incluir → consultar → versionar → desativar) executados em Validação, via SDK e via Postman.
- [x] Nenhuma autenticação repetida em menos de 60 s; CSRF renovado sempre que o portal o devolve.
- [x] README, CLAUDE.md e docs/PROGRESSO.md completos e atualizados.
- [x] Pacote instalável via `composer require marcelofecchio/siscomex` — `v0.1.0` publicada no Packagist, com atualização automática por webhook (instalação testada em projeto limpo em 16/10/2026).
