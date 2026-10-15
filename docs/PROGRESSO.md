# Progresso

Última atualização: 2026-10-15 18:00 — máquina: casa

## Status atual

SDK v0.1 implementado: autenticação (Chaves de Acesso e certificado A1), Cadastro de Atributos, Catálogo de Produtos (produtos), validação local, coleção Postman, exemplos, documentação e CI. `composer qa` verde (PHPStan nível max, 168 testes unitários, 96% de cobertura de linhas). Autenticação IMPEXP e consulta de atributos **validadas em Produção (somente leitura)** com as chaves de acesso do usuário, via SDK e via Postman/Newman. O ciclo de escrita do catálogo ainda **não** foi executado em Validação porque as chaves disponíveis são de Produção.

## Próximo passo exato

Gerar um par de **Chaves de Acesso no ambiente de Validação** (`https://val.portalunico.siscomex.gov.br` → Perfil do usuário → Chaves de acesso), informar o **CNPJ raiz** de teste e rodar:

```bash
SISCOMEX_RUN_INTEGRATION=1 SISCOMEX_AMBIENTE=validacao SISCOMEX_CNPJ_RAIZ=<raiz> composer test:integration
```

e a pasta "Fluxo completo (Validação)" da coleção Postman. Depois: Fase 8 (Packagist).

## Feito

- [x] Fase 0 — repositório Git, CLAUDE.md, PROGRESSO.md, .gitignore; specs OpenAPI baixadas para `docs/api/specs/`
- [x] Fase 1 — `docs/api/MAPA-ENDPOINTS.md`
- [x] Fase 2 — núcleo: Ambiente/Perfil/ClientConfig, credenciais, Transport (CSRF, 401, retry 503/rede, bloqueio de escrita), Authenticator (60 s, lock), TokenStores (memória, arquivo, PSR-16), ErrorMapper, LogMasker
- [x] Fase 3 — Cadastro de Atributos: porNcm, porCodigos, porNomes, downloadPorNcm, históricos, download público; DTOs; ValidadorProduto
- [x] Fase 4 — Catálogo de Produtos: consultar, detalhar, histórico, incluir (inclusive retroativo), novaVersao, retificar, desativar, exportar, códigos internos
- [x] Fase 5 — Coleção Postman + ambientes + README (testado com Newman em Produção, só leitura; bloqueio de escrita verificado sem tráfego)
- [x] Fase 6 (parcial) — autenticação e consulta de atributos reais em Produção (leitura)
- [x] Fase 7 — README, exemplos, CHANGELOG
- [x] Fase 8 (parcial) — GitHub Actions; repositório público `marcelofecchio/siscomex`

## Falta

- [ ] Fase 6 — ciclo completo do produto em **Validação** (SDK + Postman) — depende de chaves de Validação e CNPJ raiz
- [ ] Fase 8 — `composer validate --strict`, tag `v0.1.0`, cadastro no Packagist + webhook, badge no README
- [ ] v1.1 — Fabricantes-Produtores e Operadores Estrangeiros
- [ ] Confirmar os itens `TODO(confirmar)` abaixo com respostas reais

## Decisões (data — decisão — motivo)

