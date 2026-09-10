--TEST--
DR-005 (stream insert): a validation error before any block is sent keeps session state
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// A parser failure before any flush must preserve session state.

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.dr005s");
$c->execute("CREATE TABLE test.dr005s (i Int32, j Int32) ENGINE = Memory");

$c->execute("CREATE TEMPORARY TABLE dr005s_tmp (n UInt8)");
$c->execute("INSERT INTO dr005s_tmp VALUES (7)");

// Row 1 good, row 2 has the wrong column count -> the parser throws mid-feed,
// before the (large) batch threshold flushes any block.
$stream = fopen("php://memory", "r+");
fwrite($stream, "1\t2\n1\t2\t3\n");
rewind($stream);
try {
    $c->insertFromStream("test.dr005s", array('i','j'), $stream, "TabSeparated", 1000);
    echo "insert: NO THROW\n";
} catch (ClickHouseException $e) {
    echo "insert: threw\n";
}
fclose($stream);

try {
    $r = $c->select("SELECT n FROM dr005s_tmp");
    echo "temp table survives: ", json_encode(array_column($r, 'n')), "\n";
} catch (ClickHouseException $e) {
    echo "temp table GONE (reconnected)\n";
}

$r = $c->select("SELECT 1 AS ok");
echo "handle usable: ", $r[0]['ok'], "\n";

$c->execute("DROP TABLE IF EXISTS test.dr005s");
?>
--EXPECT--
insert: threw
temp table survives: [7]
handle usable: 1
