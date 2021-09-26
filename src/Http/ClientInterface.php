<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

interface ClientInterface
{
    /**
     * @throws ClientExceptionInterface
     */
    public function get(string|UriInterface $uri, array $options = []): ResponseInterface;
}
