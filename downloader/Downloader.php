<?php

declare(strict_types=1);

namespace Downloader\Downloader;

use Hexlet\PageLoader\Exceptions\BasePageLoaderException;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\PageLoader;

/**
 * Workaround for Hexlet tests
 *
 * @throws BasePageLoaderException
 */
function downloadPage(string $url, string $rootDirectory, ClientInterface|string $client): string
{
    return PageLoader::download($url, $rootDirectory, $client);
}
