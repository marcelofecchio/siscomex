# Specs OpenAPI oficiais

Baixadas em **15/10/2026** de <https://docs.portalunico.siscomex.gov.br/> (as páginas Swagger carregam estes arquivos via JavaScript).

| Arquivo | Origem | Versão |
|---|---|---|
| `plat-auth.json` | `/api/plat/plat-auth.json` (Swagger `swagger-51c654fd.html`) | OpenAPI 3.0.1 — Autenticação v75.0.0 |
| `catp.json` | `/api/catp/catp.json` (Swagger `swagger-d5e7b137.html`) | Swagger 2.0 — Catálogo de Produtos v1.0.0 |
| `cadatributos.json` | `/api/cada/cadatributos.json` (Swagger `swagger-f68672de.html`) | Swagger 2.0 — Cadastro de Atributos v1.0.0 |

Para atualizar:

```bash
cd docs/api/specs
curl -sSLO https://docs.portalunico.siscomex.gov.br/api/plat/plat-auth.json
curl -sSLO https://docs.portalunico.siscomex.gov.br/api/catp/catp.json
curl -sSLO https://docs.portalunico.siscomex.gov.br/api/cada/cadatributos.json
git diff --stat .
```

Mudanças futuras costumam aparecer antes em <https://docs-proxima.portalunico.siscomex.gov.br/>.
