# PROMPT — SDK PHP 8.4 para a API Pública do Portal Único Siscomex
### Escopo: Cadastro de Atributos + Catálogo de Produtos (perfil IMPEXP)

> **Como usar:** coloque este arquivo na raiz de um repositório Git vazio e peça ao agente (ex.: Claude Code):
> *"Leia PROMPT-SDK-SISCOMEX.md e execute a Fase 0."*
> Nas sessões seguintes (em qualquer máquina): *"Leia CLAUDE.md e docs/PROGRESSO.md e continue de onde parou."*

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

## 2. Parâmetros do projeto (confirmar com o usuário na Fase 0)

| Parâmetro | Valor padrão sugerido | Status |
|---|---|---|
| Nome no Packagist (`vendor/pacote`) | `mfecchio/siscomex-portalunico-sdk` | ⚠️ confirmar |
| Namespace raiz | `MFecchio\Siscomex\PortalUnico` | ⚠️ confirmar |
| Versão mínima do PHP | `^8.4` | definido |
| Cliente HTTP | PSR-18 (abstração) + Guzzle 7 como implementação padrão sugerida | ⚠️ confirmar |
| Cache de tokens | PSR-16 (SimpleCache), com implementação em arquivo/memória inclusa | definido |
| Licença | MIT | ⚠️ confirmar |
| Método de autenticação | Suportar **ambos**: certificado digital A1 (.pfx/.pem) e Chaves de Acesso | ⚠️ confirmar qual o usuário possui |
| Tipo de certificado do usuário | A1 (arquivo) ou A3 (token/cartão) | ⚠️ perguntar — A3 não funciona em Postman/servidor |
| Repositório remoto | GitHub (público, requisito do Packagist) | ⚠️ confirmar |

Registre as respostas em `docs/PROGRESSO.md` → seção "Decisões".

---

## 3. Fatos já levantados na documentação (verificados em 15/10/2026)

Use como ponto de partida, **mas confirme tudo contra as especificações OpenAPI na Fase 0** — a documentação muda a cada release (ver "Próxima release" em `https://docs-proxima.portalunico.siscomex.gov.br/`).

### 3.1 Ambientes
| Ambiente | URL base | Acesso |
|---|---|---|
| Produção | `https://portalunico.siscomex.gov.br` | público geral |
| Treinamento / Validação | `https://val.portalunico.siscomex.gov.br` | público geral |
| Homologação | `https://hom.pucomex.serpro.gov.br` | restrito (Serpro) — **não usar** |

- O ambiente de Validação é destinado ao desenvolvimento e testes de novas integrações; os cadastros são, em sua maioria, os mesmos de produção.
- Pode ter manutenção de seg–sex entre 18h e 7h e aos fins de semana → testes de integração devem tratar indisponibilidade (503) sem falhar a suíte inteira.
- **Os códigos e vínculos de atributos no ambiente de treinamento são diferentes dos de produção.** O SDK nunca deve ter códigos de atributo fixos no código; fixtures de teste devem ser separados por ambiente.

### 3.2 Autenticação
- Base em SSL/TLS com **certificado digital ICP-Brasil (A1 ou A3)** apresentado no handshake (mTLS).
- `POST {base}/portal/api/autenticar` com header **`Role-Type: IMPEXP`**.
- **IMPEXP só aceita e-CPF** (certificado de pessoa física), não e-CNPJ.
- Resposta traz nos headers: `Set-Token` (JWT), `X-CSRF-Token`, `X-CSRF-Expiration` (epoch em ms).
- Toda requisição seguinte exige `Authorization: <valor de Set-Token>` e `X-CSRF-Token: <valor>`.
- O `X-CSRF-Token` vale 60 minutos e **é renovado a cada resposta** — o SDK deve sempre capturar o novo valor do header de cada resposta e substituir o anterior.
- **Não são aceitas autenticações sucessivas em intervalo inferior a 60 segundos** (Comunicado Siscomex Sistemas nº 014/2024 e nº 010/2025). O SDK deve reutilizar o token válido e nunca autenticar por requisição.
- **Chaves de Acesso** (alternativa ao certificado para sistemas de terceiros): par de chaves gerado pelo próprio usuário, logado com certificado, em *Perfil do usuário → Chaves de acesso*; pode ser PF ou PJ, restrito a perfis, com tempo de expiração e revogável. Os nomes exatos dos headers (provavelmente `Client-Id` / `Client-Secret`) e se o mTLS continua exigido nesse modo **devem ser confirmados** na spec de Autenticação. Erro `PLAT-ER8003` = chave inválida.
- Erros de autenticação conhecidos: `PUCX-ER0101`, `PUCX-ER0102` (Role-Type ausente/inválido), `PLAT-ER2001` (certificado inválido), `PLAT-ER2002`, `PLAT-ER2004` (instabilidade SUCE), `PLAT-ER8002` (certificado não habilitado), `PLAT-ER8003`.

