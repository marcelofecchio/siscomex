# Progresso

Última atualização: 2026-10-17 19:48 — máquina: DESKTOP-NGLSJ67

## Status atual

**v0.3 (não lançada) na branch `feat/erro-api-fabricantes-fake-tabx`**: a partir do segundo `SUGESTOES-SDK.md` (item 1, Excel, descartado pelo usuário) — `ErroApi` + `CodigosErro`, `incluirComFabricantes()` / `vincularTodos()` / lote com fabricantes, `Testing\PortalUnicoFake`, `ClientConfig::$simularEscrita`, Tabelas Comex (`tabelas()`, com paginação real), `Ambiente::janelaDeIndisponibilidade()` e seção de versionamento/APIs `@experimental`. **Validado na Validação em 17/10/2026**: integração 7/7; Postman CLI — "Fluxo operador e fabricante" 8/8 requisições e pasta TABX 9/9 asserções. `composer qa` verde (289 testes unitários).

**v0.2.0 lançada em 17/10/2026** (PR da branch `feat/operadores-fabricantes-e-painel`, CI verde, tag `v0.2.0`, release no GitHub e Packagist): operadores estrangeiros, fabricantes-produtores, busca por código interno, inclusão em lote, atributos condicionados (avaliação + `ValidadorProduto`), cache de atributos, `testarConexao()` e `CertificadoA1::fromString()` — implementados a partir de `SUGESTOES-SDK.md` (levantamento do painel) e **validados na Validação em 17/10/2026** (integração 6/6; Postman CLI nas pastas novas, só leitura, 6/6). `composer qa` verde (252 testes unitários).

SDK v0.1 implementado e **validado no portal real**: em Validação, autenticação IMPEXP com chaves de acesso, consulta de atributos e o ciclo completo do produto (incluir → consultar → detalhar → nova versão → histórico → código interno → desativar) passaram via SDK (`composer test:integration`, 5/5) e via Postman/Newman ("Fluxo completo", 10/10 asserções). Em Produção, apenas leituras (autenticação + atributos). `composer qa` verde (169 testes unitários). Repositório público em github.com/marcelofecchio/siscomex com CI verde (PHP 8.4 e 8.5). **v0.1.1 é a primeira versão com o nome final do pacote** (`composer require marcelofecchio/siscomex-sdk`); a v0.1.0 foi publicada como `marcelofecchio/siscomex`.

## Próximo passo exato

Revisar e mesclar o PR da branch `feat/erro-api-fabricantes-fake-tabx`; lançar **v0.3.0** (CHANGELOG "Não lançado" → `[0.3.0]`, User-Agent `/0.3`, tag + release). Depois, painel em `^0.3`: `ErroApi`/`mensagemAmigavel()` na UI de importação, `incluirComFabricantes()`/lote com fabricantes, `PortalUnicoFake` nos testes, `simularEscrita` na pré-validação, `tabelas()->paises()` no select de país.

## Feito

