# Changelog

Todas as mudanças relevantes deste projeto são documentadas aqui.
O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto adota [Versionamento Semântico](https://semver.org/lang/pt-BR/) (pré-1.0: a API pública ainda pode mudar).

## [Não lançado]

### Adicionado
- Autenticação no Portal Único com **Chaves de Acesso** (`/portal/api/autenticar/chave-acesso`) e **certificado A1** (mTLS), perfil IMPEXP por padrão.
- Gestão de sessão: reaproveitamento do JWT/CSRF via `TokenStore` (memória, arquivo com `flock`, PSR-16), renovação do `X-CSRF-Token` a cada resposta, intervalo mínimo de 60 s entre autenticações (aguardar ou exceção), reautenticação única em 401 e lock contra autenticações concorrentes.
- `Transport` com retry/backoff só para 503 e erros de rede em GET, bloqueio de escrita em produção (`SISCOMEX_RUN_INTEGRATION=1`) e logger PSR-3 com mascaramento de segredos.
- Exceções tipadas preservando `code`, `tag`, `severity` e `detail` (inclui `RateLimitException` com horário de liberação para `PUCX-ER1001`).
- Cadastro de Atributos: atributos por NCM, consulta por código/nome (lotes de 100), download da relação por NCM, histórico de versões e download público sem autenticação.
- Catálogo de Produtos: consultar, detalhar, histórico, incluir (inclusive retroativo), nova versão, retificar, desativar, exportar e códigos internos.
- `ValidadorProduto` para validar o produto contra os atributos da NCM antes do envio.
- Value objects `Ncm` e `CpfCnpjRaiz` (inclui CNPJ alfanumérico).
- Coleção Postman v2.1 com ambientes de Validação e Produção (somente consultas), autenticação automática e fluxo completo.
- Exemplos, documentação (README, CLAUDE.md, mapa de endpoints, specs OpenAPI) e CI no GitHub Actions.

### Corrigido
- `ValidationException::mensagens()`/`detalhes` agora leem os itens de `detail` no envelope `{"error": {...}}` usado pelo Catálogo de Produtos (antes devolviam o JSON bruto do item).
- Coleção Postman: requisições de escrita bloqueadas são efetivamente puladas também no Newman.
