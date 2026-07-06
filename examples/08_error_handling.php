<?php

declare(strict_types=1);

/**
 * Example 08: Error Handling with ZVecException
 *
 * Demonstrates proper error handling patterns:
 * - Catching ZVecException from FFI operations
 * - Using getErrorCodeString() for human-readable error codes
 * - Accessing error details (file, line, function) with verbose errors
 * - Exception chaining
 * - try-finally cleanup pattern
 */

require_once __DIR__ . '/../src/ZVec.php';

// Enable verbose errors to get file/line/function in exceptions
ZVec::init(
    logType: ZVec::LOG_CONSOLE,
    logLevel: ZVec::LOG_WARN,
    verboseErrors: true,
);

echo "=== ZVecException Error Handling Examples ===\n\n";

// 1. Basic exception catching
echo "1. Basic exception catching:\n";
try {
    // Opening non-existent path triggers INVALID_ARGUMENT
    $c = ZVec::open('/tmp/nonexistent_' . uniqid());
} catch (ZVecException $e) {
    printf(
        "   Caught ZVecException: code=%d (%s), message=%s\n",
        $e->getCode(),
        $e->getErrorCodeString(),
        $e->getMessage(),
    );
}

echo "\n2. Error details (file, line, function):\n";
try {
    $c = ZVec::open('/tmp/nonexistent_' . uniqid());
} catch (ZVecException $e) {
    printf(
        "   File: %s\n   Line: %d\n   Function: %s\n",
        $e->getErrorFile() ?? 'N/A',
        $e->getErrorLine() ?? 0,
        $e->getErrorFunction() ?? 'N/A',
    );
}

echo "\n3. All error code strings:\n";
foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 99] as $code) {
    $e = new ZVecException('test', $code);
    printf("   Code %-2d → %s\n", $code, $e->getErrorCodeString());
}

echo "\n4. Exception chaining:\n";
try {
    try {
        // Inner error
        throw new ZVecException('FFI call failed: zvec_collection_open', 3);
    } catch (ZVecException $inner) {
        // Wrap with more context
        throw new ZVecException(
            'Failed to open collection',
            8,
            previous: $inner,
            errorFile: $inner->getErrorFile(),
            errorLine: $inner->getErrorLine(),
            errorFunction: $inner->getErrorFunction(),
        );
    }
} catch (ZVecException $outer) {
    printf(
        "   Outer: code=%d (%s), message=%s\n",
        $outer->getCode(),
        $outer->getErrorCodeString(),
        $outer->getMessage(),
    );
    if ($outer->getPrevious() !== null) {
        /** @var ZVecException $prev */
        $prev = $outer->getPrevious();
        printf(
            "   Inner: code=%d (%s), message=%s\n",
            $prev->getCode(),
            $prev->getErrorCodeString(),
            $prev->getMessage(),
        );
    }
}

echo "\n5. try-finally cleanup pattern with error tracking:\n";
$path = __DIR__ . '/../test_dbs/example_08_' . uniqid();
try {
    $schema = new ZVecSchema('error_demo');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4);

    $c = ZVec::create($path, $schema);
    echo "   Collection created successfully\n";

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    echo "   Document inserted\n";

    $c->close();
    echo "   Collection closed\n";
} catch (ZVecException $e) {
    printf(
        "   ERROR: code=%d (%s) — %s\n",
        $e->getCode(),
        $e->getErrorCodeString(),
        $e->getMessage(),
    );
} finally {
    exec("rm -rf " . escapeshellarg($path));
    echo "   Cleanup completed\n";
}

echo "\nAll examples completed.\n";
