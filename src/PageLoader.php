<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use DiDom\Document;
use Hexlet\PageLoader\Exceptions\IncorrectDirectoryException;
use Hexlet\PageLoader\Exceptions\IncorrectUrlException;
use Hexlet\PageLoader\Helpers\DiDomElementsCollection;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\Utils\FileUtils;
use Hexlet\PageLoader\Utils\UrlUtils;
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
    public static function download(string $url, string $dirpath, ClientInterface $client): string
    {
        $url = UrlUtils::normalize($url);

        try {
            $content = $client->get($url)->getBody()->getContents();
            $document = new Document($content);

            self::downloadResources($document, $url, $dirpath, $client);
        } catch (ClientExceptionInterface) {
            throw new IncorrectUrlException($url);
        }

        return FileUtils::create($dirpath, UrlUtils::toFilename($url), $document->html());
    }

    /**
     * @throws IncorrectUrlException | IncorrectDirectoryException | ClientExceptionInterface
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function downloadResources(Document $document, string $url, string $dirpath, ClientInterface $client): void
    {
        $rootUrl = UrlUtils::getRoot($url);
        $filesDirname = UrlUtils::toFilename($url, self::FILES_DIRECTORY_POSTFIX);

        $tagNames = implode(',', array_keys(self::ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME));
        /** @noinspection PhpUnhandledExceptionInspection */
        $elements = $document->find($tagNames);

        (new DiDomElementsCollection($elements, self::ATTRIBUTES_BY_DOWNLOADABLE_TAG_NAME))
            ->hasDownloadableAttributes()
            ->normalizeDownloadableAttributesUrl($rootUrl)
            ->downloadableAttributesShareCommonDomainWith($rootUrl)
            ->sortByDownloadableAttributesPreparedFilenames()
            ->downloadAttributeUrls($filesDirname, $dirpath, $client);
    }
}
