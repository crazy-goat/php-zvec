<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

if (extension_loaded('zvec')) return;

/**
 * Options for creating or opening collections with createWith()/openWith().
 *
 * Controls read-only mode, memory-mapped I/O, and buffer size.
 * Use factory methods readOnly(), readWrite(), or defaults() for
 * common configurations.
 *
 * @see ZVec::createWith()
 * @see ZVec::openWith()
 */
class ZVecCollectionOptions
{
    public bool $readOnly = false;
    public bool $enableMmap = true;
    public int $maxBufferSize = 67108864;

    public function __construct(bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864)
    {
        $this->readOnly = $readOnly;
        $this->enableMmap = $enableMmap;
        $this->maxBufferSize = $maxBufferSize;
    }

    public static function readOnly(): self
    {
        return new self(readOnly: true);
    }

    public static function readWrite(): self
    {
        return new self(readOnly: false);
    }

    public static function defaults(): self
    {
        return new self();
    }

    public function setReadOnly(bool $readOnly): self
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    public function getReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function setEnableMmap(bool $enableMmap): self
    {
        $this->enableMmap = $enableMmap;
        return $this;
    }

    public function getEnableMmap(): bool
    {
        return $this->enableMmap;
    }

    public function setMaxBufferSize(int $maxBufferSize): self
    {
        $this->maxBufferSize = $maxBufferSize;
        return $this;
    }

    public function getMaxBufferSize(): int
    {
        return $this->maxBufferSize;
    }
}
