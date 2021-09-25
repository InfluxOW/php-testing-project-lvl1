<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use DiDom\Document;
use DiDom\Element;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\Utils\FileUtils;
use Hexlet\PageLoader\Utils\UrlUtils;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class PageLoader
{
    /**
     * @param \Hexlet\PageLoader\Http\ClientInterface|string $client
     * Workaround for Hexlet tests
     *
     * @throws ClientExceptionInterface | RuntimeException
     */
    public static function download(string $url, string $rootDirectory, ClientInterface|string $client): string
    {
        if (is_string($client)) {
            $client = new $client();
        }

        $content = $client->get($url)->getBody()->getContents();
        $document = new Document($content);

        $rootUrl = UrlUtils::getRoot($url);
        $filesDirectory = implode('/', [$rootDirectory, self::prepareFilename($url, '_files')]);
        self::downloadImages($document, $rootUrl, $rootDirectory, $filesDirectory, $client);

        return FileUtils::create($rootDirectory, self::prepareFilename($url), $document->html());
    }

    private static function prepareFilename(string $url, ?string $postfix = '.html'): string
    {
        $noSchemeUrl = preg_replace('/^http(s)?:\/\//', '', $url);
        $noTrailingSlashUrl = preg_replace('/\/?$/', '', $noSchemeUrl ?? $url);
        $filename = preg_replace('/[^a-zA-Z0-9]/', '-', $noTrailingSlashUrl ?? $url);
        return "{$filename}{$postfix}";
    }

    private static function downloadImages(Document $document, string $rootUrl, string $rootDirectory, string $directory, mixed $client): void
    {
        collect($document->find('img'))->map(function (Element $element) use ($client, $directory, $rootUrl, $rootDirectory) {
            $src = $element->getAttribute('src');
            if (isset($src)) {
                $src = str_starts_with('/', $src) ? "{$rootUrl}{$src}" : $src;
                $content = $client->get($src)->getBody()->getContents();
                $fileName = basename($src);

                $absolutePath = FileUtils::create($directory, $fileName, $content);
                $relativePath = str_replace("{$rootDirectory}/", '', $absolutePath);

                $element->setAttribute('src', $relativePath);
            }
            return $element;
        });
    }
}