### 3.3 Formato de erro e status
```json
{ "message": "...", "code": "PUCX-ER0101", "tag": "[081454RXF]", "status": 422, "severity": "ERROR" }
```
Status: 200, 201, 204, 400, 401, 403, 404, 422 (validação de negócio), 500, 503. A `tag` deve ser preservada nas exceções (é usada em chamados à Central Serpro).

### 3.4 Limites de acesso (rate limit)
- Limite de execuções por funcionalidade em janelas de 1 hora; ao exceder, bloqueio progressivo (resto da janela → +1h → +2h). Bloqueios zeram à meia-noite.
- Erro `PUCX-ER1001` com o horário de liberação na mensagem → mapear para `RateLimitException` expondo o horário de desbloqueio. **Nunca** fazer retry automático nesse caso.

### 3.5 Catálogo de Produtos (CATP) — endpoints conhecidos (Notícia Siscomex Sistemas nº 004/2026)
Prefixo provável: `/catp/api` (confirmar na spec).
- `POST /ext/produto/{cpfCnpjRaiz}` — incluir produto (versão inicial)
- `POST /ext/produto/{cpfCnpjRaiz}/{dataReferencia}` — incluir com vigência retroativa
- `PUT /ext/produto/{cpfCnpjRaiz}/{codigo}` — nova versão
- `PUT /ext/produto/{cpfCnpjRaiz}/{codigo}/{versao}` — retificar versão
- `PUT /ext/produto/desativar/{cpfCnpjRaiz}/{codigo}` — desativar versão mais recente
- `GET/POST/PUT /ext/fabricante/...` e `.../ext/operador-estrangeiro/...` — vínculos de fabricante e operador estrangeiro
- Consultas de produto (listagem/detalhe/exportação): levantar na spec.
- Evento push `catp-prod-desativado` (produto desativado por ofício quando surgem novos atributos obrigatórios) — documentar no README; implementação de webhooks fica **fora do escopo** inicial.

### 3.6 Cadastro de Atributos (CADA)
- Prefixo provável: `/cadatributos/api` (confirmar).
- Existe download público (sem autenticação) de atributos por NCM: `GET {base}/cadatributos/api/atributo-ncm/download/json?perfil=PUBLICO` (ZIP/JSON, atualizado diariamente à meia-noite). Avaliar oferecer no SDK como `AtributosPublicos::baixarPorNcm()` — útil para validar payload de produto **antes** de enviar.
- Levantar na spec: consulta de atributos por NCM, detalhe de atributo, domínios, atributos condicionados, modalidade (importação/exportação), obrigatoriedade e vigência.

### 3.7 Links de referência
- Índice: `https://docs.portalunico.siscomex.gov.br/`
- Introdução/Autenticação: `https://docs.portalunico.siscomex.gov.br/introducao-api-publica/` e `/api/plat/` (Swagger: `swagger-51c654fd.html`)
- Catálogo de Produtos: `/api/catp/` (Swagger: `swagger-d5e7b137.html`)
- Cadastro de Atributos: `/api/cada/` (Swagger: `swagger-f68672de.html`)
- Ambientes, Perfis, Chaves e Limites: `/ambientes/`, `/pages/perfis-acesso/`, `/pages/chaves-acesso/`, `/pages/limites-acesso/`
- Tabelas de domínio: `/pages/tabelas_dominio/`
- Integre seu sistema (downloads de NCM/atributos): `https://www.gov.br/siscomex/pt-br/sistemas-de-comercio-exterior/integre-seu-sistema`

