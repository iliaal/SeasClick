--TEST--
ClickHouse write() rejects rows narrower than the writeStart() column count
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.write_short");
$c->execute("CREATE TABLE test.write_short (a UInt8, b UInt8) ENGINE=Memory");

$probes = [
    "short first row"        => [[[1]]],
    "short later row"        => [[[1, 2], [3]]],
    "short first row 2cols"  => [[["a"=>1]]],
];
foreach ($probes as $label => [$rows]) {
    $c->writeStart("test.write_short", ["a", "b"]);
    try {
        $c->write($rows);
        echo "$label: NO THROW\n";
    } catch (ClickHouseException $e) {
        echo "$label: REJECTED\n";
    }
    try { $c->execute("SELECT 1"); }
    catch (ClickHouseException $e) { echo "$label: client wedged: ", $e->getMessage(), "\n"; }
}

$c->writeStart("test.write_short", ["a", "b"]);
$c->write([[10, 20], [11, 21]]);
$c->writeEnd();
$cnt = $c->select("SELECT count() FROM test.write_short", [], ClickHouse::FETCH_ONE);
echo "rowcount: $cnt\n";

$c->execute("DROP TABLE test.write_short");
?>
--EXPECT--
short first row: REJECTED
short later row: REJECTED
short first row 2cols: REJECTED
rowcount: 2
