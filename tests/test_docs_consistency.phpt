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
// 1b. Every *release* must have a CHANGELOG section. Tags are a much wider net
//     than releases here (40 of them, from 0.1.0 up, many never published and
//     several with no section at all), so scope this to what GitHub actually
//     publishes. 0.4.9, 0.3.9 and 0.1.1 are tags without releases, which is
//     why they are legitimately absent from the changelog.
$changelog = file_get_contents("$root/CHANGELOG.md");
preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $sections);
$documented = $sections[1];

$published = [];
$out = (string) @shell_exec(
    'gh release list --repo crazy-goat/php-zvec --limit 100 --json tagName 2>/dev/null'
);
if (trim($out) === '') {
    $json = @file_get_contents('https://api.github.com/repos/crazy-goat/php-zvec/releases?per_page=100');
    $decoded = $json === false ? [] : json_decode($json, true);
    foreach (is_array($decoded) as $r) {
        $published[] = $r['tag_name'];
    }
} else {
    foreach (json_decode($out, true) ?: [] as $r) {
        $published[] = $r['tagName'];
    }
}
$product = array_values(array_filter($published, fn($t) => (bool) preg_match('/^v\d+\.\d+\.\d+$/', $t)));
sort($product);
$missing = [];
foreach ($product as $t) {
    if (!in_array(ltrim($t, 'v'), $documented, true)) {
        $missing[] = $t;
    }
}
echo 'published release without a CHANGELOG section: ' . ($missing === [] ? 'none' : implode(',', $missing)) . "\n";
if ($missing !== []) {
    $failures++;
}

// The [Unreleased] compare link must point at the newest published release,
// otherwise every link in the file is off by one version.
$newest = $product === [] ? '' : 'v' . ltrim((string) end($product), 'v');
if ($newest !== '' && str_contains($changelog, "compare/$newest...HEAD")) {
    echo "unreleased link: points at $newest\n";
} else {
    echo "unreleased link: STALE (expected $newest)\n";
    $failures++;
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
published release without a CHANGELOG section: none
unreleased link: points at v0.6.0
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