> Observação técnica: o site da documentação às vezes entra em loop de redirecionamento sem barra final; acrescentar `index.html` ao final da URL resolve. As páginas Swagger são renderizadas via JavaScript — localize no código-fonte a URL do arquivo OpenAPI (JSON/YAML) e baixe-o.

---

## 4. Dúvida crítica: credenciais no ambiente de Validação

**Responda e registre isto antes de qualquer teste real.**

O que a documentação indica:
- O ambiente de Validação é público, mas **não é um sandbox com credenciais fictícias**. A autenticação exige certificado ICP-Brasil válido e reconhecido pelo Serpro e consulta a base de habilitações do usuário → na prática, usa-se **o e-CPF real do responsável/representante habilitado no Siscomex com perfil IMPEXP** para a empresa (CNPJ raiz).
- Os dados cadastrais de Validação espelham, em grande parte, os de produção, mas as operações feitas lá **não produzem efeito em produção**.

O que o agente deve confirmar (e anotar em `docs/PROGRESSO.md`):
1. Login manual em `https://val.portalunico.siscomex.gov.br` com o certificado do usuário funciona e mostra o perfil IMPEXP para o CNPJ desejado?
2. As Chaves de Acesso precisam ser geradas **dentro** do ambiente de Validação (independentes das de produção)? (Hipótese: sim — confirmar.)
3. No modo Chaves de Acesso, o certificado no handshake TLS ainda é exigido?
4. Se houver dúvida remanescente: orientar o usuário a abrir chamado na **Central Serpro de Atendimento** (serviço "PORTAL ÚNICO SISCOMEX – PUCOMEX").

Regras de segurança inegociáveis:
- **Nunca** executar operações de escrita (POST/PUT) contra **produção** durante o desenvolvimento ou testes.
- Testes de integração reais só rodam com variável explícita `SISCOMEX_RUN_INTEGRATION=1` e `SISCOMEX_AMBIENTE=validacao`; o SDK deve recusar escrita em produção quando a flag de teste estiver ativa.
- Certificados, senhas e chaves **nunca** entram no Git (`.gitignore` para `*.pfx`, `*.p12`, `*.pem`, `*.key`, `.env`, `storage/`).

---

## 5. Arquitetura do SDK

### 5.1 Estrutura sugerida
```
src/
  PortalUnicoClient.php          # fachada: ->atributos(), ->catalogo()
  Config/ (Ambiente enum, Perfil enum, ClientConfig)
  Auth/
    Credentials/ (CertificadoA1, ChaveAcesso)  # implementam interface Credential
    Authenticator.php
    TokenStore.php               # PSR-16; guarda JWT, CSRF, expiração, último login
    Session.php                  # value object
  Http/
    Transport.php                # PSR-18 + PSR-17; injeta headers; captura CSRF renovado
    ErrorMapper.php
  Resources/
    Atributos/ (AtributosResource + DTOs)
    Catalogo/  (ProdutosResource, FabricantesResource*, OperadoresEstrangeirosResource* + DTOs)
  Exceptions/ (SiscomexException, AuthenticationException, ForbiddenException,
               ValidationException(422, com lista de erros), NotFoundException,
               RateLimitException, ServiceUnavailableException, CertificateException)
tests/ (Unit/, Integration/, Fixtures/{validacao,producao}/)
examples/
postman/
docs/ (PROGRESSO.md, api/specs/*.json, ADRs opcionais)
```
\* Fabricantes e Operadores Estrangeiros: confirmar com o usuário se entram na v1 ou ficam para a v1.1.

