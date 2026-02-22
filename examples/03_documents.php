<?php

declare(strict_types=1);

/**
 * Example 3: Document Management
 * 
 * Demonstrates:
 * - Document update (update)
 * - Upsert (update or insert)
 * - Deleting single documents
 * - Delete by filter
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/example_03_' . uniqid();

try {
    echo "=== Example 3: Document Management ===\n\n";

    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

    $schema = new ZVecSchema('inventory');
    $schema
        ->addString('product', nullable: false)
        ->addInt64('quantity', nullable: false)
        ->addFloat('price', nullable: false)
        ->addVectorFp32('features', dimension: 2, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    // Insert initial data
    $data = [
        ['Laptop', 10, 3999, [1.0, 0.0]],
        ['Tablet', 5, 2999, [0.8, 0.2]],
        ['Phone', 20, 2499, [0.9, 0.1]],
        ['Mouse', 50, 199, [0.2, 0.8]],
        ['Keyboard', 30, 499, [0.3, 0.7]],
    ];

    foreach ($data as $i => $d) {
        $doc = new ZVecDoc('item_' . ($i + 1));
        $doc->setString('product', $d[0])
            ->setInt64('quantity', $d[1])
            ->setFloat('price', $d[2])
            ->setVectorFp32('features', $d[3]);
        $collection->insert($doc);
    }
    $collection->optimize();
    echo "[1] Added 5 products\n";

    // Show initial state
    $docs = $collection->fetch('item_1', 'item_2');
    echo "    Initial state:\n";
    foreach ($docs as $doc) {
        echo "    - {$doc->getString('product')}: qty={$doc->getInt64('quantity')}, price={$doc->getFloat('price')}\n";
    }
    echo "\n";

    // 1. Update - partial update
    echo "[2] Update (change Laptop price):\n";
    $update = new ZVecDoc('item_1');
    $update->setFloat('price', 3499.99); // only price, rest unchanged
    $collection->update($update);
    
    $doc = $collection->fetch('item_1')[0];
    echo "    New price: {$doc->getFloat('price')} USD\n\n";

    // 2. Upsert - update existing
    echo "[3] Upsert existing (update Tablet):\n";
    $upsert1 = new ZVecDoc('item_2');
    $upsert1->setString('product', 'Tablet Pro')
             ->setInt64('quantity', 3)
             ->setFloat('price', 4999.99)
             ->setVectorFp32('features', [0.8, 0.2]);
    $collection->upsert($upsert1);
    
    $doc = $collection->fetch('item_2')[0];
    echo "    Updated: {$doc->getString('product')}, qty={$doc->getInt64('quantity')}\n\n";

    // Upsert - new document
    echo "[4] Upsert new (add Monitor):\n";
    $upsert2 = new ZVecDoc('item_6');
    $upsert2->setString('product', '4K Monitor')
             ->setInt64('quantity', 8)
             ->setFloat('price', 1999.99)
             ->setVectorFp32('features', [0.5, 0.5]);
    $collection->upsert($upsert2);
    $collection->optimize();
    
    echo "    Documents after upsert: " . count($collection->queryByFilter('quantity > 0', topk: 100)) . "\n\n";

    // 3. Single delete
    echo "[5] Delete single document:\n";
    $collection->delete('item_3'); // delete Phone
    $remaining = $collection->queryByFilter('quantity >= 0', topk: 100);
    echo "    Remaining documents: " . count($remaining) . "\n\n";

    // 4. Delete by filter
    echo "[6] Delete by filter (price < 500):\n";
    $collection->deleteByFilter('price < 500'); // will delete Mouse and Keyboard
    $collection->optimize();
    
    $remaining = $collection->queryByFilter('quantity >= 0', topk: 100);
    echo "    Remaining documents: " . count($remaining) . "\n";
    foreach ($remaining as $doc) {
        echo "    - {$doc->getString('product')}: {$doc->getFloat('price')} USD\n";
    }

    $collection->destroy();
    echo "\n✓ Success!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
