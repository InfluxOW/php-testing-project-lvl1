<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Http;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Client implements ClientInterface
{
    public function __construct(private GuzzleClient $client)
    {
    }

    public function get(UriInterface|string $uri, array $options = []): ResponseInterface
    {
        return $this->client->get($uri, $options);
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}
