<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use DiDom\Document;
use DiDom\Element;
use GuzzleHttp\RequestOptions;
use Hexlet\PageLoader\Exceptions\IncorrectDirectoryException;
use Hexlet\PageLoader\Exceptions\IncorrectUrlException;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\Utils\FileUtils;
use Hexlet\PageLoader\Utils\UrlUtils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientExceptionInterface;

class PageLoader
{
    private const ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME = [
        'img' => 'src',
        'script' => 'src',
        'link' => 'href',
        'picture > source' => 'srcset',
    ];
    private const FILES_DIRECTORY_POSTFIX = '_files';

    /**
     * @param ClientInterface|string $client | Workaround for Hexlet tests
     * @throws IncorrectDirectoryException | IncorrectUrlException
     */
    public static function download(string $url, string $rootDirectory, ClientInterface|string $client): string
    {
        if (is_string($client)) {
            $client = new $client();
        }
        $url = UrlUtils::normalize($url);

        try {
            $content = $client->get($url)->getBody()->getContents();
            $document = new Document($content);

            self::downloadResources($document, $url, $rootDirectory, $client);
        } catch (ClientExceptionInterface) {
            throw new IncorrectUrlException($url);
        }

        return FileUtils::create($rootDirectory, self::prepareFilename($url), $document->html());
    }

    private static function prepareFilename(string $url, ?string $postfix = '.html'): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extensionInfo = is_string($path) ? pathinfo($path, PATHINFO_EXTENSION) : null;
        $extension = ($extensionInfo === '') ? null : $extensionInfo;
        $postfix = ($extension === null) ? $postfix : ".{$extension}";

        /** @var string $noExtensionUrl */
        $noExtensionUrl = ($extension === null) ? $url : preg_replace("/\.{$extension}$/", '', $url);
        /** @var string $noSchemeUrl */
        $noSchemeUrl = preg_replace('/^http(s)?:\/\//', '', $noExtensionUrl);
        $filename = preg_replace('/[^a-zA-Z0-9]/', '-', $noSchemeUrl);

        return "{$filename}{$postfix}";
    }

    /**
     * @param ClientInterface|mixed $client | Workaround for Hexlet tests
     * @throws IncorrectUrlException | ClientExceptionInterface
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function downloadResources(Document $document, string $url, string $rootDir, mixed $client): void
    {
        $rootUrl = UrlUtils::getRoot($url);
        $filesDirectory = implode('/', [$rootDir, self::prepareFilename($url, self::FILES_DIRECTORY_POSTFIX)]);

        $downloadableAttributes = implode(',', array_keys(self::ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME));
        /** @noinspection PhpUnhandledExceptionInspection */
        $tags = $document->find($downloadableAttributes);

        $getElementAttributeName = static function (Element $element): ?string {
            return Arr::first(
                Arr::where(
                    self::ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME,
                    static fn (string $attribute, string $tag) => str_contains($tag, $element->tag)
                )
            );
        };

        collect($tags)
            ->filter(static function (Element $element) use ($getElementAttributeName): bool {
                $attributeName = $getElementAttributeName($element);

                return is_string($attributeName) && $element->hasAttribute($attributeName);
            })
            ->map(static function (Element $element) use ($getElementAttributeName, $rootUrl): Element {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $attributeUrl */
                $attributeUrl = $element->getAttribute($attributeName);
                $normalizedUrl = UrlUtils::normalize($attributeUrl, $rootUrl);

                $element->setAttribute($attributeName, $normalizedUrl);

                return $element;
            })
            ->filter(static function (Element $element) use ($getElementAttributeName, $rootUrl): bool {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $attributeUrl */
                $attributeUrl = $element->getAttribute($attributeName);

                return parse_url($rootUrl, PHP_URL_HOST) === parse_url($attributeUrl, PHP_URL_HOST);
            })
            ->sortBy(static function (Element $element) use ($getElementAttributeName): string {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $attributeUrl */
                $attributeUrl = $element->getAttribute($attributeName);

                return self::prepareFilename($attributeUrl);
            })
            ->reduce(function (
                Collection $acc,
                Element $element
            ) use (
                $client,
                $filesDirectory,
                $rootDir,
                $getElementAttributeName
            ) {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $attributeUrl */
                $attributeUrl = $element->getAttribute($attributeName);

                if ($acc->has($attributeUrl)) {
                    $relativePath = $acc->get($attributeUrl);
                } else {
                    @mkdir($filesDirectory);

                    $absolutePath = vsprintf('%s/%s', [$filesDirectory, self::prepareFilename($attributeUrl)]);
                    $relativePath = str_replace("{$rootDir}/", '', $absolutePath);

                    $client->request('GET', $attributeUrl, [RequestOptions::SINK => $absolutePath]);

                    $element->setAttribute($attributeName, $relativePath);
                }

                return tap($acc, static fn (Collection $acc) => $acc->offsetSet($attributeUrl, $relativePath));
            }, collect());
    }
}
