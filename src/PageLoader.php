<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\Utils\FileUtils;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class PageLoader
{
    /**
     * @throws ClientExceptionInterface | RuntimeException
     */
    public static function download(string $url, string $directory, ClientInterface $client): string
    {
        $content = $client->get($url)->getBody()->getContents();
        return FileUtils::create($directory, self::prepareFilename($url), $content);
    }

    private static function prepareFilename(string $url, ?string $postfix = '.html'): string
    {
        $noSchemeUrl = preg_replace('/^http(s)?:\/\//', '', $url);
        $noTrailingSlashUrl = preg_replace('/\/?$/', '', $noSchemeUrl ?? $url);
        $filename = preg_replace('/[^a-zA-Z0-9]/', '-', $noTrailingSlashUrl ?? $url);
        return "{$filename}{$postfix}";
    }
}
