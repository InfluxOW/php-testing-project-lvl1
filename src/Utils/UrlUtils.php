<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Utils;

use Hexlet\PageLoader\Exceptions\IncorrectUrlException;

class UrlUtils
{
    private const DEFAULT_SCHEME = 'https';

    /**
     * @throws IncorrectUrlException
     */
    public static function getRoot(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && is_string($scheme)) {
            return "{$scheme}://{$host}";
        }

        throw new IncorrectUrlException($url);
    }

    /**
     * @throws IncorrectUrlException
     */
    public static function normalize(string $url, ?string $rootUrl = null): string
    {
        if ($rootUrl === null && str_starts_with($url, '/')) {
            throw new IncorrectUrlException($url);
        }

        $fullUrl = str_starts_with($url, '/') && isset($rootUrl) ? "{$rootUrl}{$url}" : $url;
        $scheme = parse_url($fullUrl, PHP_URL_SCHEME);

        if ($scheme === null) {
            $fullUrl = vsprintf('%s://%s', [self::DEFAULT_SCHEME, $fullUrl]);
        }

        if (is_string($rootUrl) && parse_url($rootUrl, PHP_URL_HOST) === parse_url($fullUrl, PHP_URL_HOST)) {
            $rootUrlScheme = parse_url($rootUrl, PHP_URL_SCHEME);
            /** @var string $fullUrlScheme */
            $fullUrlScheme = parse_url($fullUrl, PHP_URL_SCHEME);
            if (is_string($rootUrlScheme)) {
                $fullUrl = str_replace($fullUrlScheme, $rootUrlScheme, $fullUrl);
            }
        }

        return trim($fullUrl, '/');
    }
}
