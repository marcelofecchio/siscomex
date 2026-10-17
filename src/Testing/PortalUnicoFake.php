<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Testing;

use Http\Discovery\Psr17FactoryDiscovery;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\ChaveAcesso;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\InMemoryTokenStore;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\PortalUnicoClient;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\FabricanteRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\OperadorEstrangeiroRequest;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto\ProdutoRequest;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Portal Único falso para os testes de quem usa o SDK: um cliente PSR-18 que devolve respostas
 * enfileiradas (nos formatos observados no portal real, sanitizados) e registra as requisições.
 *
 * A autenticação é respondida automaticamente e não consome a fila.
 *
 * ```php
 * $fake = new PortalUnicoFake();
 * $fake->codigoInternoLivre()->produtoIncluido(codigo: 123);
 * $client = $fake->cliente();
 *
 * $client->catalogo()->produtos()->buscarPorCodigoInterno('12345678', 'SKU-1'); // Livre
 * $client->catalogo()->produtos()->incluir('12345678', $request);              // codigo 123
 * $fake->chamadas(); // ['GET /catp/api/ext/produto', 'POST /catp/api/ext/produto/12345678']
 * ```
 *
 * @experimental
 */
final class PortalUnicoFake implements ClientInterface
{
    public const string CPF_CNPJ_RAIZ = '00000000';

    /** @var list<ResponseInterface|\Closure(RequestInterface): ResponseInterface> */
    private array $fila = [];

    /** @var list<RequestInterface> */
    private array $requisicoes = [];

    private int $autenticacoes = 0;

    public readonly SemEspera $sleeper;

    public function __construct(private readonly Ambiente $ambiente = Ambiente::Validacao)
    {
        $this->sleeper = new SemEspera();
    }

    /**
     * Cliente do SDK ligado a este fake. Padrão: sem repetições em 503 (`tentativas503: 0`),
     * para cada resposta enfileirada corresponder a uma chamada.
     */
    public function cliente(?ClientConfig $config = null, ?LoggerInterface $logger = null, ?CacheInterface $cacheAtributos = null): PortalUnicoClient
    {
        return PortalUnicoClient::create(
            credencial: new ChaveAcesso('fake-client-id', 'fake-client-secret'),
            cache: new InMemoryTokenStore(),
            logger: $logger,
            config: $config ?? new ClientConfig(ambiente: $this->ambiente, tentativas503: 0),
            httpClient: $this,
            sleeper: $this->sleeper,
            cacheAtributos: $cacheAtributos,
        );
    }

    // ----------------------------------------------------------------- respostas genéricas

