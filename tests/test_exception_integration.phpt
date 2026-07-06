--TEST--
ZVecException: integration test with real FFI errors (round-trip through FFI)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip Error details are FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$logDir = __DIR__ . '/../test_dbs/exception_integration_log_' . uniqid();
@mkdir($logDir, 0777, true);
ZVec::init(logType: ZVec::LOG_FILE, logDir: $logDir, logLevel: ZVec::LOG_ERROR, verboseErrors: true);

$path = __DIR__ . '/../test_dbs/exception_integration_' . uniqid();
try {
    // Test 1: Trigger INVALID_ARGUMENT (code 3) by opening non-existent path
    try {
        $c = ZVec::open('/tmp/nonexistent_' . uniqid());
        echo "FAIL: Should have thrown exception\n";
        exit(1);
    } catch (ZVecException $e) {
        printf("PASS: code=%d (%s)\n", $e->getCode(), $e->getErrorCodeString());
        $file = $e->getErrorFile();
        $line = $e->getErrorLine();
        $func = $e->getErrorFunction();
        if ($file !== null && $line !== null && $func !== null) {
            printf("PASS: details present (file=%s, line=%d, func=%s)\n", $file, $line, $func);
        } else {
            echo "FAIL: error details should be present with verboseErrors=true\n";
            exit(1);
        }
    }

    // Test 2: Trigger ALREADY_EXISTS (code 2) by duplicate PK insert
    $schema = new ZVecSchema('exception_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $doc = new ZVecDoc('duplicate_test');
    $doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);

    try {
        $c->insert($doc); // Same PK → ALREADY_EXISTS
        echo "FAIL: Should have thrown exception on duplicate insert\n";
        exit(1);
    } catch (ZVecException $e) {
        printf("PASS: Duplicate PK code=%d (%s)\n", $e->getCode(), $e->getErrorCodeString());
        if ($e->getErrorFile() !== null) {
            printf("PASS: error file: %s\n", $e->getErrorFile());
        } else {
            echo "FAIL: error file should be present\n";
            exit(1);
        }
    }

    // Test 3: Trigger INVALID_ARGUMENT (code 3) with invalid filter
    $c->optimize();
    try {
        $c->query('v', [0.1, 0.2, 0.3, 0.4], filter: 'bad!!filter!!');
        echo "FAIL: Should have thrown exception on invalid filter\n";
        exit(1);
    } catch (ZVecException $e) {
        printf("PASS: Invalid filter code=%d (%s)\n", $e->getCode(), $e->getErrorCodeString());
        if ($e->getErrorFile() !== null) {
            printf("PASS: error file: %s\n", $e->getErrorFile());
        }
    }
    $c->close();

    // Test 4: Exception chaining with error details preserved
    try {
        $inner = new ZVecException('FFI call failed', 3, errorFile: 'zvec_ffi.cc', errorLine: 256, errorFunction: 'zvec_collection_open');
        throw new ZVecException(
            'Operation failed',
            8,
            previous: $inner,
            errorFile: $inner->getErrorFile(),
            errorLine: $inner->getErrorLine(),
            errorFunction: $inner->getErrorFunction(),
        );
    } catch (ZVecException $outer) {
        printf("PASS: Chained exception outer=%d inner=%d\n", $outer->getCode(), $outer->getPrevious()->getCode());
        if ($outer->getErrorFile() !== null) {
            printf("PASS: Outer error file: %s\n", $outer->getErrorFile());
        }
    }

    echo "All integration tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path) . " " . escapeshellarg($logDir));
}
?>
--EXPECTF--
PASS: code=%d (%s)
PASS: details present (file=%s, line=%d, func=%s)
PASS: Duplicate PK code=%d (%s)
PASS: error file: %s
PASS: Invalid filter code=%d (%s)
PASS: error file: %s
PASS: Chained exception outer=%d inner=%d
PASS: Outer error file: %s
All integration tests passed
