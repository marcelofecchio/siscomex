# Mapa de endpoints — Autenticação, Cadastro de Atributos e Catálogo de Produtos

Fonte: specs OpenAPI em [`specs/`](specs/) (baixadas em 15/10/2026) + documentação oficial em <https://docs.portalunico.siscomex.gov.br/>.
Todos os serviços autenticados exigem os headers `Authorization` (valor de `Set-Token`) e `X-CSRF-Token`.
Perfil: todos os endpoints abaixo são usados com `Role-Type: IMPEXP` (a spec não restringe perfil por endpoint — TODO(confirmar) se algum exige outro perfil).

Legenda da coluna **SDK**: ✅ implementado na v0.1 · 🕒 planejado (v1.1) · — fora do escopo.

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

### Fabricantes-Produtores e Operadores Estrangeiros (🕒 v1.1)

| Método | Path |
|---|---|
| POST / PUT | `/ext/fabricante/{cpfCnpjRaiz}` (vincular / desvincular) |
| GET | `/ext/fabricante/exportar/{cpfCnpjRaiz}`, `/ext/fabricante/historico/{cpfCnpjRaiz}/{codigo}` |
| GET | `/ext/operador-estrangeiro` |
| POST | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}` (+ `/{dataReferencia}`) |
| PUT | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}` (nova versão), `/{versao}` (retificação), `/desativar/...` |
| GET | `/ext/operador-estrangeiro/{cpfCnpjRaiz}/{codigoPais}/{codigo}/{versao}`, `/exportar/...`, `/historico/...` |

Evento push `catp-prod-desativado` (produto desativado por ofício): documentado no README; webhooks fora do escopo.
