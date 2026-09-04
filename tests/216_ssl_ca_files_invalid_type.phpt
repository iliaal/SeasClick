--TEST--
ssl_ca_files config value of an unsupported type is rejected instead of silently ignored
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
require __DIR__ . "/_clickhouse.inc";
clickhouse_skip_if_no_server();
/* The ssl_ca_files validation lives inside the WITH_OPENSSL constructor
 * branch behind want_ssl. On a build without --enable-clickhouse-openssl
 * every 'ssl' => true construction throws "without TLS support" before the
 * branch is reachable, so skip like tests/027 does for its TLS lane. */
$probe = clickhouse_test_config();
$probe['ssl'] = true;
try {
    new ClickHouse($probe);
} catch (ClickHouseException $e) {
    if (strpos($e->getMessage(), "without TLS") !== false) {
        print "skip extension built without TLS support";
        exit;
    }
}
?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// A non-string/non-array value used to silently connect with NO CA files
// set. (A plain string is VALID here -- one CA file path -- so the invalid
// shapes are true scalars like int/bool/float.)
$base = clickhouse_test_config();
$base['ssl'] = true;

foreach ([123, true, false, 1.5] as $bad) {
    $cfg = $base;
    $cfg['ssl_ca_files'] = $bad;
    try {
        new ClickHouse($cfg);
        echo gettype($bad), ": NO EXCEPTION\n";
    } catch (ClickHouseException $e) {
        echo strpos($e->getMessage(), "ssl_ca_files must be a string or an array of strings") !== false
            ? gettype($bad) . ": rejected\n" : gettype($bad) . ": unexpected: {$e->getMessage()}\n";
    }
}

// Array elements must be strings too: an int/bool element must not coerce to
// "123"/"1" and connect with a bogus CA path.
foreach ([[123], [true]] as $bad) {
    $cfg = $base;
    $cfg['ssl_ca_files'] = $bad;
    try {
        new ClickHouse($cfg);
        echo "element ", gettype($bad[0]), ": NO EXCEPTION\n";
    } catch (ClickHouseException $e) {
        echo strpos($e->getMessage(), "ssl_ca_files must be a string or an array of strings") !== false
            ? "element " . gettype($bad[0]) . ": rejected\n" : "element " . gettype($bad[0]) . ": unexpected: {$e->getMessage()}\n";
    }
}

// A proper array passes config-shape validation; any failure past that point
// (connect-level, bogus CA path) must NOT name ssl_ca_files. Only transport /
// verification errors count as accepted here: an ssl_ca_files-shaped message
// is a wrongful rejection, and any non-ClickHouse throw is a hard failure.
$ok = $base;
$ok['ssl_ca_files'] = ['/nonexistent/ca.pem'];
try {
    new ClickHouse($ok);
    echo "array shape: accepted\n";
} catch (ClickHouseException $e) {
    echo strpos($e->getMessage(), "ssl_ca_files") !== false
        ? "array shape: WRONG rejection\n"
        : "array shape: accepted\n";
} catch (\Throwable $e) {
    echo "array shape: UNEXPECTED ", get_class($e), ": {$e->getMessage()}\n";
}
?>
--EXPECT--
integer: rejected
boolean: rejected
boolean: rejected
double: rejected
element integer: rejected
element boolean: rejected
array shape: accepted
