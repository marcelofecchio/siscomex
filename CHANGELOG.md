# Changelog

Todas as mudanças relevantes deste projeto são documentadas aqui.
O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto adota [Versionamento Semântico](https://semver.org/lang/pt-BR/) (pré-1.0: a API pública ainda pode mudar).

## [Não lançado]

Previsto para **0.2.0**. Validado no ambiente de Validação em 17/10/2026 (testes de integração e Postman CLI).

### Adicionado
- **Operadores estrangeiros** — `catalogo()->operadoresEstrangeiros()`: consultar (com resultado parcial 206), detalhar, histórico, incluir (inclusive retroativo), nova versão (inclusive retroativa), retificar, desativar e exportar; DTOs `OperadorEstrangeiroRequest` (imutável, com validação dos tamanhos da spec, e-mail e subdivisão ISO 3166-2), `OperadorEstrangeiro`, `IdentificacaoAdicional`, `FiltroOperadoresEstrangeiros`; enum `SituacaoOperadorEstrangeiro`; value object `CodigoPais`.
- **Fabricantes/produtores** — `catalogo()->fabricantes()`: vincular, desvincular, histórico e exportar; `FabricanteRequest::operadorEstrangeiro()`, `::fabricanteNacional()` e `::desconhecido()` garantem a combinação coerente dos campos.
- `produtos()->buscarPorCodigoInterno()` com `ConsultaCodigoInterno` (igualdade exata, `situacao()` → `SituacaoCodigoInterno::Livre|Ativo|Desativado`, `duplicado()`, `maisRecente()`).
- `produtos()->incluirLote()` com resultado por item (`Incluido`, `Erro`, `Incerto`, `Pendente`), callback de progresso, interrupção em rate limit/indisponibilidade/acesso negado e retomada por `ResultadoLote::pendentes()`; nenhum envio é repetido automaticamente.
- Atributos condicionados: `CondicaoAtributo::avaliar()`, `AtributosDaNcm::aplicaveisPara()`, `obrigatoriosPara()`, `condicionadosParaProduto()` e `condicionadoPorCodigo()` (em cascata); `AtributoCondicionado::$codigoCondicionante`; `ProdutoRequest::valoresDoAtributo()`.
- Cache PSR-16 opcional de `atributos()->porNcm()` (`PortalUnicoClient::create(cacheAtributos: ...)`), com expiração à meia-noite de Brasília.
- `PortalUnicoClient::testarConexao()` → `ResultadoConexao` com mensagem amigável e sem tokens.
- `CertificadoA1::fromString()` para certificados guardados pela aplicação (arquivo temporário 0600 apagado com a credencial; sessão identificada pelo conteúdo).
- `SiscomexException::possuiCodigo()` e `ValidationException::mensagensPorCampo()`.
- `Ambiente::nome()`, `Transport::config()` e `Transport::decodeArquivo()` (JSON ou ZIP).
- Coleção Postman: pastas Operadores Estrangeiros e Fabricantes-Produtores; exemplo `examples/operador-e-fabricante.php`.

### Alterado
- `ValidadorProduto` passa a avaliar atributos condicionados: cobra os obrigatórios cuja condição o produto satisfaz e não acusa mais um condicionado informado como "não vinculado à NCM" (aponta quando a condição não se cumpre).
- `AtributoCondicionado::$descricaoCondicao` vem sem os espaços das pontas que o portal envia.

## [0.1.1] - 2026-10-17

### Alterado
- Pacote renomeado de `marcelofecchio/siscomex` para **`marcelofecchio/siscomex-sdk`** e repositório renomeado para `siscomex-sdk`. O namespace `MarceloFecchio\Siscomex\PortalUnico` e a API pública não mudam. Para migrar: `composer remove marcelofecchio/siscomex && composer require marcelofecchio/siscomex-sdk`.

## [0.1.0] - 2026-10-15

Primeira versão pública. Validada no portal real: no ambiente de Validação, autenticação IMPEXP por chaves de acesso, consulta de atributos e o ciclo completo do produto (incluir → consultar → detalhar → nova versão → histórico → desativar); em Produção, apenas leituras.

### Adicionado
- Autenticação no Portal Único com **Chaves de Acesso** (`/portal/api/autenticar/chave-acesso`) e **certificado A1** (mTLS), perfil IMPEXP por padrão.
- Gestão de sessão: reaproveitamento do JWT/CSRF via `TokenStore` (memória, arquivo com `flock`, PSR-16), renovação do `X-CSRF-Token` a cada resposta, intervalo mínimo de 60 s entre autenticações (aguardar ou exceção), reautenticação única em 401 e lock contra autenticações concorrentes.
- `Transport` com retry/backoff só para 503 e erros de rede em GET, bloqueio de escrita em produção (`SISCOMEX_RUN_INTEGRATION=1`) e logger PSR-3 com mascaramento de segredos.
- Exceções tipadas preservando `code`, `tag`, `severity` e `detail` — inclusive o envelope `{"error": {...}}` dos erros 422 do Catálogo — e `RateLimitException` com horário de liberação para `PUCX-ER1001`.
- Cadastro de Atributos: atributos por NCM, consulta por código/nome (lotes de 100), download da relação por NCM, histórico de versões e download público sem autenticação.
- Catálogo de Produtos: consultar, detalhar, histórico, incluir (inclusive retroativo), nova versão, retificar, desativar, exportar e códigos internos.
- `ValidadorProduto` para validar o produto contra os atributos da NCM antes do envio.
- Value objects `Ncm` e `CpfCnpjRaiz` (inclui CNPJ alfanumérico).
- Coleção Postman v2.1 com ambientes de Validação e Produção (somente consultas; escrita pulada inclusive no Newman), autenticação automática e fluxo completo.
- Exemplos, documentação (README, CLAUDE.md, mapa de endpoints, specs OpenAPI) e CI no GitHub Actions (PHP 8.4 e 8.5).

[Não lançado]: https://github.com/marcelofecchio/siscomex-sdk/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/marcelofecchio/siscomex-sdk/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/marcelofecchio/siscomex-sdk/releases/tag/v0.1.0
