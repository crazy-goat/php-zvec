<?php
/**
 * Bug reproduction: RocksDB lock handling after failures
 * 
 * Expected: No locks remain after proper cleanup
 * Actual: (To be determined - checking if lock issues exist)
 * 
 * Status: Investigation in progress
 * Location: FFI layer - RocksDB lock management
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/bug_0006_' . uniqid();
$success = true;

try {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
    
    // Test 1: Create, fail, destroy, recreate - should not have lock issues
    echo "Test 1: Create -> fail -> destroy -> recreate\n";
    
    $schema1 = new ZVecSchema('test1');
    $schema1->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4);
    $c1 = ZVec::create($path . '_1', $schema1);
    
    // Insert valid data
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c1->insert($doc);
    $c1->optimize();
    
    // Destroy and immediately recreate
    $c1->destroy();
    
    // Small delay to let OS release locks
    usleep(100000); // 100ms
    
    try {
        $c1_new = ZVec::create($path . '_1', $schema1);
        echo "  PASS: Recreated collection after destroy\n";
        
        // Verify it works
        $doc2 = new ZVecDoc('d2');
        $doc2->setInt64('id', 2)->setVectorFp32('v', [0.4, 0.3, 0.2, 0.1]);
        $c1_new->insert($doc2);
        $c1_new->optimize();
        
        $fetched = $c1_new->fetch('d2');
        if (count($fetched) === 1) {
            echo "  PASS: New collection works correctly\n";
        } else {
            echo "  FAIL: New collection doesn't work\n";
            $success = false;
        }
        $c1_new->destroy();
    } catch (Throwable $e) {
        echo "  FAIL: Cannot recreate after destroy: " . $e->getMessage() . "\n";
        $success = false;
    }
    
    // Test 2: Multiple rapid create/fail/destroy cycles
    echo "Test 2: Rapid create/fail/destroy cycles\n";
    
    for ($i = 0; $i < 5; $i++) {
        $schema = new ZVecSchema("rapid_$i");
        $schema->addInt64('id', nullable: false)
            ->addVectorFp32('v', dimension: 4);
        
        try {
            $c = ZVec::create($path . "_rapid_$i", $schema);
            
            // Mix of operations - some might fail
            if ($i % 2 === 0) {
                // Valid insert
                $doc = new ZVecDoc("d$i");
                $doc->setInt64('id', $i)->setVectorFp32('v', [0.1 * $i, 0.2, 0.3, 0.4]);
                $c->insert($doc);
            } else {
                // Try to trigger error
                try {
                    $badDoc = new ZVecDoc("d$i");
                    $badDoc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
                    $c->insert($badDoc); // Should fail - missing id
                } catch (ZVecException $e) {
                    // Expected
                }
            }
            
            $c->destroy();
            echo "  Cycle $i: OK\n";
        } catch (Throwable $e) {
            echo "  Cycle $i: FAIL - " . $e->getMessage() . "\n";
            $success = false;
        }
    }
    
    // Test 3: Close without destroy, then reopen
    echo "Test 3: Close -> reopen (no destroy)\n";
    
    $schema3 = new ZVecSchema('test3');
    $schema3->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4);
    $c3 = ZVec::create($path . '_3', $schema3);
    
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c3->insert($doc);
    $c3->optimize();
    $c3->close();
    
    // Small delay
    usleep(100000);
    
    try {
        $c3_reopened = ZVec::open($path . '_3');
        $fetched = $c3_reopened->fetch('d1');
        if (count($fetched) === 1) {
            echo "  PASS: Reopened collection has data\n";
        } else {
            echo "  FAIL: Reopened collection missing data\n";
            $success = false;
        }
        $c3_reopened->destroy();
    } catch (Throwable $e) {
        echo "  FAIL: Cannot reopen: " . $e->getMessage() . "\n";
        $success = false;
    }
    
    // Cleanup
    exec("rm -rf " . escapeshellarg($path . '_*'));
    
    if ($success) {
        echo "\nPASS: bug_0006 - RocksDB lock handling works correctly\n";
        exit(0);
    } else {
        echo "\nFAIL: bug_0006 - Some lock scenarios failed\n";
        exit(1);
    }
    
} catch (Throwable $e) {
    echo "FAIL: Unexpected error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exec("rm -rf " . escapeshellarg($path . '_*'));
    exit(1);
}
?>