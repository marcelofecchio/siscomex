<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use MFecchio\Siscomex\PortalUnico\Auth\Authenticator;
use MFecchio\Siscomex\PortalUnico\Auth\Credentials\Credential;
use MFecchio\Siscomex\PortalUnico\Auth\Session;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\InMemoryTokenStore;
use MFecchio\Siscomex\PortalUnico\Auth\TokenStore\TokenStore;
use MFecchio\Siscomex\PortalUnico\Config\Ambiente;
use MFecchio\Siscomex\PortalUnico\Config\ClientConfig;
use MFecchio\Siscomex\PortalUnico\Config\Perfil;
use MFecchio\Siscomex\PortalUnico\Http\GuzzleClientFactory;
use MFecchio\Siscomex\PortalUnico\Http\Transport;
use MFecchio\Siscomex\PortalUnico\Resources\Atributos\AtributosPublicos;
use MFecchio\Siscomex\PortalUnico\Resources\Atributos\AtributosResource;
use MFecchio\Siscomex\PortalUnico\Resources\Catalogo\CatalogoResource;
use MFecchio\Siscomex\PortalUnico\Support\Sleeper;
use MFecchio\Siscomex\PortalUnico\Support\SystemClock;
use MFecchio\Siscomex\PortalUnico\Support\SystemSleeper;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    public function __construct(
        public readonly ClientConfig $config,
        private readonly Transport $transport,
        private readonly ?Authenticator $authenticator,
    ) {}

    /**
     * @param ClientConfig|null $config se informado, prevalece sobre $ambiente e $perfil
     * @param ClientInterface|null $httpClient cliente PSR-18 próprio; para mTLS ele já deve apresentar o certificado
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

        return new self($config, $transport, $authenticator);
    }

    /** Cadastro de Atributos (requer credencial). */
    public function atributos(): AtributosResource
    {
        return $this->atributos ??= new AtributosResource($this->transport);
    }

    /** Catálogo de Produtos (requer credencial). */
    public function catalogo(): CatalogoResource
    {
        return $this->catalogo ??= new CatalogoResource($this->transport);
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

    /** Descarta a sessão em cache (a próxima chamada autentica novamente, respeitando os 60 s). */
    public function encerrarSessao(): void
    {
        $this->authenticator()->invalidate();
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
