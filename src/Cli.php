<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use Docopt;
use Hexlet\PageLoader\Exceptions\BasePageLoaderException;
use Hexlet\PageLoader\Http\Client;
use GuzzleHttp\Client as GuzzleClient;

final class Cli
{
    private const EXIT_GENERIC_ERROR = 1;
    private const HELP = <<<'DOC'

    Download HTML page
    
    Usage:
        page-loader (-h|--help)
        page-loader (-v|--version)
        page-loader [--output | -o <directory>] <url>
    
    Options:
        -h --help                     Show this screen
        -v --version                  Show version
        --output -o <directory>       Output directory [default: .]

    DOC;

    public static function run(): void
    {
        $args = Docopt::handle(self::HELP, ['version' => "v1.0.0"]);
        $url = $args["<url>"];
        [$dir] = $args['--output'];

        $client = new Client(new GuzzleClient());

        try {
            $path = PageLoader::download($url, $dir, $client);
        } catch (BasePageLoaderException $e) {
            fwrite(STDERR, $e->prepareMessage() . PHP_EOL);
            exit(self::EXIT_GENERIC_ERROR);
        }

        print_r("Page was successfully downloaded. See {$path}." . PHP_EOL);
    }
}
