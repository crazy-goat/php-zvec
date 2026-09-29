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
// 1b. Changelog section/link self-consistency. Deliberately hermetic: an
//     earlier revision of this test shelled out to `gh` and read the GitHub
//     API, which made it pass locally (authenticated) and fail in CI (not
//     authenticated, empty result read as "no releases"). Everything below
//     needs only the file itself.
$changelog = file_get_contents("$root/CHANGELOG.md");
preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $sections);
$documented = $sections[1];

preg_match_all('/^\[(\d+\.\d+\.\d+)\]: (\S+)$/m', $changelog, $l, PREG_SET_ORDER);
$linked = [];
foreach ($l as [, $ver, $url]) {
    $linked[$ver] = $url;
}

// 0.3.9 has a compare link but no section. It was tagged and never published as
// a release, predating the changelog discipline; the orphan link still resolves
// to a valid compare view, so it is not treated as a defect here.
$knownOrphans = ['0.3.9'];

$semver = array_values(array_filter(
    array_keys($linked),
    fn($v) => (bool) preg_match('/^\d+\.\d+\.\d+$/', $v)
));
$semver = array_values(array_diff($semver, $knownOrphans));
usort($semver, 'version_compare');
usort($documented, 'version_compare');
usort($documented, fn($a, $b) => version_compare($b, $a));
echo 'linked versions checked: ' . count($semver) . "\n";

// Every linked version must also have a section: a compare link pointing at a
// version nobody documented is as wrong as a section without a link.
$orphanLinks = array_values(array_diff($semver, $documented));
echo 'compare link without a section: ' . ($orphanLinks === [] ? 'none' : implode(',', $orphanLinks)) . "\n";
if ($orphanLinks !== []) {
    $failures++;
}

// The linked+documented versions must descend from the top of the file.
$linkedInFile = array_values(array_intersect($documented, $semver));
$sortedCheck = $linkedInFile;
usort($sortedCheck, 'version_compare');
$sortedCheck = array_reverse($sortedCheck);
echo 'linked sections in descending order: ' . ($linkedInFile === $sortedCheck ? 'yes' : 'NO') . "\n";
if ($linkedInFile !== $sortedCheck) {
    $failures++;
}

// [Unreleased] must compare against the newest linked version, otherwise every
// link in the file is off by one.
$newest = $sortedCheck[0] ?? '';
$hasUnreleased = $newest !== ''
    && str_contains($changelog, "[Unreleased]: https://github.com/crazy-goat/php-zvec/compare/v$newest...HEAD");
echo 'unreleased link: ' . ($hasUnreleased ? "points at v$newest" : "STALE (expected v$newest)") . "\n";
if (!$hasUnreleased) {
    $failures++;
}

// The newest section's compare link must span the version before it, not an
// unrelated one -- this is what was off before: v0.5.0's link jumped to the
// 0.4.10 that happened to be newest when it was written.
$idx = array_search($newest, $sortedCheck, true);
$prev = $sortedCheck[$idx + 1] ?? null;
$newestLink = $linked[$newest] ?? '';
echo 'newest section link spans: ' . ($prev !== null && str_contains($newestLink, "v$prev...v$newest") ? "v$prev...v$newest" : "UNEXPECTED ($newestLink)") . "\n";
if ($prev !== null && !str_contains($newestLink, "v$prev...v$newest")) {
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

// 4. The zvec SDK version pin is v0.7.0 everywhere it is declared.
foreach (['build_zvec.sh', 'fetch_zvec_sdk.sh', 'src/Installer.php'] as $f) {
    $c = file_get_contents("$root/$f");
    $ok = str_contains($c, 'v0.7.0');
    echo pad($f, 22) . ' ' . ($ok ? 'v0.7.0' : 'WRONG') . "\n";
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
linked versions checked: 29
compare link without a section: none
linked sections in descending order: yes
unreleased link: points at v0.6.0
newest section link spans: v0.5.0...v0.6.0
conflict markers: none
ZVecIndexParams::forDiskAnn   1
ZVecIndexParams::forFts       1
setDiskAnnParams              1
setFts                        1
setHnswPrefetch               1
setIncludeDocId               1
migration section: present
states BC: yes
build_zvec.sh          v0.7.0
fetch_zvec_sdk.sh      v0.7.0
src/Installer.php      v0.7.0
INDEX_TYPE_HNSW           =1 documented
INDEX_TYPE_IVF            =2 documented
INDEX_TYPE_FLAT           =3 documented
INDEX_TYPE_HNSW_RABITQ    =4 documented
INDEX_TYPE_IVF_RABITQ     =7 documented
INDEX_TYPE_VAMANA         =5 documented
INDEX_TYPE_DISKANN        =6 documented
INDEX_TYPE_INVERT         =10 documented
INDEX_TYPE_FTS            =11 documented
PASS
