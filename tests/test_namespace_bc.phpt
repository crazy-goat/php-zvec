--TEST--
Namespace: PSR-4 namespace with backward-compatible global aliases
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

echo "--- Global namespace (BC aliases) ---\n";
echo class_exists('ZVec') ? "ZVec: OK\n" : "ZVec: MISSING\n";
echo class_exists('ZVecException') ? "ZVecException: OK\n" : "ZVecException: MISSING\n";
echo class_exists('ZVecSchema') ? "ZVecSchema: OK\n" : "ZVecSchema: MISSING\n";
echo class_exists('ZVecDoc') ? "ZVecDoc: OK\n" : "ZVecDoc: MISSING\n";
echo class_exists('ZVecCollectionOptions') ? "ZVecCollectionOptions: OK\n" : "ZVecCollectionOptions: MISSING\n";
echo class_exists('ZVecCollectionStats') ? "ZVecCollectionStats: OK\n" : "ZVecCollectionStats: MISSING\n";
echo class_exists('ZVecFieldSchema') ? "ZVecFieldSchema: OK\n" : "ZVecFieldSchema: MISSING\n";
echo class_exists('ZVecIndexParams') ? "ZVecIndexParams: OK\n" : "ZVecIndexParams: MISSING\n";
echo class_exists('ZVecQueryInterface') || interface_exists('ZVecQueryInterface') ? "ZVecQueryInterface: OK\n" : "ZVecQueryInterface: MISSING\n";
echo class_exists('ZVecVectorQuery') ? "ZVecVectorQuery: OK\n" : "ZVecVectorQuery: MISSING\n";
echo class_exists('ZVecGroupByVectorQuery') ? "ZVecGroupByVectorQuery: OK\n" : "ZVecGroupByVectorQuery: MISSING\n";
echo class_exists('ZVecReRanker') || interface_exists('ZVecReRanker') ? "ZVecReRanker: OK\n" : "ZVecReRanker: MISSING\n";
echo class_exists('ZVecRerankedDoc') ? "ZVecRerankedDoc: OK\n" : "ZVecRerankedDoc: MISSING\n";
echo class_exists('ZVecRrfReRanker') ? "ZVecRrfReRanker: OK\n" : "ZVecRrfReRanker: MISSING\n";
echo class_exists('ZVecWeightedReRanker') ? "ZVecWeightedReRanker: OK\n" : "ZVecWeightedReRanker: MISSING\n";

echo "\n--- PSR-4 namespace ---\n";
echo class_exists('CrazyGoat\ZVec\ZVec') ? "CrazyGoat\\ZVec\\ZVec: OK\n" : "CrazyGoat\\ZVec\\ZVec: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecException') ? "CrazyGoat\\ZVec\\ZVecException: OK\n" : "CrazyGoat\\ZVec\\ZVecException: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecSchema') ? "CrazyGoat\\ZVec\\ZVecSchema: OK\n" : "CrazyGoat\\ZVec\\ZVecSchema: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecDoc') ? "CrazyGoat\\ZVec\\ZVecDoc: OK\n" : "CrazyGoat\\ZVec\\ZVecDoc: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecCollectionOptions') ? "CrazyGoat\\ZVec\\ZVecCollectionOptions: OK\n" : "CrazyGoat\\ZVec\\ZVecCollectionOptions: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecCollectionStats') ? "CrazyGoat\\ZVec\\ZVecCollectionStats: OK\n" : "CrazyGoat\\ZVec\\ZVecCollectionStats: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecFieldSchema') ? "CrazyGoat\\ZVec\\ZVecFieldSchema: OK\n" : "CrazyGoat\\ZVec\\ZVecFieldSchema: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecIndexParams') ? "CrazyGoat\\ZVec\\ZVecIndexParams: OK\n" : "CrazyGoat\\ZVec\\ZVecIndexParams: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecQueryInterface') || interface_exists('CrazyGoat\ZVec\ZVecQueryInterface') ? "CrazyGoat\\ZVec\\ZVecQueryInterface: OK\n" : "CrazyGoat\\ZVec\\ZVecQueryInterface: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecVectorQuery') ? "CrazyGoat\\ZVec\\ZVecVectorQuery: OK\n" : "CrazyGoat\\ZVec\\ZVecVectorQuery: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecGroupByVectorQuery') ? "CrazyGoat\\ZVec\\ZVecGroupByVectorQuery: OK\n" : "CrazyGoat\\ZVec\\ZVecGroupByVectorQuery: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecReRanker') || interface_exists('CrazyGoat\ZVec\ZVecReRanker') ? "CrazyGoat\\ZVec\\ZVecReRanker: OK\n" : "CrazyGoat\\ZVec\\ZVecReRanker: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecRerankedDoc') ? "CrazyGoat\\ZVec\\ZVecRerankedDoc: OK\n" : "CrazyGoat\\ZVec\\ZVecRerankedDoc: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecRrfReRanker') ? "CrazyGoat\\ZVec\\ZVecRrfReRanker: OK\n" : "CrazyGoat\\ZVec\\ZVecRrfReRanker: MISSING\n";
echo class_exists('CrazyGoat\ZVec\ZVecWeightedReRanker') ? "CrazyGoat\\ZVec\\ZVecWeightedReRanker: OK\n" : "CrazyGoat\\ZVec\\ZVecWeightedReRanker: MISSING\n";

