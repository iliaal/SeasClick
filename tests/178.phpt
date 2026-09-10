--TEST--
DR-007: a typed-parameter parse error redacts the bound value instead of leaking it
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// Parameter parse errors echo values before execution markers.

$c = new ClickHouse(clickhouse_test_config());

try {
    $c->select("SELECT {x:UInt32} AS n", array("x" => "s3cr3t_value"));
    echo "no throw\n";
} catch (ClickHouseException $e) {
    $msg = $e->getMessage();
    echo "leaks value: ", (strpos($msg, "s3cr3t_value") !== false ? "YES" : "no"), "\n";
    echo "redacted: ", (strpos($msg, "<redacted>") !== false ? "YES" : "no"), "\n";
}
?>
--EXPECT--
leaks value: no
redacted: YES
