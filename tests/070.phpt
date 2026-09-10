--TEST--
ClickHouse Map(Float, *) read keys are locale-independent
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
require __DIR__ . "/_clickhouse.inc";
clickhouse_skip_if_no_server();
$prev = setlocale(LC_NUMERIC, 0);
$got = setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE.utf8', 'de_DE');
setlocale(LC_NUMERIC, $prev);
if ($got === false) {
    echo "skip de_DE locale not installed";
}
?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$prev = setlocale(LC_NUMERIC, 0);
$applied = setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE.utf8', 'de_DE');
echo "locale: ", ($applied === false ? "(not set)" : $applied), "\n";

echo "php sprintf 1.5: ", sprintf('%g', 1.5), "\n";

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.fmap_t");
$c->execute("CREATE TABLE test.fmap_t (m Map(Float64, String)) ENGINE = Memory");
// PHP array keys coerce floats to integers; create Float64 keys on the server.
$c->execute("INSERT INTO test.fmap_t VALUES (map(1.5, 'a', 0.1, 'b'))");

$rows = $c->select("SELECT m FROM test.fmap_t");
$keys = array_keys($rows[0]["m"]);
sort($keys, SORT_STRING);
echo "key 0: ", $keys[0], "\n";
echo "key 1: ", $keys[1], "\n";

setlocale(LC_NUMERIC, $prev);
$c->execute("DROP TABLE test.fmap_t");
?>
--EXPECTF--
locale: %s
php sprintf 1.5: %s
key 0: 0.10000000000000001
key 1: 1.5
