<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Utils;

class PathUtils
{
    /**
     * @param string ...$parts
     */
    public static function join(...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
