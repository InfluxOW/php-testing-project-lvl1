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

        return trim($fullUrl, '/');
    }
}
