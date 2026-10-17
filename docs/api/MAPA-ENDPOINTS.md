# Mapa de endpoints — Autenticação, Cadastro de Atributos, Catálogo de Produtos e Tabelas Comex

Fonte: specs OpenAPI em [`specs/`](specs/) (baixadas em 15/10/2026) + documentação oficial em <https://docs.portalunico.siscomex.gov.br/>.
Todos os serviços autenticados exigem os headers `Authorization` (valor de `Set-Token`) e `X-CSRF-Token`.
Perfil: todos os endpoints abaixo são usados com `Role-Type: IMPEXP` (a spec não restringe perfil por endpoint — TODO(confirmar) se algum exige outro perfil).

Legenda da coluna **SDK**: ✅ implementado · 🕒 planejado · — fora do escopo.

## Autenticação (`plat-auth.json`, base `{ambiente}/portal`)

| Método | Path | Headers de entrada | Resposta | Erros | SDK |
|---|---|---|---|---|---|
| POST | `/api/autenticar` | `Role-Type` + certificado no handshake (mTLS) | 200: headers `Set-Token`, `X-CSRF-Token`, `X-CSRF-Expiration` (ms); corpo `{token}` | 422 `ErrorResponseDTO`, 500 | ✅ `CertificadoA1` |
| POST | `/api/autenticar/chave-acesso` | `Role-Type`, `Client-Id`, `Client-Secret` (sem certificado) | idem | idem | ✅ `ChaveAcesso` |
| POST | `/api/autenticar/sistema` | `System-Code` + certificado de equipamento | idem | idem | — (exclusivo de órgãos públicos) |
| POST | `/api/autenticar/chave-acesso/sistema` | chaves de sistema | idem | idem | — (exclusivo de órgãos públicos) |

Códigos de erro documentados: `PUCX-ER0101`, `PUCX-ER0102`, `PUCX-ER0401`, `PLAT-ER2001`, `PLAT-ER2002`, `PLAT-ER2004`, `PLAT-ER2008`, `PLAT-ER2013`, `PLAT-ER2023`, `PLAT-ER2033`, `PLAT-ER3005`, `PLAT-ER8001`, `PLAT-ER8002`, `PLAT-ER8003`, `PLAT-ER8004`, `PLAT-ER8008`.
Observado na prática (não documentado): `PLAT-ER8007` — "Par de chaves inválido ou inexistente" (chave de outro ambiente).

## Cadastro de Atributos (`cadatributos.json`, base `{ambiente}/cadatributos/api`)

| Método | Path | Parâmetros | Resposta | SDK |
|---|---|---|---|---|
| GET | `/ext/atributo-ncm/{codigo-ncm}` | query: `data` (yyyy-MM-dd), `modalidade` (IMPORTACAO\|EXPORTACAO), `objetivos[]` (PRODUTO, TRATAMENTO_ADMINISTRATIVO, DUIMP), `orgaosDemandantes[]` | **schema ausente na spec**; observado em produção: `{listaNcm:[{codigoNcm, listaAtributos}], detalhesAtributos:[ArquivoDiarioAtributoDTO]}` | ✅ `atributos()->porNcm()` |
| POST | `/ext/atributo/consulta-codigo` | body `AtributoConsultaFiltroCodigoDTO {codigos[] (≤100), data}` | `ArquivoDiarioAtributoDTO` — spec diz objeto; **observado: lista** | ✅ `porCodigos()` / `porCodigo()` |
| POST | `/ext/atributo/consulta-nome` | body `AtributoConsultaFiltroNomeDTO {nomes[] (≤100), data}` | idem | ✅ `porNomes()` |
| GET | `/ext/atributo-ncm/download/{tipo}` | `tipo` json\|xml; query `data`, `modalidade`, `objetivos[]`, `orgaosDemandantes[]`, `codigosNCM[]` | ZIP com JSON | ✅ `downloadPorNcm()` (json) |
| GET | `/ext/atributo-ncm/historico-versoes` | query `versao[]` | `ChangeLogArquivoDiarioAtributoNcmDTO[]` | ✅ `historicoVersoesPorNcm()` |
| GET | `/ext/atributo/historico-versoes` | query `versao[]` | `ChangeLogArquivoDiarioAtributoDTO[]` | ✅ `historicoVersoesAtributos()` |
| GET | `/ext/atributo/download/{tipo}` | query `data`, `objetivos[]`, `orgaosDemandantes[]` | ZIP | 🕒 |
| GET | `/ext/atributo-fundamento-legal/{codigo}` | query `data`, `orgaosDemandantes[]` | schema ausente | — (Fundamento Legal da DUIMP) |
| GET | `/ext/atributo-fundamento-legal/download/{tipo}` | idem | ZIP | — |
| GET | `/atributo-ncm/download/json?perfil=PUBLICO` | **sem autenticação** | ZIP com JSON (~15 MB), atualizado diariamente à meia-noite | ✅ `atributosPublicos()->baixarPorNcm()` |

