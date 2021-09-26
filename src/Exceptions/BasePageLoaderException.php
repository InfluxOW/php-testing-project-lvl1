<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Exceptions;

use Exception;
use Illuminate\Support\Arr;

abstract class BasePageLoaderException extends Exception
{
    public function prepareMessage(): string
    {
        $exceptionClass = static::class;
        $exceptionClassBasename = preg_replace('/Exception$/', '', Arr::last(explode('\\', $exceptionClass)));
        /** @var string[] $pieces */
        $pieces = preg_split('/(?=[A-Z])/', $exceptionClassBasename);
        $error = ucfirst(strtolower(trim(implode(' ', $pieces))));
        return vsprintf('%s: %s', [$error, $this->getMessage()]);
    }
}
