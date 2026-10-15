<?php

declare(strict_types=1);

/*
 * Gera a coleção e os ambientes Postman (formato v2.1).
 *
 *   php postman/gerar-colecao.php
 *
 * Edite este arquivo (e não o JSON gerado) para alterar a coleção.
 */

$dir = __DIR__;

/** @return array<string, mixed> */
function script(string $tipo, string $codigo): array
{
    return ['listen' => $tipo, 'script' => ['type' => 'text/javascript', 'exec' => explode("\n", trim($codigo))]];
}

/**
 * @param list<string>               $path
 * @param array<string, string>      $query
 * @param list<array{0:string,1:string}> $headers
 *
 * @return array<string, mixed>
 */
function req(string $nome, string $metodo, array $path, array $query = [], ?string $body = null, string $testes = '', string $descricao = '', array $headers = [], string $preRequest = ''): array
{
    $raw = '{{baseUrl}}/'.implode('/', $path);
    $queryList = [];
    foreach ($query as $k => $v) {
        $queryList[] = ['key' => $k, 'value' => $v];
    }
    if ($queryList !== []) {
        $raw .= '?'.implode('&', array_map(static fn (array $q): string => $q['key'].'='.$q['value'], $queryList));
    }

    $request = [
        'method' => $metodo,
        'header' => array_map(static fn (array $h): array => ['key' => $h[0], 'value' => $h[1]], [['Accept', 'application/json'], ...$headers]),
        'url' => ['raw' => $raw, 'host' => ['{{baseUrl}}'], 'path' => $path] + ($queryList !== [] ? ['query' => $queryList] : []),
        'description' => $descricao,
    ];
    if ($body !== null) {
        $request['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
        $request['body'] = ['mode' => 'raw', 'raw' => $body, 'options' => ['raw' => ['language' => 'json']]];
    }

    $events = [];
    if ($preRequest !== '') {
        $events[] = script('prerequest', $preRequest);
    }
    if ($testes !== '') {
        $events[] = script('test', $testes);
    }

    return ['name' => $nome, 'event' => $events, 'request' => $request, 'response' => []];
}

function status(int ...$codigos): string
{
    $lista = implode(', ', $codigos);

    return "pm.test('Status esperado ({$lista})', () => pm.expect(pm.response.code).to.be.oneOf([{$lista}]));";
}

$preRequestColecao = <<<'JS'
// Autenticação automática da coleção:
// - reaproveita o token enquanto o X-CSRF-Token for válido (nunca autentica por requisição);
// - respeita o intervalo mínimo de 60 s entre autenticações (PLAT-ER2033);
// - bloqueia escrita quando permitirEscrita != "true" (ambiente de Produção).
(function () {
    const env = pm.environment;
    const url = pm.request.url.toString();
    const ehAutenticacao = url.includes('/portal/api/autenticar');
    const ehPublico = url.includes('perfil=PUBLICO');
    const ehConsultaViaPost = url.includes('/consulta-codigo') || url.includes('/consulta-nome');
    const metodo = pm.request.method;

    if (!['GET', 'HEAD', 'OPTIONS'].includes(metodo) && !ehAutenticacao && !ehConsultaViaPost && env.get('permitirEscrita') !== 'true') {
        const mensagem = 'Escrita bloqueada neste ambiente (permitirEscrita != "true"). Em Produção use apenas consultas.';
        // Um erro no pre-request NÃO impede o envio no Newman: pulamos a requisição e,
        // por garantia, trocamos a URL por um host inexistente.
        if (pm.execution && typeof pm.execution.skipRequest === 'function') {
            pm.execution.skipRequest();
        }
        pm.request.url.update('http://escrita-bloqueada.invalid/');
        throw new Error(mensagem);
    }

    const ultima = Number(env.get('lastAuthAt') || 0);
    const espera = ultima ? 60000 - (Date.now() - ultima) : 0;

    if (ehAutenticacao) {
        if (espera > 0) {
            throw new Error('Aguarde ' + Math.ceil(espera / 1000) + ' s: o portal recusa autenticações em intervalo inferior a 60 s (PLAT-ER2033).');
        }
        return;
    }
    if (ehPublico) {
        return;
    }

    const aplicar = function () {
        pm.request.headers.upsert({ key: 'Authorization', value: env.get('jwt') });
        pm.request.headers.upsert({ key: 'X-CSRF-Token', value: env.get('csrfToken') });
    };

    const expira = Number(env.get('csrfExpiration') || 0);
    if (env.get('jwt') && env.get('csrfToken') && expira - 60000 > Date.now()) {
        aplicar();
        return;
    }

    const modo = env.get('authMode') || 'chave';
    const autenticar = function () {
        const header = [{ key: 'Role-Type', value: env.get('roleType') || 'IMPEXP' }];
        if (modo === 'chave') {
            header.push({ key: 'Client-Id', value: env.get('clientId') });
            header.push({ key: 'Client-Secret', value: env.get('clientSecret') });
        }
        pm.sendRequest({
            url: env.get('baseUrl') + (modo === 'chave' ? '/portal/api/autenticar/chave-acesso' : '/portal/api/autenticar'),
            method: 'POST',
            header: header,
        }, function (err, res) {
            if (err) {
                console.error('Falha de rede na autenticação', err);
                return;
            }
            if (res.code !== 200) {
                if (res.text().includes('PLAT-ER2033')) {
                    env.set('lastAuthAt', String(Date.now()));
                }
                console.error('Falha na autenticação: HTTP ' + res.code + ' ' + res.text());
                return;
            }
            let corpo = {};
            try { corpo = res.json(); } catch (e) { /* corpo opcional */ }
            env.set('jwt', res.headers.get('Set-Token'));
            env.set('csrfToken', res.headers.get('X-CSRF-Token') || corpo.token);
            env.set('csrfExpiration', res.headers.get('X-CSRF-Expiration') || String(Date.now() + 3600000));
            env.set('lastAuthAt', String(Date.now()));
            aplicar();
        });
    };

    if (espera > 0) {
        console.log('Aguardando ' + espera + ' ms para respeitar a regra dos 60 s entre autenticações.');
        setTimeout(autenticar, espera + 500);
    } else {
        autenticar();
    }
})();
JS;

$testesColecao = <<<'JS'
// Captura Set-Token, X-CSRF-Token e X-CSRF-Expiration de TODA resposta (o CSRF é renovado pelo portal).
(function () {
    const env = pm.environment;
    const jwt = pm.response.headers.get('Set-Token');
    const csrf = pm.response.headers.get('X-CSRF-Token');
    const expira = pm.response.headers.get('X-CSRF-Expiration');

    if (jwt) {
        env.set('jwt', jwt);
        env.set('lastAuthAt', String(Date.now()));
    }
    if (csrf) {
        env.set('csrfToken', csrf);
        env.set('csrfExpiration', expira || String(Date.now() + 3600000));
    } else if (expira) {
        env.set('csrfExpiration', expira);
    }

    if (pm.response.code >= 400) {
        let erro = null;
        try { erro = pm.response.json(); } catch (e) { /* HTML de proxy/manutenção */ }
        if (erro) {
            pm.test('Erro no formato padrão do portal (message, code, tag)', function () {
                pm.expect(erro).to.have.property('message');
                pm.expect(erro).to.have.property('code');
                pm.expect(erro).to.have.property('tag');
            });
            if (erro.code === 'PUCX-ER1001') {
                console.warn('Limite de acessos atingido (não repita): ' + erro.message);
            }
            if (erro.code === 'PLAT-ER2033') {
                env.set('lastAuthAt', String(Date.now()));
            }
            console.log('Erro do portal — informe a tag em chamados à Central Serpro: ' + erro.tag);
        }
    }
})();
JS;

$cabecalhoErroAuth = <<<'JS'
pm.test('Retornou Set-Token e X-CSRF-Token', function () {
    pm.response.to.have.status(200);
    pm.expect(pm.response.headers.get('Set-Token')).to.be.a('string').and.not.empty;
    pm.expect(pm.response.headers.get('X-CSRF-Token') || pm.response.json().token).to.be.a('string').and.not.empty;
});
JS;

$bodyProduto = json_encode([
    'denominacao' => 'Produto de exemplo',
    'descricao' => 'Detalhamento complementar do produto',
    'modalidade' => '{{modalidade}}',
    'ncm' => '{{ncm}}',
    'atributos' => [['atributo' => 'ATT_XXXX', 'valor' => '01']],
    'atributosMultivalorados' => [],
    'atributosCompostos' => [],
    'atributosCompostosMultivalorados' => [],
    'codigosInterno' => ['{{codigoInterno}}'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$testeProduto = status(200).<<<'JS'

pm.test('Produto retornado com código e versão', function () {
    const p = pm.response.json();
    pm.expect(p).to.have.property('codigo');
    pm.expect(p).to.have.property('versao');
    pm.environment.set('codigoProduto', String(p.codigo));
    pm.environment.set('versao', String(p.versao));
});
JS;

$testeAtributosNcm = status(200, 204).<<<'JS'

pm.test('Estrutura {listaNcm, detalhesAtributos}', function () {
    if (pm.response.code === 204) { return; }
    const j = pm.response.json();
    pm.expect(j).to.have.property('listaNcm');
    pm.expect(j).to.have.property('detalhesAtributos');
});
JS;

$montarProduto = $testeAtributosNcm.<<<'JS'

// Monta o corpo do produto com os atributos obrigatórios (objetivo Produto) e valores de exemplo.
const j = pm.response.json();
const detalhes = {};
(j.detalhesAtributos || []).forEach(function (d) { detalhes[d.codigo] = d; });
const item = (j.listaNcm || [])[0] || { listaAtributos: [] };
const prefixo = pm.environment.get('modalidade') === 'EXPORTACAO' ? 'Export' : 'Import';
const atributos = [];
item.listaAtributos.filter(function (a) { return a.obrigatorio && String(a.modalidade).indexOf(prefixo) === 0; }).forEach(function (a) {
    const d = detalhes[a.codigo] || {};
    if (!(d.objetivos || []).some(function (o) { return o.descricao === 'Produto'; })) { return; }
    let valor = 'Teste';
    switch (d.formaPreenchimento) {
        case 'LISTA_ESTATICA': valor = ((d.dominio || [])[0] || {}).codigo || '01'; break;
        case 'BOOLEANO': valor = 'false'; break;
        case 'NUMERO_INTEIRO': case 'NUMERO_REAL': valor = '1'; break;
        case 'DATA': valor = new Date().toISOString().slice(0, 10); break;
    }
    atributos.push({ atributo: a.codigo, valor: valor });
});
pm.environment.set('produtoBody', JSON.stringify({
    denominacao: 'Produto de teste Postman ' + Date.now(),
    descricao: 'Fluxo completo da coleção Postman',
    modalidade: pm.environment.get('modalidade') || 'IMPORTACAO',
    ncm: pm.environment.get('ncm'),
    atributos: atributos,
    atributosMultivalorados: [],
    atributosCompostos: [],
    atributosCompostosMultivalorados: [],
    codigosInterno: []
}));
console.log('Atributos obrigatórios preenchidos: ' + atributos.map(function (a) { return a.atributo; }).join(', '));
JS;

$P = ['catp', 'api', 'ext', 'produto'];
$C = ['cadatributos', 'api', 'ext'];

$autenticacao = [
    'name' => 'Autenticação',
    'description' => 'POST {baseUrl}/portal/api/autenticar[/chave-acesso]. A coleção autentica sozinha quando necessário; use estas requisições só para testar. Respeite 60 s entre autenticações.',
    'item' => [
        req('Autenticar — chave de acesso', 'POST', ['portal', 'api', 'autenticar', 'chave-acesso'], testes: $cabecalhoErroAuth, headers: [['Role-Type', '{{roleType}}'], ['Client-Id', '{{clientId}}'], ['Client-Secret', '{{clientSecret}}']], descricao: 'Par de chaves gerado em Perfil do usuário → Chaves de acesso (no mesmo ambiente). Não exige certificado.'),
        req('Autenticar — certificado A1 (mTLS)', 'POST', ['portal', 'api', 'autenticar'], testes: $cabecalhoErroAuth, headers: [['Role-Type', '{{roleType}}']], descricao: 'Configure o certificado em Settings → Certificates (host do ambiente, arquivo PFX + senha). IMPEXP exige e-CPF. Certificado A3 não funciona no Postman.'),
    ],
];

$atributos = [
    'name' => 'Cadastro de Atributos',
    'item' => [
        req('Atributos por NCM', 'GET', [...$C, 'atributo-ncm', '{{ncm}}'], ['modalidade' => '{{modalidade}}'], testes: $testeAtributosNcm, descricao: 'GET /cadatributos/api/ext/atributo-ncm/{codigo-ncm}. Filtros opcionais: data (yyyy-MM-dd), modalidade, objetivos (PRODUTO, TRATAMENTO_ADMINISTRATIVO, DUIMP), orgaosDemandantes.'),
        req('Consultar atributos por código', 'POST', [...$C, 'atributo', 'consulta-codigo'], body: "{\n  \"codigos\": [\"{{codigoAtributo}}\"],\n  \"data\": \"{{dataReferencia}}\"\n}", testes: status(200, 204), descricao: 'Até 100 códigos por chamada. Consulta (não altera dados) apesar do POST.'),
        req('Consultar atributos por nome', 'POST', [...$C, 'atributo', 'consulta-nome'], body: "{\n  \"nomes\": [\"Material constitutivo\"],\n  \"data\": \"{{dataReferencia}}\"\n}", testes: status(200, 204)),
        req('Download da relação por NCM (autenticado, ZIP)', 'GET', [...$C, 'atributo-ncm', 'download', 'json'], ['codigosNCM' => '{{ncm}}'], testes: status(200), headers: [['Accept', 'application/zip, application/json']], descricao: 'Retorna ZIP com JSON {versao, listaNcm, detalhesAtributos}.'),
        req('Histórico de versões da relação por NCM', 'GET', [...$C, 'atributo-ncm', 'historico-versoes'], testes: status(200, 204)),
        req('Download público da relação por NCM (sem autenticação)', 'GET', ['cadatributos', 'api', 'atributo-ncm', 'download', 'json'], ['perfil' => 'PUBLICO'], testes: status(200), headers: [['Accept', 'application/zip, application/json']], descricao: 'Público, atualizado diariamente à meia-noite. ~15 MB descompactado.'),
    ],
];

$catalogo = [
    'name' => 'Catálogo de Produtos',
    'description' => 'Requisições marcadas com [ESCRITA] alteram dados: use apenas no ambiente de Validação. No ambiente de Produção (permitirEscrita=false) elas são bloqueadas pelo pre-request da coleção.',
    'item' => [
        req('Consultar produtos', 'GET', $P, ['cpfCnpjRaiz' => '{{cnpjRaiz}}', 'ncm' => '{{ncm}}'], testes: status(200, 204, 206), descricao: '206 = resultado parcial. Outros filtros: codigo, codigoInterno, descricao, denominacao, situacao (0 ativado, 1 desativado, 2 rascunho), modalidade, períodos, paisOrigem, cpfCnpjFabricante, operadorEstrangeiroCodigo, dataReferencia.'),
        req('Detalhar versão do produto', 'GET', [...$P, '{{cnpjRaiz}}', '{{codigoProduto}}', '{{versao}}'], testes: status(200, 204)),
        req('Histórico de alterações', 'GET', [...$P, 'historico', '{{cnpjRaiz}}', '{{codigoProduto}}'], testes: status(200, 204)),
        req('Exportar catálogo (ZIP)', 'GET', [...$P, 'exportar', '{{cnpjRaiz}}', 'false'], testes: status(200, 204), headers: [['Accept', 'application/zip, application/json']]),
        req('[ESCRITA] Incluir produto', 'POST', [...$P, '{{cnpjRaiz}}'], body: $bodyProduto, testes: $testeProduto, descricao: 'Use apenas códigos de atributos do ambiente atual (Validação ≠ Produção) e com objetivo Produto.'),
        req('[ESCRITA] Incluir produto retroativo', 'POST', [...$P, '{{cnpjRaiz}}', '{{dataReferencia}}'], body: $bodyProduto, testes: $testeProduto),
        req('[ESCRITA] Incluir nova versão', 'PUT', [...$P, '{{cnpjRaiz}}', '{{codigoProduto}}'], body: $bodyProduto, testes: $testeProduto),
        req('[ESCRITA] Retificar versão', 'PUT', [...$P, '{{cnpjRaiz}}', '{{codigoProduto}}', '{{versao}}'], body: $bodyProduto, testes: $testeProduto),
        req('[ESCRITA] Desativar produto', 'PUT', [...$P, 'desativar', '{{cnpjRaiz}}', '{{codigoProduto}}'], testes: status(200, 204)),
        req('[ESCRITA] Incluir código interno', 'POST', [...$P, 'codigo-interno', '{{cnpjRaiz}}', '{{codigoProduto}}', '{{versao}}'], body: "{\n  \"codigoInterno\": \"{{codigoInterno}}\"\n}", testes: status(200, 204)),
        req('[ESCRITA] Remover código interno', 'DELETE', [...$P, 'codigo-interno', '{{cnpjRaiz}}', '{{codigoProduto}}', '{{versao}}'], body: "{\n  \"codigoInterno\": \"{{codigoInterno}}\"\n}", testes: status(200, 204)),
    ],
];

$fluxo = [
    'name' => 'Fluxo completo (Validação)',
    'description' => 'Execute em sequência no Runner, apenas no ambiente de Validação: atributos da NCM → incluir → detalhar → nova versão → desativar.',
    'item' => [
        req('1. Atributos da NCM (monta o produto)', 'GET', [...$C, 'atributo-ncm', '{{ncm}}'], ['modalidade' => '{{modalidade}}'], testes: $montarProduto),
        req('2. [ESCRITA] Incluir produto', 'POST', [...$P, '{{cnpjRaiz}}'], body: '{{produtoBody}}', testes: $testeProduto),
        req('3. Detalhar produto incluído', 'GET', [...$P, '{{cnpjRaiz}}', '{{codigoProduto}}', '{{versao}}'], testes: status(200)."\npm.test('Mesmo código', () => pm.expect(String(pm.response.json().codigo)).to.eql(pm.environment.get('codigoProduto')));"),
        req('4. [ESCRITA] Nova versão', 'PUT', [...$P, '{{cnpjRaiz}}', '{{codigoProduto}}'], body: '{{produtoBody}}', testes: $testeProduto),
        req('5. Histórico', 'GET', [...$P, 'historico', '{{cnpjRaiz}}', '{{codigoProduto}}'], testes: status(200)),
        req('6. [ESCRITA] Desativar', 'PUT', [...$P, 'desativar', '{{cnpjRaiz}}', '{{codigoProduto}}'], testes: status(200, 204)),
    ],
];

$colecao = [
    'info' => [
        'name' => 'Siscomex — Portal Único (IMPEXP)',
        '_postman_id' => '6f1d6a8e-3c39-4a63-9a43-5b8b8f3c1a01',
        'description' => "Autenticação, Cadastro de Atributos e Catálogo de Produtos da API pública do Portal Único Siscomex.\n\nSelecione o ambiente (Validação ou Produção). A coleção autentica automaticamente, reaproveita o token, respeita 60 s entre autenticações e captura o X-CSRF-Token renovado de toda resposta.\n\nVeja postman/README.md.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'event' => [script('prerequest', $preRequestColecao), script('test', $testesColecao)],
    'item' => [$autenticacao, $atributos, $catalogo, $fluxo],
];

/** @return array<string, mixed> */
function ambiente(string $id, string $nome, string $baseUrl, bool $permitirEscrita): array
{
    $vars = [
        ['baseUrl', $baseUrl, 'default'],
        ['roleType', 'IMPEXP', 'default'],
        ['authMode', 'chave', 'default'],
        ['clientId', '', 'secret'],
        ['clientSecret', '', 'secret'],
        ['cnpjRaiz', '', 'default'],
        ['ncm', '84733049', 'default'],
        ['modalidade', 'IMPORTACAO', 'default'],
        ['codigoProduto', '', 'default'],
        ['versao', '1', 'default'],
        ['codigoAtributo', '', 'default'],
        ['codigoInterno', 'SKU-TESTE-1', 'default'],
        ['dataReferencia', date('Y-m-d'), 'default'],
        ['produtoBody', '', 'default'],
        ['permitirEscrita', $permitirEscrita ? 'true' : 'false', 'default'],
        ['jwt', '', 'secret'],
        ['csrfToken', '', 'secret'],
        ['csrfExpiration', '', 'default'],
        ['lastAuthAt', '', 'default'],
    ];

    return [
        'id' => $id,
        'name' => $nome,
        'values' => array_map(static fn (array $v): array => ['key' => $v[0], 'value' => $v[1], 'type' => $v[2], 'enabled' => true], $vars),
        '_postman_variable_scope' => 'environment',
    ];
}

$json = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
file_put_contents($dir.'/Siscomex-PortalUnico.postman_collection.json', json_encode($colecao, $json)."\n");
file_put_contents($dir.'/Validacao.postman_environment.json', json_encode(ambiente('b3f0f5d2-7f1e-4d8a-9c55-2b8f0f4a6e10', 'Siscomex — Validação', 'https://val.portalunico.siscomex.gov.br', true), $json)."\n");
file_put_contents($dir.'/Producao.postman_environment.json', json_encode(ambiente('c7a1e9b4-2d3f-4b6a-8e77-9f1c3d5e7a20', 'Siscomex — Produção (somente consultas)', 'https://portalunico.siscomex.gov.br', false), $json)."\n");

echo "Coleção e ambientes gerados em {$dir}\n";