Arrays de query usam `collectionFormat=multi` (chave repetida: `codigosNCM=1&codigosNCM=2`).
Respostas: 200, 204, 400, 401, 403, 404, 422, 500.

## Catálogo de Produtos (`catp.json`, base `{ambiente}/catp/api`)

### Produtos

| Método | Path | Entrada | Resposta | SDK |
|---|---|---|---|---|
| GET | `/ext/produto` | query obrigatória `cpfCnpjRaiz`; opcionais `codigo`, `codigoInterno`, `descricao`, `denominacao`, `ncm`, `situacao` (0/1/2), `modalidade` (IMPORTACAO\|EXPORTACAO\|AMBOS), `periodoRegistroInicio/Fim`, `ultimaAlteracaoInicio/Fim`, `paisOrigem`, `cpfCnpjFabricante`, `dataReferencia`, `operadorEstrangeiroCodigo` | 200 `ProdutoIntegracaoDTO[]`, 204, **206 parcial** | ✅ `consultar(FiltroProdutos)` |
| POST | `/ext/produto/{cpfCnpjRaiz}` | body `ProdutoIntegracaoRequestDTO` | `ProdutoIntegracaoResponseDTO` | ✅ `incluir()` |
| POST | `/ext/produto/{cpfCnpjRaiz}/{dataReferencia}` | idem (vigência retroativa) | idem | ✅ `incluir(..., dataReferencia:)` |
| PUT | `/ext/produto/{cpfCnpjRaiz}/{codigo}` | body `ProdutoIntegracaoRequestDTO` | idem | ✅ `novaVersao()` |
| PUT | `/ext/produto/{cpfCnpjRaiz}/{codigo}/{versao}` | idem (versão "1" ou "1.1") | idem | ✅ `retificar()` |
| GET | `/ext/produto/{cpfCnpjRaiz}/{codigo}/{versao}` | — | idem | ✅ `detalhar()` |
| PUT | `/ext/produto/desativar/{cpfCnpjRaiz}/{codigo}` | — (desativa a versão mais recente) | idem ou 204 | ✅ `desativar()` |
| GET | `/ext/produto/historico/{cpfCnpjRaiz}/{codigo}` | — | `ProdutoHistoricoResponseDTO` (lista) | ✅ `historico()` |
| GET | `/ext/produto/exportar/{cpfCnpjRaiz}/{exibirDesativados}` | query `codigosProduto[]`, `inicioVigenciaDe`, `inicioVigenciaAte` | ZIP | ✅ `exportar()` |
| POST | `/ext/produto/codigo-interno/{cpfCnpjRaiz}/{codigo}/{versao}` | body `{codigoInterno}` | 200/204 | ✅ `incluirCodigoInterno()` |
| DELETE | `/ext/produto/codigo-interno/{cpfCnpjRaiz}/{codigo}/{versao}` | body `{codigoInterno}` | 200/204 | ✅ `removerCodigoInterno()` |

`ProdutoIntegracaoRequestDTO`: `denominacao` (1–120), `descricao` (≤3700), `modalidade`, `ncm` (8 dígitos), `atributos[] {atributo, valor (1–100)}`, `atributosMultivalorados[] {atributo, valores[]}`, `atributosCompostos[] {atributo, valores[{atributo, valor}]}`, `atributosCompostosMultivalorados[] {atributo, valores[[...]]}` (formato interno TODO(confirmar)), `codigosInterno[]` (≤60 cada).

Erro 422 observado (Validação): `{message, code: "PUCX-ER0123", tag, detail: [{error: {message, code: "CADA-ER5008", field: "ATT_x"}}]}`. Outro código observado: `CATP-ER2021` (retificação idêntica à versão existente).