- [x] Fase 0 — repositório Git, CLAUDE.md, PROGRESSO.md, .gitignore; specs OpenAPI baixadas para `docs/api/specs/`
- [x] Fase 1 — `docs/api/MAPA-ENDPOINTS.md`
- [x] Fase 2 — núcleo: Ambiente/Perfil/ClientConfig, credenciais, Transport (CSRF, 401, retry 503/rede, bloqueio de escrita), Authenticator (60 s, lock), TokenStores (memória, arquivo, PSR-16), ErrorMapper, LogMasker
- [x] Fase 3 — Cadastro de Atributos: porNcm, porCodigos, porNomes, downloadPorNcm, históricos, download público; DTOs; ValidadorProduto
- [x] Fase 4 — Catálogo de Produtos: consultar, detalhar, histórico, incluir (inclusive retroativo), novaVersao, retificar, desativar, exportar, códigos internos
- [x] Fase 5 — Coleção Postman + ambientes + README (testado com Newman em Produção, só leitura; bloqueio de escrita verificado sem tráfego)
- [x] Fase 6 — Validação: autenticação, atributos e ciclo completo do produto via SDK e Postman; Produção: só leitura
- [x] Fase 7 — README, exemplos, CHANGELOG
- [x] Fase 8 — GitHub Actions; repositório público; pacote renomeado para `marcelofecchio/siscomex`; `composer validate --strict`; tag e release `v0.1.0`; publicado no Packagist com webhook de atualização; `composer require marcelofecchio/siscomex` testado em projeto limpo (16/10/2026)
- [x] `docs/PROMPT-INICIAL.md` revisado com os fatos confirmados e os parâmetros definidos (16/10/2026)
- [x] `doc/PROMPT-INICIAL.md` movido para `docs/` (pasta `doc` removida)
- [x] 17/10/2026 — v0.2.0: PR, CI, merge em `main`, tag, release e Packagist
- [x] 17/10/2026 — segundo `SUGESTOES-SDK.md`: itens 2, 3, 4, 5 e os de baixa prioridade viáveis (TABX/países, paginação na TABX, janela de indisponibilidade, política de versionamento); Postman atualizado
- [x] 17/10/2026 — `SUGESTOES-SDK.md` verificado contra as specs e a Validação e implementado (itens 1, 2, 3, 4, 5, 7, 8, 10 e o `CertificadoA1::fromString()`); itens 6 e 9 e parte dos de baixa prioridade descartados com motivo (ver Decisões)

## Falta

- [ ] Painel: usar `operadoresEstrangeiros()`, `fabricantes()`, `buscarPorCodigoInterno()`, `incluirLote()`, `testarConexao()` e `obrigatoriosPara()`; remover as lacunas do SDK do PROGRESSO do painel
- [ ] Confirmar os itens `TODO(confirmar)` abaixo com respostas reais
- [ ] Bateria controlada de escrita em **Produção** antes de o painel gravar lá — decisão e execução do responsável (a regra 1 proíbe escrita em Produção em desenvolvimento/testes; não automatizar)
- [ ] Atualizar o Postman CLI (1.74.0 → 1.75.0): `postman update` falhou nesta máquina ("Não é possível criar um arquivo já existente" — o executável fica em `WindowsApps`); reinstalar pelo instalador oficial com o terminal fechado

## Decisões (data — decisão — motivo)

- 2026-10-15 — Nome do pacote `marcelofecchio/siscomex`, namespace `MarceloFecchio\Siscomex\PortalUnico`, licença MIT — escolha do usuário antes da publicação, alinhada aos pacotes existentes da conta no Packagist (`marcelofecchio/correios-api`, `marcelofecchio/safe2pay-api`); substitui o padrão `mfecchio/siscomex-portalunico-sdk` sugerido no prompt.
- 2026-10-15 — Repositório público `github.com/marcelofecchio/siscomex` — pedido do usuário.
- 2026-10-17 — Repositório renomeado para `siscomex-sdk` e pacote para `marcelofecchio/siscomex-sdk` (pedido do usuário; o Packagist não renomeia pacotes, então o antigo é removido e o novo cadastrado). Namespace inalterado. Tag nova `v0.1.1`, porque o `composer.json` da `v0.1.0` ainda tem o nome antigo.
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

