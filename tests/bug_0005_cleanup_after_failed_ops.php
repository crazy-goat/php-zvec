<?php
/**
 * Bug reproduction: Cleanup safety after failed operations
 * 
 * Expected: Collection can be safely closed/destroyed after failed operations
 * Actual: (To be determined - checking if issues exist)
 * 
 * Status: Investigation in progress
 * Location: php/ZVec.php - cleanup logic
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/bug_0005_' . uniqid();
$success = true;

try {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
    
    // Test 1: Failed insert followed by close
    echo "Test 1: Failed insert -> close\n";
    $schema = new ZVecSchema('test1');
    $schema->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4);
    $c1 = ZVec::create($path . '_1', $schema);
    
    try {
        // Insert doc without required field to trigger error
        $doc = new ZVecDoc('d1');
        $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
        // Missing required 'id' field - should fail
        $c1->insert($doc);
        echo "  UNEXPECTED: Insert should have failed\n";
    } catch (ZVecException $e) {
        echo "  Expected error caught: " . substr($e->getMessage(), 0, 50) . "...\n";
    }
    
    // Try to close after failure
    try {
        $c1->close();
        echo "  PASS: Close succeeded after failed insert\n";
    } catch (Throwable $e) {
        echo "  FAIL: Close failed after failed insert: " . $e->getMessage() . "\n";
        $success = false;
    }
    exec("rm -rf " . escapeshellarg($path . '_1'));
    
    // Test 2: Failed insert followed by destroy
    echo "Test 2: Failed insert -> destroy\n";
    $schema2 = new ZVecSchema('test2');
    $schema2->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4);
    $c2 = ZVec::create($path . '_2', $schema2);
    
    try {
        $doc = new ZVecDoc('d1');
        $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
        $c2->insert($doc); // Should fail
    } catch (ZVecException $e) {
        // Expected
    }
    
    try {
        $c2->destroy();
        echo "  PASS: Destroy succeeded after failed insert\n";
    } catch (Throwable $e) {
        echo "  FAIL: Destroy failed after failed insert: " . $e->getMessage() . "\n";
        $success = false;
    }
    // No cleanup needed - destroy removes directory
    
    // Test 3: Failed DDL followed by operations
    echo "Test 3: Failed insert -> DDL -> close\n";
    $schema3 = new ZVecSchema('test3');
    $schema3->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4);
    $c3 = ZVec::create($path . '_3', $schema3);
    
    // Insert some valid data
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c3->insert($doc);
    
    // Now try invalid insert
    try {
        $badDoc = new ZVecDoc('d2');
        $badDoc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
        $c3->insert($badDoc); // Should fail
    } catch (ZVecException $e) {
        // Expected
    }
    
    // Try DDL after failure
    try {
        $c3->addColumnFloat('extra');
        echo "  DDL after failed insert: OK\n";
    } catch (Throwable $e) {
        echo "  DDL failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $c3->close();
        echo "  PASS: Close succeeded after failed insert + DDL\n";
    } catch (Throwable $e) {
        echo "  FAIL: Close failed: " . $e->getMessage() . "\n";
        $success = false;
    }
    exec("rm -rf " . escapeshellarg($path . '_3'));
    
    // Test 4: Multiple collections with mixed failures
    echo "Test 4: Multiple collections with mixed failures\n";
    $collections = [];
    for ($i = 0; $i < 3; $i++) {
        $schema = new ZVecSchema("multi_$i");
        $schema->addInt64('id', nullable: false)
            ->addVectorFp32('v', dimension: 4);
        $c = ZVec::create($path . "_multi_$i", $schema);
        
        if ($i === 1) {
            // Make this one fail
            try {
                $badDoc = new ZVecDoc('d1');
                $badDoc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
                $c->insert($badDoc);
            } catch (ZVecException $e) {
                // Expected
            }
        } else {
            // Valid insert
            $doc = new ZVecDoc('d1');
            $doc->setInt64('id', $i)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
            $c->insert($doc);
        }
        $collections[] = $c;
    }
    
    // Close all
    foreach ($collections as $idx => $c) {
        try {
            $c->close();
            echo "  Collection $idx: close OK\n";
        } catch (Throwable $e) {
            echo "  Collection $idx: close FAIL - " . $e->getMessage() . "\n";
            $success = false;
        }
    }
    exec("rm -rf " . escapeshellarg($path . '_multi_*'));
    
    if ($success) {
        echo "\nPASS: bug_0005 - All cleanup scenarios work correctly\n";
        exit(0);
    } else {
        echo "\nFAIL: bug_0005 - Some cleanup scenarios failed\n";
        exit(1);
    }
    
} catch (Throwable $e) {
    echo "FAIL: Unexpected error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    // Cleanup
    exec("rm -rf " . escapeshellarg($path . '_*'));
    exit(1);
}
?>