Observado na Validação (17/10/2026) em `GET /ext/produto`: sem `situacao` a consulta **inclui desativados** (`situacao` vem como "Desativado", capitalizado) e devolve só a **versão vigente** de cada produto; o filtro `codigoInterno` é **parcial e não diferencia maiúsculas**. O portal aceita o mesmo código interno em dois produtos ativos; o código de um produto desativado pode ser reutilizado; `PUT /ext/produto/{cpfCnpjRaiz}/{codigo}` (nova versão) **reativa** um produto desativado. A exportação de um produto incluído minutos antes ainda não o trazia.

### Operadores Estrangeiros (✅ `catalogo()->operadoresEstrangeiros()`)

| Método | Path | Entrada | Resposta | SDK |
|---|---|---|---|---|
| GET | `/ext/operador-estrangeiro` | query obrigatória `cpfCnpjRaiz`; opcionais `tin`, `codigo`, `codigoInterno`, `nome` (≤70), `paisOrigem`, `exibirDesativados` | 200 `OperadorEstrangeiroIntegracaoDTO[]`, 204, **206 parcial** | ✅ `consultar(FiltroOperadoresEstrangeiros)` |
| POST | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}` | body `OperadorEstrangeiroRequestIntegracaoDTO` | `OperadorEstrangeiroIntegracaoDTO` | ✅ `incluir()` |
| POST | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{dataReferencia}` | idem (retroativo) | idem | ✅ `incluir(..., dataReferencia:)` |
| PUT | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}` | idem (nova versão) | idem | ✅ `novaVersao()` |
| POST | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{dataReferencia}` | idem (nova versão retroativa) | idem | ✅ `novaVersao(..., dataReferencia:)` |
| PUT | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao}` | idem (retificação) | idem | ✅ `retificar()` |
| GET | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao}` | — | idem | ✅ `detalhar()` |
| PUT | `/ext/operador-estrangeiro/desativar/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao}` | — | idem | ✅ `desativar()` |
| GET | `/ext/operador-estrangeiro/historico/{cpfCnpjRaiz}/{codigoPais}/{codigo}` | — | `OperadorEstrangeiroHistoricoResponseDTO[]` | ✅ `historico()` |
| GET | `/ext/operador-estrangeiro/exportar/{cpfCnpjRaiz}/{exibirDesativados}` | query `codigosOperador[]`, `inicioVigenciaDe/Ate` | spec: lista JSON | ✅ `exportar()` (aceita JSON e ZIP) |

`OperadorEstrangeiroRequestIntegracaoDTO`: obrigatórios `nome` (1–150), `logradouro` (1–70), `nomeCidade` (1–35); opcionais `tin` (≤35), `codigoSubdivisaoPais` (ISO 3166-2, ≤6), `cep` (≤9), `codigoInterno` (≤35), `email` (≤70), `identificacoesAdicionais[] {numero (≤35), codigo (agência UN/EDIFACT 3055, 3)}`. O país vai no path (ISO 3166-1 alfa-2).

Observado na Validação (17/10/2026): `codigo` é texto (`OPE_2`); retificação gera `2.1` e a versão anterior fica desativada; retificação idêntica → 422 `CATP-ER5013`; a consulta traz também `seq` e `dataReferencia` e, **sem `exibirDesativados=true`, omite desativados**; a exportação devolveu **204** logo após a inclusão, enquanto a consulta já encontrava o operador (TODO(confirmar) se é gerada D+1).

### Fabricantes-Produtores (✅ `catalogo()->fabricantes()`)

| Método | Path | Entrada | Resposta | SDK |
|---|---|---|---|---|
| POST | `/ext/fabricante/{cpfCnpjRaiz}` | body `FabricanteIntegracaoRequestDTO {codigoProduto, codigoPais*, conhecido*, codigoOperadorEstrangeiro, cpfCnpjFabricante}` | `FabricanteIntegracaoResponseDTO` | ✅ `vincular()` |
| PUT | `/ext/fabricante/{cpfCnpjRaiz}` | idem | idem | ✅ `desvincular()` |
| GET | `/ext/fabricante/historico/{cpfCnpjRaiz}/{codigo}` | `codigo` = código do produto | `FabricanteHistoricoResponseDTO` (lista) | ✅ `historico()` |
| GET | `/ext/fabricante/exportar/{cpfCnpjRaiz}` | query `codigosProduto[]`, `paisesOrigem[]` | spec: `FabricanteIntegracaoDTO` (objeto) | ✅ `exportar()` (aceita objeto, lista e ZIP) |

