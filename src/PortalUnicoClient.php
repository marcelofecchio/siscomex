<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Authenticator;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Credentials\Credential;
use MarceloFecchio\Siscomex\PortalUnico\Auth\ResultadoConexao;
use MarceloFecchio\Siscomex\PortalUnico\Auth\Session;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\InMemoryTokenStore;
use MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore\TokenStore;
use MarceloFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MarceloFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MarceloFecchio\Siscomex\PortalUnico\Config\Perfil;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;
use MarceloFecchio\Siscomex\PortalUnico\Http\EscritaSimulada;
use MarceloFecchio\Siscomex\PortalUnico\Http\GuzzleClientFactory;
use MarceloFecchio\Siscomex\PortalUnico\Http\Transport;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\AtributosPublicos;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\AtributosResource;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\CatalogoResource;
use MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\TabelasResource;
use MarceloFecchio\Siscomex\PortalUnico\Support\Sleeper;
use MarceloFecchio\Siscomex\PortalUnico\Support\SystemClock;
use MarceloFecchio\Siscomex\PortalUnico\Support\SystemSleeper;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Fachada do SDK.
 *
 * ```php
 * $client = PortalUnicoClient::create(
 *     ambiente: Ambiente::Validacao,
 *     credencial: ChaveAcesso::fromEnv(),
 *     cache: new FileTokenStore(__DIR__.'/storage'),
 * );
 * $atributos = $client->atributos()->porNcm('84733049');
 * ```
 */
final class PortalUnicoClient
{
    private ?AtributosResource $atributos = null;

    private ?CatalogoResource $catalogo = null;

    private ?AtributosPublicos $atributosPublicos = null;

    private ?TabelasResource $tabelas = null;

    public function __construct(
        public readonly ClientConfig $config,
        private readonly Transport $transport,
        private readonly ?Authenticator $authenticator,
        private readonly ?CacheInterface $cacheAtributos = null,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {}

    /**
     * @param ClientConfig|null $config se informado, prevalece sobre $ambiente e $perfil
     * @param ClientInterface|null $httpClient     cliente PSR-18 próprio; para mTLS ele já deve apresentar o certificado
     * @param CacheInterface|null  $cacheAtributos cache PSR-16 opcional de atributos()->porNcm() (expira à meia-noite)
     */
    public static function create(
        Ambiente $ambiente = Ambiente::Validacao,
        ?Credential $credencial = null,
        Perfil $perfil = Perfil::IMPEXP,
        ?TokenStore $cache = null,
        ?LoggerInterface $logger = null,
        ?ClientConfig $config = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?ClockInterface $clock = null,
        ?Sleeper $sleeper = null,
        ?CacheInterface $cacheAtributos = null,
    ): self {
        $config ??= new ClientConfig(ambiente: $ambiente, perfil: $perfil);
        $logger ??= new NullLogger();
        $clock ??= new SystemClock();
        $sleeper ??= new SystemSleeper();
        $cache ??= new InMemoryTokenStore();

        if ($httpClient === null) {
            $httpClient = GuzzleClientFactory::disponivel()
                ? GuzzleClientFactory::create($config, $credencial)
                : ($credencial?->exigeMtls() === true
                    ? throw new \LogicException('Autenticação com certificado requer guzzlehttp/guzzle ou um cliente PSR-18 já configurado com o certificado.')
                    : Psr18ClientDiscovery::find());
        }
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();

        $authenticator = $credencial === null ? null : new Authenticator(
            $httpClient,
            $requestFactory,
            $credencial,
            $config,
            $cache,
            $clock,
            $sleeper,
            $logger,
        );

        $transport = new Transport($httpClient, $requestFactory, $streamFactory, $config, $authenticator, $clock, $sleeper, $logger);

        return new self($config, $transport, $authenticator, $cacheAtributos, $clock);
    }

    /** Cadastro de Atributos (requer credencial). */
    public function atributos(): AtributosResource
    {
        return $this->atributos ??= new AtributosResource($this->transport, $this->cacheAtributos, $this->clock);
    }

    /** Catálogo de Produtos (requer credencial). */
    public function catalogo(): CatalogoResource
    {
        return $this->catalogo ??= new CatalogoResource($this->transport);
    }

    /** Tabelas Comex — TABX (requer credencial; somente consultas). */
    public function tabelas(): TabelasResource
    {
        return $this->tabelas ??= new TabelasResource($this->transport);
    }

    /** Download público da relação de atributos por NCM (não requer credencial). */
    public function atributosPublicos(): AtributosPublicos
    {
        return $this->atributosPublicos ??= new AtributosPublicos($this->transport);
    }

    /**
     * Garante uma sessão válida (reaproveita a do cache, se houver) e a retorna.
     */
    public function autenticar(): Session
    {
        return $this->authenticator()->session();
    }

    /**
     * Verifica as credenciais sem lançar exceção (para um botão "Testar conexão").
     *
     * Reaproveita a sessão em cache, se houver; caso contrário autentica (respeitando os 60 s).
     * Uma sessão em cache pode já ter sido invalidada por outra autenticação com a mesma chave:
     * nesse caso o erro só aparece na próxima chamada à API (que reautentica uma vez).
     *
     * @throws \LogicException se o cliente foi criado sem credencial
     */
    public function testarConexao(): ResultadoConexao
    {
        $authenticator = $this->authenticator();

        try {
            return ResultadoConexao::sucesso($this->config->ambiente, $this->config->perfil, $authenticator->session());
        } catch (SiscomexException $e) {
            return ResultadoConexao::falha($this->config->ambiente, $this->config->perfil, $e);
        }
    }

    /** Descarta a sessão em cache (a próxima chamada autentica novamente, respeitando os 60 s). */
    public function encerrarSessao(): void
    {
        $this->authenticator()->invalidate();
    }

    /**
     * Escritas registradas e não enviadas quando ClientConfig::$simularEscrita está ativo.
     *
     * @return list<EscritaSimulada>
     */
    public function escritasSimuladas(): array
    {
        return $this->transport->escritasSimuladas();
    }

    /** Acesso de baixo nível para endpoints ainda não cobertos pelo SDK. */
    public function transport(): Transport
    {
        return $this->transport;
    }

    private function authenticator(): Authenticator
    {
        return $this->authenticator ?? throw new \LogicException('Nenhuma credencial informada ao criar o PortalUnicoClient.');
    }
}