- 2026-10-17 — Fabricante **não** entra no `ProdutoRequest` (a sugestão pedia `comFabricante()`): a spec mostra que `ProdutoIntegracaoRequestDTO` não tem esse campo; o vínculo é `POST/PUT /ext/fabricante/{cpfCnpjRaiz}` → `catalogo()->fabricantes()`.
- 2026-10-17 — `conhecido` segue o nome do campo (true = conhecido), contrariando a descrição da spec, porque a Validação recusou `conhecido=true` sem operador. `FabricanteRequest` só aceita combinações coerentes (conhecido ⇒ exatamente um de operador/CPF-CNPJ; desconhecido ⇒ nenhum).
- 2026-10-17 — `buscarPorCodigoInterno()` filtra igualdade exata no cliente porque o filtro da API é parcial e não diferencia maiúsculas; `SituacaoCodigoInterno` é derivada da consulta (o portal não tem regra de unicidade).
- 2026-10-17 — `incluirLote()` não repete nada (regra 7): 4xx/500 → Erro e segue; 1001/503/401/403 → interrompe com Pendente; falha de rede no POST → Incerto e interrompe. Classificação por status HTTP, porque `ErrorMapper` trata `PUCX-ER0101/0102` como autenticação mesmo num 422 de negócio.
- 2026-10-17 — Paginação ergonômica (item 9) descartada: a API não pagina; 206 significa resultado truncado (`parcial`).
- 2026-10-17 — Planilha-modelo (item 6) fica para um pacote opcional (`siscomex-sdk-excel`), para não pôr `phpoffice/phpspreadsheet` no núcleo; `condicionadosParaProduto()` já fornece o que o gerador precisa.
- 2026-10-17 — Não implementados por ora: modo dry-run global (o bloqueio de escrita + `ValidadorProduto` + `toArray()` cobrem o caso), cliente fake oficial (qualquer PSR-18 mockado serve; `tests/Support/FakeHttpClient` é o modelo) e consultas públicas de países/unidades (não constam das specs).
- 2026-10-17 — Condições de atributos: avaliação da esquerda para a direita (nenhuma condição real mistura `||` e `&&`), lógica de três valores (`null` = indeterminado não exige o condicionado), operadores levantados nas relações públicas dos dois ambientes (`==`, `!=`, `>`, `>=`, `<=`).
- 2026-10-17 — Cache de atributos: respostas brutas de `porNcm()` em PSR-16, chave por ambiente+path+query, TTL até a meia-noite de Brasília (atualização diária); invalidação por `historicoVersoesPorNcm` não foi feita (o TTL diário já acompanha a publicação).
- 2026-10-17 — `CertificadoA1::fromString()`: o identificador da sessão usa o hash do conteúdo (o caminho temporário muda a cada processo e quebraria o reaproveitamento da sessão / regra dos 60 s).

- 2026-10-17 — Item 1 do segundo `SUGESTOES-SDK.md` (Excel) ignorado por pedido do usuário.
- 2026-10-17 — `CodigosErro` só com códigos documentados (plat-auth.json) ou observados; PUCX-ER0101/0102 descritos como genéricos ("campo obrigatório/inválido") porque a spec os usa para Role-Type e o Catálogo para campos do corpo.
- 2026-10-17 — `incluirComFabricantes()` lança os erros da inclusão do produto (como `incluir()`) e só captura os dos vínculos — o estado "erro" da sugestão vira exceção; `Incerto` não entra em `paraReprocessar()` para não duplicar vínculo.
- 2026-10-17 — `Fabricante` (sem código de produto) criado para o fluxo composto; `FabricanteRequest` delega a validação a ele (uma regra só).
- 2026-10-17 — `simularEscrita`: intercepta no `Transport` antes de autenticar; resposta = eco do corpo (200) ou 204; sem códigos do portal, os vínculos ficam `Pendente` sem erro. Tem prioridade sobre o bloqueio de produção (nada é enviado).
- 2026-10-17 — `PortalUnicoFake` em `src/Testing` (autoload normal) e sem depender do Guzzle: usa PSR-17 discovery; responde a autenticação automaticamente; `tentativas503: 0` por padrão.
- 2026-10-17 — TABX somente leitura (as escritas são dos órgãos). `paises()` filtra vigentes por DATA_INICIO/DATA_FIM e ordena por NOME no portal.
- 2026-10-17 — Paginação ergonômica: implementada para a TABX (que pagina); no Catálogo continua não aplicável (206 = truncado) — `parcial` já é uniforme em produtos e operadores.
- 2026-10-17 — Bateria de escrita em Produção não executada: conflita com a regra inegociável 1; fica como pendência do responsável e critério para a 1.0.

## Dúvidas em aberto / bloqueios

