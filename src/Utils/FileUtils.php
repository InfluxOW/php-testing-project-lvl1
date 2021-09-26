<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Utils;

use Hexlet\PageLoader\Exceptions\IncorrectDirectoryException;
use Hexlet\PageLoader\Exceptions\IncorrectFileException;

class FileUtils
{
    /**
     * @throws IncorrectDirectoryException
     */
    public static function create(string $dir, string $filename, mixed $content): string
    {
        @mkdir($dir);

        if (is_dir($dir) && is_writable($dir)) {
            $path = "{$dir}/{$filename}";
            file_put_contents($path, $content);

            return $path;
        }

        throw new IncorrectDirectoryException($dir);
    }

    /**
     * @throws IncorrectFileException
     */
    public static function get(string $path): string
    {
        if (is_file($path) && $file = file_get_contents($path)) {
            return $file;
        }

        throw new IncorrectFileException($path);
    }
}
