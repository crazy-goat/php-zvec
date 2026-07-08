<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// getVersion returns a non-empty string
$version = ZVec::getVersion();
echo "version non-empty: " . (strlen($version) > 0 ? '1' : '0') . "\n";

// getVersionMajor/Minor/Patch return integers >= 0
$major = ZVec::getVersionMajor();
$minor = ZVec::getVersionMinor();
$patch = ZVec::getVersionPatch();
echo "major: " . $major . "\n";
echo "minor: " . $minor . "\n";
echo "patch: " . $patch . "\n";

// checkVersion with exact current version
echo "check exact: " . (ZVec::checkVersion($major, $minor, $patch) ? '1' : '0') . "\n";

// checkVersion with lower minor version (should pass)
$lowerMinor = max(0, $minor - 1);
echo "check lower: " . (ZVec::checkVersion($major, $lowerMinor, 0) ? '1' : '0') . "\n";

// checkVersion with higher major (should fail)
echo "check higher: " . (ZVec::checkVersion($major + 1, 0, 0) ? '1' : '0') . "\n";

// checkVersion with negative values (should fail gracefully)
echo "check negative: " . (ZVec::checkVersion(-1, 0, 0) ? '1' : '0') . "\n";

// Verify version string contains the major.minor.patch
$expectedPrefix = "v$major.$minor.$patch";
echo "version starts with $expectedPrefix: " . (str_starts_with($version, $expectedPrefix) ? '1' : '0') . "\n";

echo "OK\n";
?>
