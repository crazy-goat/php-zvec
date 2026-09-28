--TEST--
Docs consistency: no conflict markers, no duplicate API entries, new v0.6.0 API documented
--SKIPIF--
<?php
$root = __DIR__ . '/..';
foreach (['README.md', 'CHANGELOG.md', 'MIGRATION.md', 'AGENTS.md'] as $f) {
    if (!is_readable("$root/$f")) die("skip missing $f");
}
?>
--FILE--
<?php
$root = __DIR__ . '/..';
$failures = 0;

// str_pad() is locale- and version-sensitive for this repo's purposes, so pad
// explicitly: the expected output below is plain ASCII alignment.
function pad(string $s, int $n): string
{
    return $s . str_repeat(' ', max(1, $n - strlen($s)));
}

// 1. No unresolved merge conflict markers anywhere in the tracked docs.
$markers = 0;
foreach (['README.md', 'CHANGELOG.md', 'MIGRATION.md', 'AGENTS.md'] as $f) {
    $lines = file("$root/$f", FILE_IGNORE_NEW_LINES);
    foreach ($lines as $i => $line) {
        if (preg_match('/^(<<<<<<<|>>>>>>>)( |$)/', $line)) {
            echo "CONFLICT MARKER $f:" . ($i + 1) . ": $line\n";
            $markers++;
            $failures++;
        }
    }
}
echo "conflict markers: " . ($markers === 0 ? "none" : "$markers found") . "\n";

// 2. Every milestone v0.6.0 public API entry is documented in README exactly once
//    in its signature block (the block that lists `->method(...)` shapes).
$readme = file_get_contents("$root/README.md");
// Count only real signature lines -- a name mentioned in a trailing comment
// (e.g. "needs setIncludeDocId(true)") is a cross-reference, not an entry.
$sigBlocks = [];
preg_match_all('/```php\n(.*?)```/s', $readme, $m);
foreach ($m[1] as $block) {
    $lines = [];
    foreach (explode("\n", $block) as $line) {
        $code = preg_replace('#//.*$#', '', $line);
        if (str_contains($code, '->set') || str_contains($code, '::for')) {
            $lines[] = $code;
        }
    }
    if ($lines !== []) {
        $sigBlocks[] = implode("\n", $lines);
    }
}
$api = [
    'ZVecIndexParams::forDiskAnn',
    'ZVecIndexParams::forFts',
    'setDiskAnnParams',
    'setFts',
    'setHnswPrefetch',
    'setIncludeDocId',
];
foreach ($api as $name) {
    $n = 0;
    foreach ($sigBlocks as $b) {
        $n += substr_count($b, $name);
    }
    echo pad($name, 29) . " $n\n";
    if ($n !== 1) {
        $failures++;
    }
}

// 3. MIGRATION.md carries a v0.5.x -> v0.6.0 section that states BC status
$mig = file_get_contents("$root/MIGRATION.md");
echo "migration section: " . (str_contains($mig, 'v0.5.x → v0.6.0') ? "present" : "MISSING") . "\n";
if (!str_contains($mig, 'v0.5.x → v0.6.0')) {
    $failures++;
}
echo "states BC: " . (str_contains($mig, 'backward') ? "yes" : "NO") . "\n";
if (!str_contains($mig, 'backward')) {
    $failures++;
}

// 4. The zvec version pin is v0.6.0 everywhere it is declared.
foreach (['build_zvec.sh', 'build_zvec_lib.sh', 'docker/build-zvec.sh'] as $f) {
    $c = file_get_contents("$root/$f");
    $ok = str_contains($c, 'v0.6.0');
    echo pad($f, 22) . ' ' . ($ok ? 'v0.6.0' : 'WRONG') . "\n";
    if (!$ok) {
        $failures++;
    }
}

// 5. Index type table lists every INDEX_TYPE_ constant the class defines.
$zvec = file_get_contents("$root/src/ZVec.php");
preg_match_all('/const (INDEX_TYPE_[A-Z_]+) = (\d+);/', $zvec, $consts, PREG_SET_ORDER);
foreach ($consts as [, $name, $value]) {
    $inTable = str_contains($readme, "`$name`");
    echo pad($name, 25) . " =$value " . ($inTable ? 'documented' : 'MISSING FROM README') . "\n";
    if (!$inTable) {
        $failures++;
    }
}

echo $failures === 0 ? "PASS\n" : "FAIL ($failures)\n";
?>
--EXPECT--
conflict markers: none
ZVecIndexParams::forDiskAnn   1
ZVecIndexParams::forFts       1
setDiskAnnParams              1
setFts                        1
setHnswPrefetch               1
setIncludeDocId               1
migration section: present
states BC: yes
build_zvec.sh          v0.6.0
build_zvec_lib.sh      v0.6.0
docker/build-zvec.sh   v0.6.0
INDEX_TYPE_HNSW           =1 documented
INDEX_TYPE_IVF            =2 documented
INDEX_TYPE_FLAT           =3 documented
INDEX_TYPE_HNSW_RABITQ    =4 documented
INDEX_TYPE_VAMANA         =5 documented
INDEX_TYPE_DISKANN        =6 documented
INDEX_TYPE_INVERT         =10 documented
INDEX_TYPE_FTS            =11 documented
PASS