O vínculo é separado do produto: `ProdutoIntegracaoRequestDTO` não tem campo de fabricante.

Observado na Validação (17/10/2026): `conhecido=true` = fabricante **conhecido** (a descrição da spec diz o contrário) — sem operador, a API responde 422 com `detail` `PUCX-ER0101` "Campo Fabricante/produtor estrangeiro é obrigatório" e `PUCX-ER0102` (sem `code` no nível superior); `conhecido=false` só com o país é aceito. No histórico, `conhecido` vem como **"Sim"/"Não"** (spec: "true"/"false"), `cpfCnpjTin` traz o TIN do operador e os eventos vêm do mais recente para o mais antigo. A exportação devolveu **204** logo após o vínculo.

Evento push `catp-prod-desativado` (produto desativado por ofício): documentado no README; webhooks fora do escopo.

## Tabelas Comex (`tabx.json`, base `{ambiente}/tabx/api`) — ✅ `tabelas()`, somente leitura

Spec baixada em 17/10/2026 de `https://docs.portalunico.siscomex.gov.br/api/tabx/tabx.json`. Requer `Authorization` e `X-CSRF-Token`; o perfil IMPEXP consegue ler (verificado na Validação).

| Método | Path | Entrada | Resposta | SDK |
|---|---|---|---|---|
| GET | `/ext/tabela` | — | `TabelaApiRepresentation[] {nome, rotulo, descricao}` | ✅ `listar()` |
| GET | `/ext/tabela/{nomeTabela}/metadado` | `nomeTabela` (1–50) | `TabelaCamposApiRepresentation {nome, rotulo, descricao, campos[]}` | ✅ `metadados()` |
| GET | `/ext/tabela/{nomeTabela}` | query `filtros`, `camposRetorno`, `camposOrdenacao`, `nivel` (0\|1), `offset` | `DadosTabelaApiRepresentation {nomeTabela, dados[{campos[{nome, valor, dadosTabelaEstrangeira}]}]}` | ✅ `consultar()`, `todos()`, `paises()` |
| POST / PUT / DELETE | `/ext/tabela/{nomeTabela}[/{codigo}]` | body `campos` | — | — (manutenção pelos órgãos) |

Observado na Validação (17/10/2026):
- `filtros`, `camposRetorno` e `camposOrdenacao`: a spec só diz "Modelo: ...". O portal aceita **listas JSON URL-codificadas** (`[{"nome":"SIGLA_ISO2","valores":["CN","DE"]}]`, `[{"nome":"NOME","tipoOrdenacao":"DESC"}]`); objeto único → 422 `TABX-IN0067`; JSON cru, sem codificar → 400. O filtro é por igualdade.
- Paginação: até 500 registros (`Accept-Ranges: dado 500`), `offset` começa em 1, `Content-Range: 1 - 500/2635`; **206 enquanto houver mais páginas**, 200 na última.
- Nenhum registro → **404 sem corpo**; tabela inexistente → 422 `TABX-IN0003`.
- Tabelas disponíveis (17): CIDADE_ATIT, EMBALAGEM_ATIT, ENQUADRAMENTO, FUNDAMENTO_LEGAL_TT, GRUPO_EXIGENCIA, LIPS_ZFM, MOTIVO_SITUACAO_ESPECIAL_DUIMP, ORGAO_ANUENTE, PAIS, PORTO, RECINTO_ATIT, SOLICITANTE, TIPO_AREA_EQUIPAMENTO, TIPO_CONHECIMENTO, TIPO_CONTEINER, TIPO_DECLARACAO_ADUANEIRA, UNIDADE_ATIT. Não há tabela de unidades de medida.
- PAIS: 250 registros, campos SIGLA_ISO2 (chave), SIGLA_ISO3, NOME, NOME_INGLES, NOME_FRANCES, CODIGO_NUMERICO, DATA_INICIO, DATA_FIM (`yyyy-MM-dd'T'HH:mm:ss.SSSZ`, vazio = sem fim), INTERNO_VERSAO; inclui `XX` "A DESIGNAR".
