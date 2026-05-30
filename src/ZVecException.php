<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use RuntimeException;
use Throwable;

if (extension_loaded('zvec')) return;

class ZVecException extends RuntimeException
{
    private ?string $errorFile = null;
    private ?int $errorLine = null;
    private ?string $errorFunction = null;

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, ?string $errorFile = null, ?int $errorLine = null, ?string $errorFunction = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorFile = $errorFile;
        $this->errorLine = $errorLine;
        $this->errorFunction = $errorFunction;
    }

    public function getErrorFile(): ?string
    {
        return $this->errorFile;
    }

    public function getErrorLine(): ?int
    {
        return $this->errorLine;
    }

    public function getErrorFunction(): ?string
    {
        return $this->errorFunction;
    }

    public function getErrorCodeString(): string
    {
        return match ($this->getCode()) {
            0 => 'OK',
            1 => 'NOT_FOUND',
            2 => 'ALREADY_EXISTS',
            3 => 'INVALID_ARGUMENT',
            4 => 'PERMISSION_DENIED',
            5 => 'FAILED_PRECONDITION',
            6 => 'RESOURCE_EXHAUSTED',
            7 => 'UNAVAILABLE',
            8 => 'INTERNAL_ERROR',
            9 => 'NOT_SUPPORTED',
            10 => 'UNKNOWN',
            default => 'UNRECOGNIZED',
        };
    }
}
