<?php

namespace Downloader\Downloader;

use Hexlet\PageLoader\PageLoader;

/**
 * Workaround for Hexlet tests
 */
function downloadPage(string $url, string $directory, string $client): string
{
    return PageLoader::download($url, $directory, $client);
}
