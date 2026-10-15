<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente PSR-18 que devolve respostas enfileiradas e registra as requisições.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable|\Closure(RequestInterface): ResponseInterface> */
    private array $queue = [];

    /** @param ResponseInterface|\Throwable|\Closure(RequestInterface): ResponseInterface ...$responses */
    public function queue(ResponseInterface|\Throwable|\Closure ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('FakeHttpClient: nenhuma resposta enfileirada para '.$request->getMethod().' '.$request->getUri());
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }
        if ($next instanceof \Closure) {
            return $next($request);
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        return $this->requests[\count($this->requests) - 1] ?? throw new \LogicException('Nenhuma requisição enviada.');
    }

    public function request(int $index): RequestInterface
    {
        return $this->requests[$index] ?? throw new \LogicException("Requisição {$index} não existe.");
    }

    public function pending(): int
    {
        return \count($this->queue);
    }

    /** @return list<string> "METHOD path" de cada requisição */
    public function calls(): array
    {
        return array_map(
            static fn(RequestInterface $r): string => $r->getMethod().' '.$r->getUri()->getPath(),
            $this->requests,
        );
    }
}
