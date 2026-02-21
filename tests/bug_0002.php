<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_bug_0002_' . uniqid();

try {
    $schema = new ZVecSchema('bug0002');
    $schema->setMaxDocCountPerSegment(1000)
        ->addString('category', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $docs = [
        ['pk' => 'a1', 'cat' => 'electronics', 'name' => 'Phone',  'vec' => [0.1, 0.2, 0.3, 0.4]],
        ['pk' => 'a2', 'cat' => 'electronics', 'name' => 'Laptop', 'vec' => [0.2, 0.3, 0.4, 0.5]],
        ['pk' => 'b1', 'cat' => 'books',       'name' => 'Novel',  'vec' => [0.5, 0.5, 0.5, 0.5]],
        ['pk' => 'b2', 'cat' => 'books',       'name' => 'Manual', 'vec' => [0.6, 0.6, 0.6, 0.6]],
        ['pk' => 'c1', 'cat' => 'food',        'name' => 'Apple',  'vec' => [0.9, 0.1, 0.1, 0.1]],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d['pk']);
        $doc->setString('category', $d['cat'])
            ->setString('name', $d['name'])
            ->setVectorFp32('v', $d['vec']);
        $c->insert($doc);
    }
    $c->optimize();

    $groups = $c->groupByQuery(
        'v', [0.5, 0.5, 0.5, 0.5],
        groupByField: 'category',
        groupCount: 2,
        groupTopk: 3,
    );

    $ok = true;

    if (count($groups) < 2) {
        echo "FAIL: bug_0002 - expected multiple groups, got " . count($groups) . "\n";
        $ok = false;
    }

    foreach ($groups as $group) {
        if ($group['group_value'] === '') {
            echo "FAIL: bug_0002 - group_value is empty (grouping not working)\n";
            $ok = false;
            break;
        }
    }

    if ($ok) {
        echo "PASS: bug_0002 - GroupByQuery returns proper groups\n";
    }

    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
