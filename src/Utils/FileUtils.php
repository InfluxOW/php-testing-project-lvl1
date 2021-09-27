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
    public static function create(string $dirpath, string $filename, mixed $content): string
    {
        @mkdir($dirpath, 0777, true);

        if (is_dir($dirpath) && is_writable($dirpath)) {
            $path = PathUtils::join($dirpath, $filename);
            file_put_contents($path, $content);

            return $path;
        }

        throw new IncorrectDirectoryException($dirpath);
    }

    /**
     * @throws IncorrectFileException
     */
    public static function get(string $path): string
    {
        if (is_file($path)) {
            $file = file_get_contents($path);

            if (is_string($file)) {
                return $file;
            }
        }

        throw new IncorrectFileException($path);
    }
}
