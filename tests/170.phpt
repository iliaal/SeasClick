--TEST--
DR-006: insertFromStream() rejects an unbounded batch_rows
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.dr006");
$c->execute("CREATE TABLE test.dr006 (n UInt32) ENGINE = Memory");

$stream = fopen("php://memory", "r+");
fwrite($stream, "1\n2\n3\n");
rewind($stream);

try {
    $c->insertFromStream("test.dr006", ["n"], $stream, "TabSeparated", PHP_INT_MAX);
    echo "huge batch_rows: NO THROW\n";
} catch (ClickHouseException $e) {
    echo "huge batch_rows: REJECTED\n";
}
fclose($stream);

$stream = fopen("php://memory", "r+");
fwrite($stream, "1\n");
rewind($stream);
try {
    $c->insertFromStream("test.dr006", ["n"], $stream, "TabSeparated", 0);
    echo "zero batch_rows: NO THROW\n";
} catch (ClickHouseException $e) {
    echo "zero batch_rows: REJECTED\n";
}
fclose($stream);

$stream = fopen("php://memory", "r+");
fwrite($stream, "10\n20\n30\n");
rewind($stream);
$c->insertFromStream("test.dr006", ["n"], $stream, "TabSeparated", 2);
fclose($stream);
$r = $c->select("SELECT count() c, sum(n) s FROM test.dr006");
echo "normal insert: count=", $r[0]['c'], " sum=", $r[0]['s'], "\n";

$c->execute("DROP TABLE test.dr006");
?>
--EXPECT--
huge batch_rows: REJECTED
zero batch_rows: REJECTED
normal insert: count=3 sum=60