### 5.2 Requisitos técnicos
- `declare(strict_types=1)` em todos os arquivos; PSR-4; PER Coding Style.
- Use recursos do PHP 8.4 onde fizer sentido: classes `readonly`, enums com métodos, *property hooks* e visibilidade assimétrica (`public private(set)`) nos DTOs, `new` sem parênteses em encadeamento.
- DTOs imutáveis com `fromArray()`/`toArray()` gerados a partir da spec OpenAPI; nada de arrays soltos na API pública do SDK.
- Datas como `DateTimeImmutable`; NCM e CNPJ raiz como value objects validados (8 dígitos).
- **mTLS**: aceitar `.pfx` + senha; converter para PEM em diretório temporário seguro (permissão 0600) e apagar após uso, ou usar opções nativas do cURL (`CURLOPT_SSLCERTTYPE=P12`). Documentar ambos.
- **Gestão de token**:
  - reaproveitar sessão válida do cache;
  - renovar `X-CSRF-Token` a partir de **cada** resposta;
  - antes de reautenticar, respeitar o intervalo mínimo de 60 s (aguardar ou lançar exceção, configurável);
  - em 401, reautenticar **uma única vez** e repetir a requisição.
  - lock para evitar autenticações concorrentes (ex.: `flock` no store de arquivo).
- Retry com backoff apenas para 503/erros de rede, nunca para 4xx nem `PUCX-ER1001`.
- Logger PSR-3 opcional, com **mascaramento** de tokens, senhas e chaves.
- Sem dependência de framework. Opcional: seção no README mostrando integração com Laravel (service provider de exemplo, não incluso no pacote).

### 5.3 Exemplo de API pública desejada (ilustrativo — ajustar à spec)
```php
$client = PortalUnicoClient::create(
    ambiente: Ambiente::Validacao,
    credencial: new CertificadoA1('/caminho/cert.pfx', getenv('CERT_SENHA')),
    perfil: Perfil::IMPEXP,
    cache: new FileTokenStore(__DIR__.'/storage'),
);

$atributos = $client->atributos()->porNcm('84733049');
$produto   = $client->catalogo()->produtos()->incluir(cnpjRaiz: '12345678', produto: $novoProduto);
```

---

## 6. Qualidade e testes

- PHPUnit (versão estável atual compatível com PHP 8.4), PHPStan nível máximo, PHP-CS-Fixer, Rector opcional.
- **Testes unitários** com cliente HTTP mockado e fixtures extraídas dos exemplos da spec (cobertura ≥ 85% em `src/`).
- **Testes de integração** em `tests/Integration`, desligados por padrão (ver seção 4), somente contra Validação.
- Testes obrigatórios: renovação de CSRF, intervalo mínimo de 60 s, reautenticação em 401, mapeamento de cada código de erro, rate limit, mascaramento de log, validação de NCM/CNPJ.
- `composer.json` com scripts: `test`, `test:integration`, `analyse`, `cs:check`, `cs:fix`, `qa` (todos juntos).
- GitHub Actions: matriz PHP 8.4 (e 8.5 como *allow-failure*), rodando `composer qa`.

---

## 7. Coleção Postman

Gerar em `postman/`:
- `Siscomex-PortalUnico.postman_collection.json` (formato v2.1) com pastas: **Autenticação**, **Cadastro de Atributos**, **Catálogo de Produtos**.
- `Validacao.postman_environment.json` e `Producao.postman_environment.json` (produção apenas com consultas GET habilitadas; requisições de escrita marcadas com aviso no nome).
- Variáveis: `baseUrl`, `roleType=IMPEXP`, `cnpjRaiz`, `ncm`, `codigoProduto`, `versao`, `jwt`, `csrfToken`, `csrfExpiration`, `lastAuthAt`, e (se aplicável) `clientId`/`clientSecret` como *secret*.
- **Pre-request script em nível de coleção**: autentica apenas se não houver token válido; respeita 60 s desde `lastAuthAt`.
- **Test script em nível de coleção**: captura `Set-Token`, `X-CSRF-Token` e `X-CSRF-Expiration` de toda resposta e atualiza o ambiente.
- Cada requisição com testes (status esperado, presença de campos-chave, formato de erro) e exemplos de body documentados.
- Uma pasta "Fluxo completo" encadeando: autenticar → buscar atributos da NCM → incluir produto → consultar → nova versão → desativar.
- `postman/README.md` explicando: configurar certificado em *Settings → Certificates* (host `val.portalunico.siscomex.gov.br`, arquivo PFX + senha), que **certificado A3 não funciona no Postman**, e como rodar via Newman.

---

## 8. Documentação

