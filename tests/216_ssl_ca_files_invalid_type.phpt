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
// shapes are true scalars like int/bool.)
$base = clickhouse_test_config();
$base['ssl'] = true;

foreach ([123 => 'int', true => 'bool'] as $bad => $shape) {
    $cfg = $base;
    $cfg['ssl_ca_files'] = $bad;
    try {
        new ClickHouse($cfg);
        echo "$shape: NO EXCEPTION\n";
    } catch (ClickHouseException $e) {
        echo strpos($e->getMessage(), "ssl_ca_files must be a string or an array of strings") !== false
            ? "$shape: rejected\n" : "$shape: unexpected: {$e->getMessage()}\n";
    }
}

// A proper array passes config-shape validation; any failure past that point
// (connect-level, bogus CA path) must NOT name ssl_ca_files.
$ok = $base;
$ok['ssl_ca_files'] = ['/nonexistent/ca.pem'];
try {
    new ClickHouse($ok);
    echo "array shape: accepted\n";
} catch (ClickHouseException $e) {
    echo strpos($e->getMessage(), "ssl_ca_files") !== false
        ? "array shape: WRONG rejection\n"
        : "array shape: accepted\n";
}
?>
--EXPECT--
int: rejected
bool: rejected
array shape: accepted
