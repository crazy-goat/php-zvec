<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Common interface for vector query objects.
 *
 * Implemented by ZVecVectorQuery and ZVecGroupByVectorQuery.
 * Provides getHandle() for FFI access and free() for resource cleanup.
 *
 * @see ZVecVectorQuery
 * @see ZVecGroupByVectorQuery
 */
interface ZVecQueryInterface
{
    public function getHandle(): FFI\CData;

    public function free(): void;
}
