<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Utils;

class StringUtils
{
    public static function longestCommonSubstring(string $first, string $second): ?string
    {
        $length1 = strlen($first);
        $length2 = strlen($second);

        if ($length1 < $length2) {
            $shortest = $first;
            $longest = $second;
            $lengthShortest = $length1;
        } else {
            $shortest = $second;
            $longest = $first;
            $lengthShortest = $length2;
        }

        $pos = strpos($longest, $shortest);
        if (is_int($pos)) {
            return $shortest;
        }

        /* @phpstan-ignore-next-line */
        for ($i = 1, $j = $lengthShortest - 1; $j > 0; --$j, ++$i) {
            /* @phpstan-ignore-next-line */
            for ($k = 0; $k <= $i; ++$k) {
                $substr = substr($shortest, $k, $j);
                $pos = strpos($longest, $substr);
                if (is_int($pos)) {
                    return $substr;
                }
            }
        }

        return null;
    }
}
