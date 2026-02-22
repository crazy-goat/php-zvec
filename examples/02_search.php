<?php

declare(strict_types=1);

/**
 * Example 2: Vector Search
 * 
 * Demonstrates:
 * - Finding similar documents (kNN)
 * - Search with filter (vector + scalar)
 * - Filter-only search (no vector)
 * - Limiting returned fields
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/example_02_' . uniqid();

try {
    echo "=== Example 2: Vector Search ===\n\n";

    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

    // Schema with categories
    $schema = new ZVecSchema('shop');
    $schema
        ->addString('name', nullable: false)
        ->addString('category', nullable: false)
        ->addFloat('price', nullable: false)
        ->addVectorFp32('embedding', dimension: 3, metricType: ZVecSchema::METRIC_COSINE);

    $collection = ZVec::create($path, $schema);

    // Add products from different categories
    $products = [
        ['Laptop Dell', 'Electronics', 3999, [0.9, 0.1, 0.1]],
        ['iPad Pro', 'Electronics', 4999, [0.8, 0.2, 0.1]],
        ['iPhone 15', 'Electronics', 5499, [0.85, 0.15, 0.1]],
        ['Office Chair', 'Furniture', 599, [0.1, 0.9, 0.1]],
        ['Gaming Desk', 'Furniture', 1299, [0.15, 0.85, 0.1]],
        ['Desk Lamp', 'Furniture', 199, [0.2, 0.8, 0.2]],
        ['Coffee Beans', 'Food', 49, [0.1, 0.1, 0.9]],
        ['Green Tea', 'Food', 29, [0.15, 0.1, 0.85]],
    ];

    foreach ($products as $i => $p) {
        $doc = new ZVecDoc('item_' . ($i + 1));
        $doc->setString('name', $p[0])
            ->setString('category', $p[1])
            ->setFloat('price', $p[2])
            ->setVectorFp32('embedding', $p[3]);
        $collection->insert($doc);
    }
    $collection->optimize();
    echo "[1] Added " . count($products) . " products\n\n";

    // 1. Basic vector search
    echo "[2] Search for products similar to 'electronics':\n";
    $results = $collection->query('embedding', [0.9, 0.1, 0.1], topk: 3);
    foreach ($results as $doc) {
        $score = round($doc->getScore(), 3);
        echo "    - {$doc->getString('name')} (similarity: {$score})\n";
    }
    echo "\n";

    // 2. Search with price filter
    echo "[3] Electronics over $4000:\n";
    $results = $collection->query('embedding', [0.85, 0.15, 0.1], topk: 10, filter: 'price > 4000');
    foreach ($results as $doc) {
        echo "    - {$doc->getString('name')}: {$doc->getFloat('price')} USD\n";
    }
    echo "\n";

    // 3. Filter-only search (no vector)
    echo "[4] All products in 'Furniture' category:\n";
    $results = $collection->queryByFilter("category = 'Furniture'", topk: 10);
    foreach ($results as $doc) {
        echo "    - {$doc->getString('name')}: {$doc->getFloat('price')} USD\n";
    }
    echo "\n";

    // 4. Search with field selection
    echo "[5] Search with limited fields (name only):\n";
    $results = $collection->query('embedding', [0.1, 0.1, 0.9], topk: 2, outputFields: ['name']);
    foreach ($results as $doc) {
        $name = $doc->getString('name');
        $price = $doc->getFloat('price'); // will be null as not fetched
        echo "    - name: {$name}, price: " . ($price ?? 'not fetched') . "\n";
    }

    $collection->destroy();
    echo "\n✓ Success!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
