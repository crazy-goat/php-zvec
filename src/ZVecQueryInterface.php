<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

interface ZVecQueryInterface
{
    public function getHandle(): FFI\CData;

    public function free(): void;
}