- Chaves por ambiente — **confirmado**: as chaves de Produção são recusadas em Validação com `PLAT-ER8007` ("Par de chaves inválido ou inexistente"); com chaves geradas em Validação a autenticação funciona. `credentials.txt` (fora do Git) contém os dois pares e o CNPJ de teste.
- Modo chave dispensa certificado no handshake — **confirmado** nos dois ambientes.
- Login manual em Validação com certificado — não foi necessário (as chaves de Validação funcionaram com perfil IMPEXP).
- TODO(confirmar): formato dos itens de `atributosCompostosMultivalorados[].valores` (spec: `array[array]`) — a NCM de teste não tem atributos compostos.
- TODO(confirmar): exportações de operadores (`/ext/operador-estrangeiro/exportar`), de vínculos de fabricante (`/ext/fabricante/exportar`) e de produto recém-incluído voltaram vazias minutos após a criação (a consulta e o histórico já mostravam os registros) — verificar se são geradas D+1.
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
- 2026-10-15 — Uma sessão em cache, ainda dentro da validade do CSRF, foi recusada com HTTP 401 depois que outro cliente (Newman) autenticou com a **mesma chave**: indício de que uma nova autenticação invalida a sessão anterior. O SDK reautenticou uma vez e seguiu. Não compartilhe a mesma chave entre sistemas que mantêm sessões próprias (TODO(confirmar) na documentação).
- 2026-10-17 — Validação, consulta de produtos: sem `situacao` inclui desativados ("Desativado", capitalizado) e só a versão vigente; filtro `codigoInterno` parcial e sem diferenciar maiúsculas (`SKU-SDK` e `sku-sdk-1` encontram `SKU-SDK-1`).
- 2026-10-17 — Validação, código interno: aceito em produto novo quando o produto antigo está desativado; aceito em **dois produtos ativos** ao mesmo tempo; `incluirCodigoInterno` aceita código de produto desativado; nova versão de produto desativado o **reativa** (produto 2 voltou a v2 ativa e foi desativado de novo).
- 2026-10-17 — Validação, operadores: `codigo` texto (`OPE_2`); retificação gera `2.1` e desativa a versão anterior; retificação idêntica → 422 `CATP-ER5013`; consulta traz `seq`/`dataReferencia` e omite desativados sem `exibirDesativados=true`.
- 2026-10-17 — Validação, fabricantes: `conhecido=true` + operador e `conhecido=false` só com país foram aceitos; `conhecido=true` sem operador → 422 com `detail` `PUCX-ER0101`/`PUCX-ER0102` e sem `code` no topo; histórico com `conhecido` "Sim"/"Não", mais recente primeiro.
- 2026-10-17 — Registros de teste criados **e desativados** em Validação no CNPJ de teste: operador `OPE_2` (CN) e os do teste de integração; produtos 4 e 5 e os do teste de integração; produto 2 reativado e desativado de novo. Fixtures sanitizadas em `tests/Fixtures/validacao/{operador-estrangeiro-*,fabricante-historico,produtos-por-codigo-interno}.json`.
- 2026-10-17 — TABX (Validação): spec `tabx.json` pública (o índice `/api/` responde 403); IMPEXP lê; filtros/retorno/ordenação como listas JSON URL-codificadas (objeto → 422 `TABX-IN0067`; JSON cru → 400 no Postman); `Content-Range: 1 - 500/2635`, `Accept-Ranges: dado 500`, offset a partir de 1, **206 = há mais páginas**, 200 na última; sem resultado → 404 sem corpo; tabela inexistente → 422 `TABX-IN0003`; 17 tabelas, sem unidades de medida; PAIS com 250 registros (inclui `XX` "A DESIGNAR").
- 2026-10-17 — Postman CLI: duas execuções com menos de 60 s (ou SDK + coleção com a mesma chave) → `PLAT-ER2033` na autenticação e 401 na primeira requisição; com intervalo, a pasta TABX passou 9/9.
- 2026-10-17 — Registros de teste criados **e desativados** em Validação nesta rodada: produto 10 e operador `OPE_5` (fluxo Postman) e os produtos/operadores dos testes de integração (`incluirComFabricantes`).
