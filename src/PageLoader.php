<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use DiDom\Document;
use DiDom\Element;
use Hexlet\PageLoader\Exceptions\IncorrectDirectoryException;
use Hexlet\PageLoader\Exceptions\IncorrectUrlException;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\Utils\FileUtils;
use Hexlet\PageLoader\Utils\StringUtils;
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
     * @throws IncorrectDirectoryException | IncorrectUrlException
     */
    public static function download(string $url, string $rootDirectory, ClientInterface $client): string
    {
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
     * @throws IncorrectUrlException | IncorrectDirectoryException | ClientExceptionInterface
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function downloadResources(Document $document, string $url, string $rootDirectory, ClientInterface $client): void
    {
        $rootUrl = UrlUtils::getRoot($url);
        $filesDirectory = implode('/', [$rootDirectory, self::prepareFilename($url, self::FILES_DIRECTORY_POSTFIX)]);

        $downloadableAttributes = implode(',', array_keys(self::ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME));
        /** @noinspection PhpUnhandledExceptionInspection */
        $tags = $document->find($downloadableAttributes);

        $getElementAttributeName = static function (Element $element): ?string {
            return Arr::first(Arr::where(self::ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME, static fn (string $attribute, string $tag) => str_contains($tag, $element->tag)));
        };

        collect($tags)
            ->filter(static function (Element $element) use ($getElementAttributeName) {
                $attributeName = $getElementAttributeName($element);

                return is_string($attributeName) && $element->hasAttribute($attributeName);
            })
            ->map(static function (Element $element) use ($getElementAttributeName, $rootUrl) {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $attributeUrl */
                $attributeUrl = $element->getAttribute($attributeName);
                $normalizedUrl = UrlUtils::normalize($attributeUrl, $rootUrl);

                $element->setAttribute($attributeName, $normalizedUrl);

                return $element;
            })
            ->filter(static function (Element $element) use ($getElementAttributeName, $rootUrl) {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $attributeUrl */
                $attributeUrl = $element->getAttribute($attributeName);

                /** @var string $rootUrlHost */
                $rootUrlHost = parse_url($rootUrl, PHP_URL_HOST);
                /** @var string $attributeUrlHost */
                $attributeUrlHost = parse_url($attributeUrl, PHP_URL_HOST);
                $commonDomain = StringUtils::longestCommonSubstring($rootUrlHost, $attributeUrlHost);

                return isset($commonDomain) && str_ends_with($rootUrlHost, $commonDomain) && str_ends_with($attributeUrlHost, $commonDomain);
            })
            ->sortBy(static function (Element $element) use ($getElementAttributeName) {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);

                return $element->getAttribute($attributeName);
            })
            ->reduce(function (Collection $acc, Element $element) use ($client, $filesDirectory, $rootDirectory, $getElementAttributeName) {
                /** @var string $attributeName */
                $attributeName = $getElementAttributeName($element);
                /** @var string $url */
                $url = $element->getAttribute($attributeName);

                if ($acc->has($url)) {
                    $relativePath = $acc->get($url);
                } else {
                    $content = $client->get($url)->getBody()->getContents();

                    $absolutePath = FileUtils::create($filesDirectory, self::prepareFilename($url), $content);
                    $relativePath = str_replace("{$rootDirectory}/", '', $absolutePath);

                    $element->setAttribute($attributeName, $relativePath);
                }

                return tap($acc, static fn (Collection $acc) => $acc->offsetSet($url, $relativePath));
            }, collect());
    }
}
