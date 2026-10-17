# Coleção Postman — Portal Único Siscomex (IMPEXP)

Arquivos:

| Arquivo | Conteúdo |
|---|---|
| `Siscomex-PortalUnico.postman_collection.json` | Coleção v2.1: Autenticação, Cadastro de Atributos, Catálogo de Produtos, Operadores Estrangeiros, Fabricantes-Produtores, Tabelas Comex (TABX), Fluxo completo e Fluxo operador e fabricante |
| `Validacao.postman_environment.json` | Ambiente de Validação/Treinamento — escrita permitida |
| `Producao.postman_environment.json` | Ambiente de Produção — **somente consultas** (`permitirEscrita=false`) |
| `gerar-colecao.php` | Gerador dos três JSON acima (`php postman/gerar-colecao.php`) — edite ele, não os JSON |

## Configuração

1. Importe a coleção e os dois ambientes no Postman.
2. No ambiente desejado, preencha:
   - `clientId` / `clientSecret` — par de **Chaves de Acesso** gerado em *Perfil do usuário → Chaves de acesso* **no mesmo ambiente** (chaves de produção são recusadas em Validação com `PLAT-ER8007`);
   - `cnpjRaiz` — 8 primeiros caracteres do CNPJ (ou CPF) do catálogo;
   - `ncm`, `modalidade` (`IMPORTACAO`/`EXPORTACAO`).
3. Prefira guardar `clientId`/`clientSecret` como **Current value** (não sincronizado) e nunca exporte o ambiente com valores reais para o repositório (o `.gitignore` ignora `postman/*.local.postman_environment.json`).

### Usando certificado digital A1 em vez das chaves

1. Defina `authMode=certificado` no ambiente.
2. Em **Settings → Certificates → Add Certificate**:
   - Host: `val.portalunico.siscomex.gov.br` (Validação) ou `portalunico.siscomex.gov.br` (Produção);
   - PFX file: o arquivo `.pfx`/`.p12` do **e-CPF** (o perfil IMPEXP não aceita e-CNPJ);
   - Passphrase: a senha do certificado.
3. **Certificado A3 (token/cartão) não funciona no Postman** — use Chaves de Acesso.

## Como a coleção funciona

- **Pre-request da coleção**: se não houver token válido (`csrfExpiration` com folga de 60 s), autentica via `pm.sendRequest` e injeta `Authorization` e `X-CSRF-Token`. Se a última autenticação (`lastAuthAt`) foi há menos de 60 s, aguarda o tempo restante — o portal recusa autenticações em intervalo inferior a 60 s (`PLAT-ER2033`).
- **Tests da coleção**: capturam `Set-Token`, `X-CSRF-Token` e `X-CSRF-Expiration` de **toda** resposta e atualizam o ambiente; em erros, verificam o formato `{message, code, tag}` e registram a `tag` (use-a em chamados à Central Serpro).
- **Proteção de escrita**: com `permitirEscrita` diferente de `true` (padrão em Produção), requisições POST/PUT/DELETE — exceto autenticação e consultas via POST — são **puladas** (`pm.execution.skipRequest()`) e redirecionadas para um host `.invalid`, então nunca chegam ao portal. As requisições que gravam dados têm o prefixo **[ESCRITA]**.
- **Fluxo completo (Validação)**: o passo 1 lê os atributos da NCM e monta `produtoBody` com os obrigatórios de objetivo *Produto* (valores de exemplo); os passos seguintes incluem, detalham, versionam, consultam o histórico e desativam o produto.
- **Fluxo operador e fabricante (Validação)**: inclui um operador estrangeiro e um produto, vincula o operador como fabricante, confere o histórico ("Sim" em `conhecido`) e desfaz tudo (desvincula, desativa produto e operador).
- **Tabelas Comex (TABX)**: somente consultas. Filtros, `camposRetorno` e `camposOrdenacao` são listas JSON **URL-codificadas** na query (o portal recusa o JSON cru com 400); 206 = há mais páginas; `Content-Range` traz faixa e total; sem resultado = 404 sem corpo.
- **Operadores Estrangeiros** e **Fabricantes-Produtores**: usam as variáveis `codigoPais` (ISO 3166, padrão `CN`), `codigoOperador` e `versaoOperador` (preenchidas ao incluir um operador) e `codigoProduto`. O vínculo de fabricante usa `conhecido: true` com o operador; para fabricante desconhecido envie `conhecido: false` só com o país.

> Os códigos de atributos são diferentes entre Validação e Produção (ex.: "Categoria de produto - Catálogo Anvisa" é `ATT_18551` em Validação e `ATT_14545` em Produção em 15/10/2026). Nunca reutilize um body de um ambiente no outro.

## Newman (linha de comando)

```bash
# Fluxo completo em Validação (grava dados de teste)
npx newman run postman/Siscomex-PortalUnico.postman_collection.json \
  -e postman/Validacao.postman_environment.json \
  --env-var "clientId=$SISCOMEX_CLIENT_ID" --env-var "clientSecret=$SISCOMEX_CLIENT_SECRET" \
  --env-var "cnpjRaiz=$SISCOMEX_CNPJ_RAIZ" \
  --folder "Fluxo completo (Validação)"

# Somente consultas de atributos em Produção
npx newman run postman/Siscomex-PortalUnico.postman_collection.json \
  -e postman/Producao.postman_environment.json \
  --env-var "clientId=$SISCOMEX_CLIENT_ID" --env-var "clientSecret=$SISCOMEX_CLIENT_SECRET" \
  --folder "Cadastro de Atributos"
```

Com certificado no Newman: `--ssl-client-cert-list` (arquivo JSON com `name`, `matches`, `pfx.src` e `passphrase`).

## Postman CLI

O [Postman CLI](https://learning.postman.com/docs/postman-cli/postman-cli-overview/) roda a mesma coleção (pastas com `-i`) e foi usado para validar a v0.3:

```bash
postman collection run postman/Siscomex-PortalUnico.postman_collection.json \
  -e postman/Validacao.postman_environment.json \
  --env-var "clientId=$SISCOMEX_CLIENT_ID" --env-var "clientSecret=$SISCOMEX_CLIENT_SECRET" \
  --env-var "cnpjRaiz=$SISCOMEX_CNPJ_RAIZ" \
  -i "Tabelas Comex (TABX)" -i "Fluxo operador e fabricante (Validação)"
```

Cada execução do CLI começa com o ambiente do arquivo, sem a sessão anterior: duas execuções com menos de 60 s de intervalo (ou o SDK e a coleção usando a mesma chave ao mesmo tempo) levam a `PLAT-ER2033` na primeira autenticação e a um 401 na primeira requisição. Espere 60 s entre execuções ou use chaves diferentes para cada sistema.
