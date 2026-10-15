# CLAUDE.md — SDK PHP do Portal Único Siscomex

## Visão geral

Pacote Composer `marcelofecchio/siscomex` (namespace `MarceloFecchio\Siscomex\PortalUnico`), SDK agnóstico de framework para a API pública do Portal Único Siscomex, perfil **IMPEXP**.

- **Escopo v0.1**: autenticação (Chaves de Acesso e certificado A1), Cadastro de Atributos, Catálogo de Produtos (produtos), coleção Postman, exemplos.
- **Fora do escopo**: qualquer aplicação final (telas, painel, API própria), webhooks/eventos push, Fundamento Legal/LPCO, autenticação de sistemas de órgãos públicos.
- **Próximo (v1.1)**: Fabricantes-Produtores e Operadores Estrangeiros do Catálogo.

## Stack

PHP ^8.4 · PSR-18/17/7 (Guzzle 7 como padrão) · PSR-16 · PSR-3 · PSR-20 (clock) · PHPUnit 12 · PHPStan 2 (nível max) · PHP-CS-Fixer (PER-CS 2.0).

## Estrutura

```
src/
  PortalUnicoClient.php        fachada: atributos(), catalogo()->produtos(), atributosPublicos(), autenticar()
  Config/                      Ambiente, Perfil, ClientConfig, PoliticaIntervaloAutenticacao
  Auth/                        Authenticator, Session, Credentials/ (ChaveAcesso, CertificadoA1), TokenStore/
  Http/                        Transport (headers, CSRF, 401, retry, bloqueio de escrita), ErrorMapper, LogMasker, GuzzleClientFactory
  Resources/Atributos/         AtributosResource, AtributosPublicos, Dto/
  Resources/Catalogo/          CatalogoResource, ProdutosResource, Dto/
  Validation/                  ValidadorProduto
  Enum/ ValueObject/ Support/ Exceptions/
tests/Unit · tests/Integration (desligados por padrão) · tests/Fixtures/{validacao,producao}
examples/ · postman/ (gerado por postman/gerar-colecao.php) · docs/ (PROGRESSO.md, api/MAPA-ENDPOINTS.md, api/specs/)
```

## Comandos

```bash
composer install
composer qa                   # cs:check + analyse + test — deve passar antes de todo commit
composer test                 # unitários
composer test:coverage        # cobertura (pcov)
composer cs:fix
SISCOMEX_RUN_INTEGRATION=1 SISCOMEX_AMBIENTE=validacao composer test:integration
php postman/gerar-colecao.php # regenerar coleção/ambientes Postman
npx newman run postman/Siscomex-PortalUnico.postman_collection.json -e postman/Validacao.postman_environment.json --env-var clientId=... --env-var clientSecret=...
```

## Convenções

- `declare(strict_types=1)` em todos os arquivos; PSR-4; PER-CS 2.0 (`composer cs:fix`).
- **Idioma**: domínio em português, como na API (`porNcm`, `incluir`, `ProdutoRequest`, `codigoErro`); infraestrutura técnica em inglês (`Transport`, `Authenticator`, `TokenStore::getSession`). Documentação e mensagens de erro em português do Brasil.
- DTOs imutáveis (`readonly` ou `public private(set)`) com `fromArray()`/`toArray()`; nada de arrays soltos na API pública. Leitura de JSON via `Support\Data` (tolerante e tipada).
- Datas como `DateTimeImmutable` (fuso America/Sao_Paulo); NCM e CPF/CNPJ raiz como value objects.
- Commits: Conventional Commits em português (`feat:`, `fix:`, `docs:`, `test:`, `chore:`). Branch principal `main`; features em `feat/<assunto>`.

## Regras inegociáveis

1. **Nunca** executar POST/PUT/DELETE contra **produção** durante desenvolvimento ou testes. Leituras em produção são permitidas.
2. Testes de integração só com `SISCOMEX_RUN_INTEGRATION=1` **e** `SISCOMEX_AMBIENTE=validacao`. Com a flag ativa, o SDK recusa escrita em produção (`ProductionWriteBlockedException`).
3. Segredos fora do Git: `credentials.txt`, `.env`, `*.pfx`, `*.p12`, `*.pem`, `*.key`, `storage/` estão no `.gitignore`. Não exibir tokens/chaves em logs ou saídas (use `LogMasker`).
4. Respeitar os 60 s entre autenticações — nunca autenticar por requisição; reutilizar a sessão do `TokenStore`.
5. Não inventar endpoints, campos ou códigos de erro: consultar `docs/api/specs/` e `docs/api/MAPA-ENDPOINTS.md` antes de alterar um recurso. O que não estiver documentado vira `TODO(confirmar)` e é registrado em `docs/PROGRESSO.md`.
6. Nunca fixar códigos de atributos (diferem entre Validação e Produção); fixtures separadas por ambiente.
7. Nunca repetir automaticamente `PUCX-ER1001` (rate limit) nem 4xx.

## Ritual de sessão

- **Início**: `git pull` → ler `CLAUDE.md` e `docs/PROGRESSO.md` → `composer install` → `composer qa`.
- **Fim**: atualizar `docs/PROGRESSO.md` (data, máquina, feito/falta/decisões) → `composer qa` → commit → `git push`.