- 2026-10-15 — Nome do pacote `mfecchio/siscomex-portalunico-sdk`, namespace `MFecchio\Siscomex\PortalUnico`, licença MIT — padrões sugeridos no prompt, sem objeção do usuário.
- 2026-10-15 — Repositório público `github.com/marcelofecchio/siscomex` — pedido do usuário.
- 2026-10-15 — Credencial principal: **Chaves de Acesso** (o usuário forneceu um par); certificado A1 também suportado (mTLS via `CURLOPT_SSLCERTTYPE=P12` ou PEM temporário 0600). A3 não suportado.
- 2026-10-15 — PSR-18 + PSR-17 com `php-http/discovery`; Guzzle 7 recomendado (obrigatório para mTLS) e usado automaticamente quando instalado.
- 2026-10-15 — Cache de sessão via interface `TokenStore` com `InMemoryTokenStore` (padrão), `FileTokenStore` (flock) e `Psr16TokenStore` (lock opcional injetável).
- 2026-10-15 — Regra dos 60 s: política padrão **Aguardar** (bom para CLI/jobs); `LancarExcecao` recomendada para web.
- 2026-10-15 — Retry: 503 (exceto `PUCX-ER0401`) para qualquer método — o serviço não processou; erros de rede **só em GET/HEAD** para não duplicar inclusões.
- 2026-10-15 — `Role-Type` enviado apenas na autenticação (as specs não o exigem nas demais chamadas).
- 2026-10-15 — `cpfCnpjRaiz` aceita CNPJ raiz de 8 posições **alfanuméricas** (CNPJ alfanumérico vigente desde jul/2026) ou CPF de 11 dígitos com DV validado; a spec ainda diz `NNNNNNNN`.
- 2026-10-15 — Fabricantes e Operadores Estrangeiros ficam para a v1.1 (padrão do prompt); disponíveis desde já via `transport()`.
- 2026-10-15 — Fundamento Legal / download geral de atributos fora da v0.1 (não necessários ao catálogo IMPEXP).
- 2026-10-15 — `RelacaoAtributosNcm` hidrata DTOs sob demanda: o arquivo completo (~15 MB) estourava 128 MB quando todos os objetos eram criados de uma vez.
- 2026-10-15 — Idioma: domínio em português, infraestrutura em inglês.
- 2026-10-15 — Postman: bloqueio de escrita usa `pm.execution.skipRequest()` + troca da URL para host `.invalid`, porque no Newman um erro no pre-request **não** impede o envio (verificado).

## Dúvidas em aberto / bloqueios

- **Chaves de Validação**: as chaves de `credentials.txt` são de **Produção** — em Validação a autenticação retorna `PLAT-ER8007 "Par de chaves inválido ou inexistente"` (15/10/2026 17:20, tag `[PLAT-SUXKSU2VFS]`). Confirma a hipótese de que as chaves são por ambiente. Para testes de escrita é preciso gerar chaves em Validação.
- **CNPJ raiz** de teste não informado.
- Seção 4 do prompt: (1) login manual em Validação com o certificado e perfil IMPEXP — **a confirmar pelo usuário**; (2) chaves por ambiente — **confirmado** (acima); (3) modo chave dispensa certificado no handshake — **confirmado** na spec e na prática (autenticação em Produção sem certificado).
- TODO(confirmar): formato dos itens de `atributosCompostosMultivalorados[].valores` (spec: `array[array]`).
- TODO(confirmar): formato dos itens de `detail` no erro 422 do CATP.
- TODO(confirmar): resposta de `consulta-codigo`/`consulta-nome` (spec: objeto; SDK aceita objeto ou lista).
- TODO(confirmar): formato do horário na mensagem de `PUCX-ER1001` (SDK aceita `HH:MM[:SS]` e `dd/MM/yyyy HH:MM`).

## Pontos verificados na documentação (com link e data)

- 2026-10-15 — Specs: `https://docs.portalunico.siscomex.gov.br/api/plat/plat-auth.json`, `/api/catp/catp.json`, `/api/cada/cadatributos.json`.
- 2026-10-15 — Autenticação por chave: `POST /portal/api/autenticar/chave-acesso` com `Client-Id`, `Client-Secret`, `Role-Type`, sem certificado (plat-auth.json; `/pages/chaves-acesso/`).
- 2026-10-15 — IMPEXP: apenas e-CPF (tabela de perfis em plat-auth.json). `PLAT-ER2033` = autenticação há menos de 60 s.
- 2026-10-15 — Ambientes e janela de manutenção de Validação: `https://docs.portalunico.siscomex.gov.br/ambientes/`.
- 2026-10-15 — Limites de acesso / `PUCX-ER1001`: `https://docs.portalunico.siscomex.gov.br/pages/limites-acesso/`.
- 2026-10-15 — `GET /cadatributos/api/ext/atributo-ncm/{ncm}` em Produção retorna `{listaNcm, detalhesAtributos}` (sem `versao`) e **não** devolveu `X-CSRF-Token` renovado nessa resposta.
- 2026-10-15 — Resposta de autenticação em Produção inclui `X-CSRF-Expiration` (≈ 60 min).
- 2026-10-15 — Download público `GET {ambiente}/cadatributos/api/atributo-ncm/download/json?perfil=PUBLICO` funciona sem autenticação em ambos os ambientes (Produção: versão 359; Validação: versão 409).
- 2026-10-15 — Códigos de atributos diferem entre ambientes: "Categoria de produto - Catálogo Anvisa" = `ATT_14545` (Produção) × `ATT_18551` (Validação).
