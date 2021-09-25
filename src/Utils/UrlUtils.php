<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Utils;

use RuntimeException;

class UrlUtils
{
    public static function getRoot(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme === null && $host === null) {
            $host = parse_url("https://{$url}", PHP_URL_HOST);
        }

        if (is_string($host) && (is_string($scheme) || $scheme === null)) {
            return ($scheme === null) ? $host : "{$scheme}://{$host}";
        }

        throw new RuntimeException("Incorrect url {$url}");
    }
}
