<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Helpers;

use DiDom\Element;
use Hexlet\PageLoader\Exceptions\IncorrectDirectoryException;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\Utils\FileUtils;
use Hexlet\PageLoader\Utils\PathUtils;
use Hexlet\PageLoader\Utils\StringUtils;
use Hexlet\PageLoader\Utils\UrlUtils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;

final class DiDomElementsCollection
{
    /* @var Collection<Element> */
    private Collection $elements;
    /**
     * @var string[]
     * @example ['img' => 'src']
     */
    private array $attributesByDownloadableTagName;

    public function __construct(array $elements, array $attributesByDownloadableTagName)
    {
        $this->attributesByDownloadableTagName = $attributesByDownloadableTagName;

        $elementsCollection = collect($elements);
        if ($elementsCollection->every(fn (mixed $element) => $element instanceof Element)) {
            $this->elements = $elementsCollection;
        } else {
            throw new InvalidArgumentException();
        }
    }

    public function hasDownloadableAttributes(): self
    {
        $this->elements = $this->elements->filter(function (Element $element): bool {
            $attributeName = $this->getElementDownloadableAttributeName($element);

            return is_string($attributeName) && $element->hasAttribute($attributeName);
        });

        return $this;
    }

    public function normalizeDownloadableAttributesUrl(string $rootUrl): self
    {
        $this->elements = $this->elements->map(function (Element $element) use ($rootUrl): Element {
            $attributeName = $this->getElementDownloadableAttributeName($element);

            if (is_string($attributeName)) {
                $attributeUrl = $element->getAttribute($attributeName);

                if (is_string($attributeUrl)) {
                    $element->setAttribute($attributeName, UrlUtils::normalize($attributeUrl, $rootUrl));
                }
            }

            return $element;
        });

        return $this;
    }

    public function downloadableAttributesShareCommonDomainWith(string $rootUrl): self
    {
        $this->elements = $this->elements->filter(function (Element $element) use ($rootUrl): bool {
            $attributeUrl = $this->getElementDownloadableAttributeUrl($element);

            if ($attributeUrl === null) {
                return false;
            }

            /** @var string $rootUrlHost */
            $rootUrlHost = parse_url($rootUrl, PHP_URL_HOST);
            /** @var string $attributeUrlHost */
            $attributeUrlHost = parse_url($attributeUrl, PHP_URL_HOST);
            $commonDomain = StringUtils::longestCommonSubstring($rootUrlHost, $attributeUrlHost);

            return isset($commonDomain) && str_ends_with($rootUrlHost, $commonDomain) && str_ends_with($attributeUrlHost, $commonDomain);
        });

        return $this;
    }

    public function sortByDownloadableAttributesPreparedFilenames(): self
    {
        $this->elements = $this->elements->sortBy(function (Element $element): ?string {
            $attributeUrl = $this->getElementDownloadableAttributeUrl($element);

            return ($attributeUrl === null) ? null : UrlUtils::toFilename($attributeUrl);
        });

        return $this;
    }

    /**
     * @throws IncorrectDirectoryException | ClientExceptionInterface
     */
    public function downloadAttributeUrls(string $dirname, string $dirpath, ClientInterface $client): self
    {
        $dir = PathUtils::join($dirpath, $dirname);

        $this->elements = $this->elements->reduce(function (Collection $acc, Element $element) use ($client, $dirname, $dir): Collection {
            $attributeName = $this->getElementDownloadableAttributeName($element);
            if ($attributeName === null) {
                return $acc;
            }

            $attributeUrl = $element->getAttribute($attributeName);
            if ($attributeUrl === null) {
                return $acc;
            }

            if ($acc->has($attributeUrl)) {
                $relativePath = $acc->get($attributeUrl);
            } else {
                $content = $client->get($attributeUrl)->getBody()->getContents();

                $filename = UrlUtils::toFilename($attributeUrl);
                FileUtils::create($dir, $filename, $content);
                $relativePath = PathUtils::join($dirname, $filename);

                $element->setAttribute($attributeName, $relativePath);
            }

            return tap($acc, static fn (Collection $acc) => $acc->offsetSet($attributeUrl, $relativePath));
        }, collect());

        return $this;
    }

    private function getElementDownloadableAttributeName(Element $element): ?string
    {
        return Arr::first(
            Arr::where($this->attributesByDownloadableTagName, static fn (string $attribute, string $tag) => str_contains($tag, $element->tag))
        );
    }

    private function getElementDownloadableAttributeUrl(Element $element): ?string
    {
        $attributeName = $this->getElementDownloadableAttributeName($element);

        return ($attributeName === null) ? null : $element->getAttribute($attributeName);
    }
}