echo "\n--- Alias instanceof check ---\n";
$schema = new ZVecSchema("test_schema");
echo ($schema instanceof ZVecSchema) ? "Global ZVecSchema instanceof: OK\n" : "Global ZVecSchema instanceof: FAIL\n";
echo ($schema instanceof CrazyGoat\ZVec\ZVecSchema) ? "Namespaced ZVecSchema instanceof: OK\n" : "Namespaced ZVecSchema instanceof: FAIL\n";

echo "\n--- Namespace usage with use statement ---\n";
// Simulate namespace-style usage
eval('
use CrazyGoat\ZVec\ZVec as ZVecNS;
use CrazyGoat\ZVec\ZVecSchema as ZVecSchemaNS;
use CrazyGoat\ZVec\ZVecDoc as ZVecDocNS;
echo ZVecNS::class === "CrazyGoat\\\ZVec\\\ZVec" ? "use ZVec: OK\n" : "use ZVec: FAIL\n";
echo ZVecSchemaNS::class === "CrazyGoat\\\ZVec\\\ZVecSchema" ? "use ZVecSchema: OK\n" : "use ZVecSchema: FAIL\n";
echo ZVecDocNS::class === "CrazyGoat\\\ZVec\\\ZVecDoc" ? "use ZVecDoc: OK\n" : "use ZVecDoc: FAIL\n";
');

echo "\nAll namespace tests passed!\n";
?>
--EXPECT--
--- Global namespace (BC aliases) ---
ZVec: OK
ZVecException: OK
ZVecSchema: OK
ZVecDoc: OK
ZVecCollectionOptions: OK
ZVecCollectionStats: OK
ZVecFieldSchema: OK
ZVecIndexParams: OK
ZVecQueryInterface: OK
ZVecVectorQuery: OK
ZVecGroupByVectorQuery: OK
ZVecReRanker: OK
ZVecRerankedDoc: OK
ZVecRrfReRanker: OK
ZVecWeightedReRanker: OK

--- PSR-4 namespace ---
CrazyGoat\ZVec\ZVec: OK
CrazyGoat\ZVec\ZVecException: OK
CrazyGoat\ZVec\ZVecSchema: OK
CrazyGoat\ZVec\ZVecDoc: OK
CrazyGoat\ZVec\ZVecCollectionOptions: OK
CrazyGoat\ZVec\ZVecCollectionStats: OK
CrazyGoat\ZVec\ZVecFieldSchema: OK
CrazyGoat\ZVec\ZVecIndexParams: OK
CrazyGoat\ZVec\ZVecQueryInterface: OK
CrazyGoat\ZVec\ZVecVectorQuery: OK
CrazyGoat\ZVec\ZVecGroupByVectorQuery: OK
CrazyGoat\ZVec\ZVecReRanker: OK
CrazyGoat\ZVec\ZVecRerankedDoc: OK
CrazyGoat\ZVec\ZVecRrfReRanker: OK
CrazyGoat\ZVec\ZVecWeightedReRanker: OK

--- Alias instanceof check ---
Global ZVecSchema instanceof: OK
Namespaced ZVecSchema instanceof: OK

--- Namespace usage with use statement ---
use ZVec: OK
use ZVecSchema: OK
use ZVecDoc: OK

All namespace tests passed!
