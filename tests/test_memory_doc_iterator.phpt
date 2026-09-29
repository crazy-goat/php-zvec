--TEST--
Memory leak: document iteration does not leak C handles or the C string array
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/mem_iter_' . uniqid();
$THRESHOLD = 500 * 1024;

/** VmRSS in kB, or 0 when unavailable. */
function getVmRSS(): int
{
    $status = @file_get_contents('/proc/self/status');
    if ($status === false) {
        return 0;
    }
    return preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $m) ? (int)$m[1] : 0;
}

try {
    $schema = new ZVecSchema('mem_iter');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addString('name', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    for ($i = 0; $i < 20; $i++) {
        $doc = new ZVecDoc("doc{$i}");
        $doc->setInt64('id', $i)
            ->setString('name', "name_$i")
            ->setVectorFp32('embedding', [(float)$i, 1.0, 0.0, 0.0]);
        $c->insert($doc);
    }
    $c->flush();

    // Warm up so one-off allocations are not counted as a leak.
    for ($i = 0; $i < 5; $i++) {
        foreach ($c->iterDocs() as $doc) {
            // no-op
        }
    }

    $startHeap = memory_get_usage();
    $startRss = getVmRSS();

    // Early close: the handle must be released even though iteration stops.
    for ($i = 0; $i < 50; $i++) {
        $it = $c->iterDocs();
        $it->rewind();
        $it->next();
        $it->next();
        $it->close();
    }

    // Full scan, auto-closed on exhaustion.
    for ($i = 0; $i < 50; $i++) {
        foreach ($c->iterDocs() as $doc) {
            // no-op
        }
    }

    // Exercises the output-fields path, where toCStringArray() allocates both
    // the char*[N] array and each string; iterDocs() frees the array itself.
    for ($i = 0; $i < 20; $i++) {
        foreach ($c->iterDocs(outputFields: ['id']) as $doc) {
            // no-op
        }
    }

    $heapDelta = memory_get_usage() - $startHeap;
    $rssDelta = getVmRSS() - $startRss;

    printf("heap delta: %d bytes\n", $heapDelta);
    printf("VmRSS delta: %d kB\n", $rssDelta);

    $ok = $heapDelta < $THRESHOLD;
    if ($startRss > 0) {
        $ok = $ok && $rssDelta < $THRESHOLD;
    }
    echo $ok ? "PASS: memory delta within threshold\n" : "FAIL: memory delta above threshold\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECTF--
heap delta: %d bytes
VmRSS delta: %d kB
PASS: memory delta within threshold
