--TEST--
MAP_AS_PAIRS preserves duplicate and numeric-string Map keys across a round-trip
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());
$sql = "SELECT mapFromArrays(" .
    "['dup', 'dup', '1'], ['first', 'second', 'numeric']) AS m";

try {
    $c->select($sql);
    echo "default=accepted\n";
} catch (ClickHouseException $e) {
    echo strpos($e->getMessage(), "MAP_AS_PAIRS") !== false
        ? "default=rejected-lossy\n"
        : "default=other-error\n";
}

$pairs = $c->select($sql, [], ClickHouse::MAP_AS_PAIRS)[0]["m"];
echo json_encode($pairs), "\n";

$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.map_lossless_pairs");
$c->execute(
    "CREATE TABLE test.map_lossless_pairs " .
    "(m Map(String, String)) ENGINE=Memory"
);
$c->insert("test.map_lossless_pairs", ["m"], [[$pairs]]);
$roundTrip = $c->select(
    "SELECT m FROM test.map_lossless_pairs",
    [],
    ClickHouse::MAP_AS_PAIRS
)[0]["m"];
echo json_encode($roundTrip), "\n";

$stream = $c->selectStream(
    "SELECT m FROM test.map_lossless_pairs",
    [],
    "",
    [],
    ClickHouse::MAP_AS_PAIRS
);
echo json_encode($stream->current()["m"]), "\n";

$c->execute("DROP TABLE test.map_lossless_pairs");
?>
--EXPECT--
default=rejected-lossy
[["dup","first"],["dup","second"],["1","numeric"]]
[["dup","first"],["dup","second"],["1","numeric"]]
[["dup","first"],["dup","second"],["1","numeric"]]