### README.md
Instalação via Composer, requisitos (PHP 8.4, ext-curl, ext-openssl, ext-json), pré-requisitos no Siscomex (habilitação, perfil IMPEXP, e-CPF), configuração de credenciais (certificado A1 e Chaves de Acesso), exemplos para **cada** operação implementada, tratamento de erros, cache de token e regra dos 60 s, rate limit, ambientes, diferença de códigos de atributos entre ambientes, uso da coleção Postman, como contribuir, links oficiais. Em português.

### CLAUDE.md (raiz, versionado)
Deve conter, de forma enxuta e prática:
- Visão geral do projeto e escopo atual (e o que está fora do escopo);
- Stack e versões; estrutura de pastas;
- Comandos (`composer install`, `composer qa`, `composer test`, como rodar integração, Newman);
- Convenções de código, nomes (domínio em português, como na API; código técnico em inglês ou português — decidir e registrar), commits (Conventional Commits em português), branches;
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
`CHANGELOG.md` (Keep a Changelog), `LICENSE`, `.env.example` (sem valores reais), `docs/api/specs/` com as specs OpenAPI baixadas e a data de download.

---

## 9. Publicação no Packagist (última fase)

1. Validar `composer.json` (`name`, `description`, `type: library`, `license`, `authors`, `keywords`, `autoload`, `require`, `suggest` para Guzzle/Symfony Cache, `support`);
2. `composer validate --strict`;
3. Tag semântica `v0.1.0` (pré-1.0 enquanto a API pública puder mudar);
4. Orientar o usuário a cadastrar o repositório em packagist.org e ativar o webhook do GitHub;
5. Atualizar README com o badge e o comando de instalação.

---

## 10. Ordem de execução (com checkpoints)

Ao fim de **cada** fase: atualizar `docs/PROGRESSO.md`, fazer commit e informar o usuário. Pare e pergunte sempre que algo na spec contradizer este prompt.

- **Fase 0 — Preparação:** confirmar parâmetros da seção 2 com o usuário; `git init`; criar `CLAUDE.md`, `docs/PROGRESSO.md`, `.gitignore`. Baixar as specs OpenAPI (plat, catp, cada) para `docs/api/specs/`. Se a rede do ambiente bloquear, pedir ao usuário para baixá-las e anexar.
- **Fase 1 — Levantamento:** a partir das specs, produzir `docs/api/MAPA-ENDPOINTS.md` (método, path, parâmetros, body, respostas, erros, perfil exigido) apenas para Autenticação, Atributos e Catálogo. Revisar com o usuário o que entra na v1.
- **Fase 2 — Núcleo:** config, enums, credenciais, transporte HTTP, autenticação, token store, mapeamento de erros + testes unitários.
- **Fase 3 — Cadastro de Atributos** + testes.
- **Fase 4 — Catálogo de Produtos** + testes.
- **Fase 5 — Coleção Postman** + README do Postman.
- **Fase 6 — Validação real:** somente após o usuário confirmar a seção 4; rodar Postman e testes de integração em Validação; registrar resultados.
- **Fase 7 — Documentação final:** README, exemplos, CHANGELOG.
- **Fase 8 — CI e Packagist.**

---

## 11. Regras gerais para o agente

- **Não invente** endpoints, campos ou códigos de erro: tudo deve vir das specs ou da documentação oficial, com o link anotado em `docs/PROGRESSO.md`. Se algo não estiver documentado, marque como `TODO(confirmar)` e pergunte.
- Faça perguntas objetivas quando houver ambiguidade que mude o design; para o resto, escolha o padrão mais convencional e registre a decisão.
- Commits pequenos e frequentes; nunca commitar segredos.
- Linguagem de toda a documentação: português do Brasil.

## 12. Critérios de aceite

- [ ] `composer qa` passa sem erros (testes, PHPStan nível máximo, estilo).
- [ ] Autenticação IMPEXP funcionando em Validação com o método de credencial escolhido.
- [ ] Consulta de atributos por NCM e ciclo completo de produto (incluir → consultar → versionar → desativar) executados em Validação, via SDK e via Postman.
- [ ] Nenhuma autenticação repetida em menos de 60 s; CSRF renovado a cada resposta.
- [ ] README, CLAUDE.md e docs/PROGRESSO.md completos e atualizados.
- [ ] Pacote instalável via `composer require <vendor/pacote>`.
