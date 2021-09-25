<?php

declare(strict_types=1);

namespace Hexlet\PageLoader;

use Docopt;
use Hexlet\PageLoader\Http\Client;
use GuzzleHttp\Client as GuzzleClient;

final class Cli
{
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

        PageLoader::download($url, $dir, $client);
    }
}
