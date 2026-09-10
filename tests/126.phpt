--TEST--
Per-call settings reject non-string and empty keys (parity with setSettings)
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$ch = new ClickHouse(clickhouse_test_config());

/* An empty setting key terminates the native-protocol settings section. */
try {
    $ch->execute("SELECT 1", [], "", ["" => "1"]);
    echo "empty key: no throw\n";
} catch (ClickHouseException $e) {
    echo "empty key: ", (strpos($e->getMessage(), "must not be empty") !== false ? "rejected" : "other"), "\n";
}

try {
    $ch->execute("SELECT 1", [], "", [0 => "1"]);
    echo "numeric key: no throw\n";
} catch (ClickHouseException $e) {
    echo "numeric key: ", (strpos($e->getMessage(), "must be strings") !== false ? "rejected" : "other"), "\n";
}

var_dump($ch->select("SELECT 1 AS x", [], 0, "", ["max_block_size" => "100"])[0]["x"]);
?>
--EXPECT--
empty key: rejected
numeric key: rejected
int(1)
