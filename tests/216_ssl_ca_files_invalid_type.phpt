--TEST--
ssl_ca_files config value of an unsupported type is rejected instead of silently ignored
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
require __DIR__ . "/_clickhouse.inc";
clickhouse_skip_if_no_server();
/* A TLS-enabled build is required to reach CA-file validation. */
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

// Transport/verification failures prove the valid array passed shape validation.
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
