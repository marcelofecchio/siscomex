# Progresso

Última atualização: 2026-10-15 18:35 — máquina: casa

## Status atual

SDK v0.1 implementado e **validado no portal real**: em Validação, autenticação IMPEXP com chaves de acesso, consulta de atributos e o ciclo completo do produto (incluir → consultar → detalhar → nova versão → histórico → código interno → desativar) passaram via SDK (`composer test:integration`, 5/5) e via Postman/Newman ("Fluxo completo", 10/10 asserções). Em Produção, apenas leituras (autenticação + atributos). `composer qa` verde (169 testes unitários). Repositório público em github.com/marcelofecchio/siscomex com CI verde (PHP 8.4 e 8.5).

## Próximo passo exato

Fase 8: criar a tag `v0.1.0`, cadastrar o repositório no Packagist e ativar o webhook do GitHub; depois atualizar o README (badge e `composer require` sem repositório VCS). Em seguida, v1.1 (Fabricantes e Operadores Estrangeiros).

## Feito

- [x] Fase 0 — repositório Git, CLAUDE.md, PROGRESSO.md, .gitignore; specs OpenAPI baixadas para `docs/api/specs/`
- [x] Fase 1 — `docs/api/MAPA-ENDPOINTS.md`
- [x] Fase 2 — núcleo: Ambiente/Perfil/ClientConfig, credenciais, Transport (CSRF, 401, retry 503/rede, bloqueio de escrita), Authenticator (60 s, lock), TokenStores (memória, arquivo, PSR-16), ErrorMapper, LogMasker
- [x] Fase 3 — Cadastro de Atributos: porNcm, porCodigos, porNomes, downloadPorNcm, históricos, download público; DTOs; ValidadorProduto
- [x] Fase 4 — Catálogo de Produtos: consultar, detalhar, histórico, incluir (inclusive retroativo), novaVersao, retificar, desativar, exportar, códigos internos
- [x] Fase 5 — Coleção Postman + ambientes + README (testado com Newman em Produção, só leitura; bloqueio de escrita verificado sem tráfego)
- [x] Fase 6 — Validação: autenticação, atributos e ciclo completo do produto via SDK e Postman; Produção: só leitura
- [x] Fase 7 — README, exemplos, CHANGELOG
- [x] Fase 8 (parcial) — GitHub Actions; repositório público `marcelofecchio/siscomex`

## Falta

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

- Chaves por ambiente — **confirmado**: as chaves de Produção são recusadas em Validação com `PLAT-ER8007` ("Par de chaves inválido ou inexistente"); com chaves geradas em Validação a autenticação funciona. `credentials.txt` (fora do Git) contém os dois pares e o CNPJ de teste.
- Modo chave dispensa certificado no handshake — **confirmado** nos dois ambientes.
- Login manual em Validação com certificado — não foi necessário (as chaves de Validação funcionaram com perfil IMPEXP).
- TODO(confirmar): formato dos itens de `atributosCompostosMultivalorados[].valores` (spec: `array[array]`) — a NCM de teste não tem atributos compostos.
- TODO(confirmar): formato do horário na mensagem de `PUCX-ER1001` (SDK aceita `HH:MM[:SS]` e `dd/MM/yyyy HH:MM`) — limite não atingido.

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
- 2026-10-15 — Erro 422 do CATP (Validação): `{message: "Problemas encontrados: ", code: "PUCX-ER0123", tag, detail: [{error: {message, code: "CADA-ER5008", field: "ATT_x", ...}}]}` — cada item de `detail` vem no envelope `error` (bug corrigido no `ErroDetalhe`). O campo `info.usuario` traz o CPF do usuário: não logar o corpo bruto.
- 2026-10-15 — `consulta-codigo` devolve **lista** de `ArquivoDiarioAtributoDTO` (a spec indica objeto).
- 2026-10-15 — As respostas do CATP renovam `X-CSRF-Token`; as do CADA (`atributo-ncm`, `consulta-codigo`) não renovam.
- 2026-10-15 — Incluir/detalhar/desativar devolvem `ProdutoIntegracaoResponseDTO` com `codigo` inteiro e `versao` "1"; consulta (`GET /ext/produto`) devolve lista com `seq`; histórico devolve lista de `{data, versao, situacao, usuario, observacao}`; incluir código interno → 200 sem corpo.
- 2026-10-15 — Retificar com corpo idêntico ao da versão → 422 `CATP-ER2021` "Já existe produto idêntico cadastrado"; nova versão (PUT sem versão) com corpo idêntico foi aceita.
- 2026-10-15 — Produtos de teste criados **e desativados** em Validação no CNPJ de teste: códigos 1 (teste de integração), 2 (inspeção de respostas) e 3 (fluxo Postman).