    /** @param array<string, string> $headers */
    public function responder(int $status, mixed $corpo = null, array $headers = []): self
    {
        $resposta = Psr17FactoryDiscovery::findResponseFactory()->createResponse($status);
        foreach ($headers as $nome => $valor) {
            $resposta = $resposta->withHeader($nome, $valor);
        }
        if ($corpo !== null) {
            $resposta = $resposta
                ->withHeader('Content-Type', 'application/json')
                ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream(json_encode($corpo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
        }
        $this->fila[] = $resposta;

        return $this;
    }

    /** HTTP 204 (consulta sem resultado, desativação sem corpo etc.). */
    public function semConteudo(): self
    {
        return $this->responder(204);
    }

    /**
     * Erro no formato do portal: {message, code, tag, status, severity, detail: [{error: {...}}]}.
     *
     * @param list<array{mensagem: string, codigo?: string|null, campo?: string|null}> $detalhes
     */
    public function erro(int $status, ?string $codigo, string $mensagem, array $detalhes = [], string $tag = '[FAKE-TAG]'): self
    {
        return $this->responder($status, [
            'message' => $mensagem,
            'code' => $codigo,
            'tag' => $tag,
            'status' => $status,
            'severity' => 'ERROR',
            'detail' => array_map(static fn(array $d): array => ['error' => ['message' => $d['mensagem'], 'code' => $d['codigo'] ?? null, 'field' => $d['campo'] ?? null]], $detalhes),
        ]);
    }

    /**
     * 422 de validação de negócio, como o do Catálogo (PUCX-ER0123 com um detalhe por campo).
     *
     * @param array<string, string> $mensagensPorCampo ex.: ['ATT_5426' => 'O Atributo ATT_5426 para o NCM 84733049 é obrigatório.']
     */
    public function erroValidacao(array $mensagensPorCampo, string $codigoDetalhe = 'CADA-ER5008'): self
    {
        $detalhes = [];
        foreach ($mensagensPorCampo as $campo => $mensagem) {
            $detalhes[] = ['mensagem' => $mensagem, 'codigo' => $codigoDetalhe, 'campo' => (string) $campo];
        }

        return $this->erro(422, 'PUCX-ER0123', 'Problemas encontrados: ', $detalhes);
    }

    /**
     * PUCX-ER1001. O status HTTP real ainda não foi observado (TODO(confirmar)); o SDK classifica
     * pelo código.
     */
    public function rateLimit(string $liberadoAs = '15:00', int $limite = 1000, int $status = 429): self
    {
        return $this->erro($status, 'PUCX-ER1001', \sprintf('Limite de %d acessos por hora excedido. Tente novamente após as %s.', $limite, $liberadoAs));
    }

    /** 503 da parada programada (PUCX-ER0401), que o SDK nunca repete. */
    public function indisponivel(): self
    {
        return $this->erro(503, 'PUCX-ER0401', 'O Portal Único de Comércio Exterior encontra-se em uma parada programada que ocorre diariamente entre 01:00 e 03:00.');
    }

    /** A conexão cai durante a próxima chamada (em POST, o item de um lote fica Incerto). */
    public function falhaDeRede(string $mensagem = 'Connection reset by peer'): self
    {
        $this->fila[] = static fn(RequestInterface $r): ResponseInterface => throw new FalhaDeRede($mensagem, $r);

        return $this;
    }

    // ----------------------------------------------------------------- cenários do Catálogo

    /** Resposta de incluir/nova versão/retificar/detalhar produto (ProdutoIntegracaoResponseDTO). */
    public function produtoIncluido(int $codigo = 1, ?ProdutoRequest $request = null, string $versao = '1', string $situacao = 'ATIVADO'): self
    {
        return $this->responder(200, self::produto($codigo, $request, $versao, $situacao));
    }

    /** Consulta por código interno sem resultado. */
    public function codigoInternoLivre(): self
    {
        return $this->semConteudo();
    }

    public function codigoInternoAtivo(string $codigoInterno, int $codigoProduto = 1): self
    {
        return $this->responder(200, [self::produtoConsultado(1, $codigoProduto, $codigoInterno, 'Ativado')]);
    }

    public function codigoInternoDesativado(string $codigoInterno, int $codigoProduto = 1): self
    {
        return $this->responder(200, [self::produtoConsultado(1, $codigoProduto, $codigoInterno, 'Desativado')]);
    }

    /** O mesmo código interno em vários produtos ativos (o portal permite). */
    public function codigoInternoDuplicado(string $codigoInterno, int ...$codigosProduto): self
    {
        $codigosProduto = $codigosProduto === [] ? [1, 2] : $codigosProduto;
        $lista = [];
        foreach (array_values($codigosProduto) as $i => $codigo) {
            $lista[] = self::produtoConsultado($i + 1, $codigo, $codigoInterno, 'Ativado');
        }

        return $this->responder(200, $lista);
    }

    /** Resposta de incluir/nova versão/retificar/detalhar operador estrangeiro. */
    public function operadorIncluido(string $codigo = 'OPE_1', string $codigoPais = 'CN', string $versao = '1', ?OperadorEstrangeiroRequest $request = null): self
    {
        return $this->responder(200, ($request?->toArray() ?? ['nome' => 'Fornecedor Fake', 'logradouro' => 'Rua 1', 'nomeCidade' => 'Shenzhen', 'identificacoesAdicionais' => []]) + [
            'cpfCnpjRaiz' => self::CPF_CNPJ_RAIZ,
            'codigo' => $codigo,
            'versao' => $versao,
            'situacao' => 'ATIVADO',
            'codigoPais' => $codigoPais,
            'inicioVigencia' => date('Y-m-d\TH:i:s'),
            'fimVigencia' => null,
        ]);
    }

    /** Resposta de vincular/desvincular fabricante (FabricanteIntegracaoResponseDTO). */
    public function fabricanteVinculado(?FabricanteRequest $request = null): self
    {
        return $this->responder(200, ($request?->toArray() ?? ['codigoProduto' => 1, 'codigoPais' => 'CN', 'conhecido' => false]) + ['cpfCnpjRaiz' => self::CPF_CNPJ_RAIZ]);
    }

    // ----------------------------------------------------------------- inspeção

    /** @return list<RequestInterface> requisições recebidas, sem as de autenticação */
    public function requisicoes(): array
    {
        return $this->requisicoes;
    }

    public function ultimaRequisicao(): RequestInterface
    {
        return $this->requisicoes[\count($this->requisicoes) - 1] ?? throw new \LogicException('PortalUnicoFake: nenhuma requisição recebida.');
    }

    /** Corpo JSON decodificado da última requisição. */
    public function corpoDaUltima(): mixed
    {
        $corpo = (string) $this->ultimaRequisicao()->getBody();

        return $corpo === '' ? null : json_decode($corpo, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> "MÉTODO path" de cada requisição (sem autenticação) */
    public function chamadas(): array
    {
        return array_map(static fn(RequestInterface $r): string => $r->getMethod().' '.$r->getUri()->getPath(), $this->requisicoes);
    }

    public function autenticacoes(): int
    {
        return $this->autenticacoes;
    }

    /** Respostas enfileiradas que não foram usadas. */
    public function respostasPendentes(): int
    {
        return \count($this->fila);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (str_starts_with($request->getUri()->getPath(), '/portal/api/autenticar')) {
            ++$this->autenticacoes;

            return Psr17FactoryDiscovery::findResponseFactory()->createResponse(200)
                ->withHeader('Set-Token', 'Bearer fake.jwt.'.$this->autenticacoes)
                ->withHeader('X-CSRF-Token', 'fake-csrf-'.$this->autenticacoes)
                ->withHeader('X-CSRF-Expiration', (string) ((time() + 3600) * 1000));
        }

        $this->requisicoes[] = $request;
        $proxima = array_shift($this->fila);
        if ($proxima === null) {
            throw new \LogicException(\sprintf('PortalUnicoFake: nenhuma resposta enfileirada para %s %s.', $request->getMethod(), $request->getUri()->getPath()));
        }

        return $proxima instanceof \Closure ? $proxima($request) : $proxima;
    }

    /** @return array<string, mixed> */
    private static function produto(int $codigo, ?ProdutoRequest $request, string $versao, string $situacao): array
    {
        return ($request?->toArray() ?? [
            'denominacao' => 'Produto fake',
            'modalidade' => 'IMPORTACAO',
            'ncm' => '84733049',
            'atributos' => [],
            'atributosMultivalorados' => [],
            'atributosCompostos' => [],
            'atributosCompostosMultivalorados' => [],
            'codigosInterno' => [],
        ]) + [
            'codigo' => $codigo,
            'versao' => $versao,
            'cpfCnpjRaiz' => self::CPF_CNPJ_RAIZ,
            'situacao' => $situacao,
            'inicioVigencia' => date('Y-m-d\TH:i:s'),
            'fimVigencia' => null,
        ];
    }

    /**
     * Item de GET /ext/produto, no formato observado (situacao capitalizada, com seq).
     *
     * @return array<string, mixed>
     */
    private static function produtoConsultado(int $seq, int $codigo, string $codigoInterno, string $situacao): array
    {
        return ['seq' => $seq, 'codigosInterno' => [$codigoInterno]] + self::produto($codigo, null, '1', $situacao);
    }
}
