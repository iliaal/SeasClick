--TEST--
ClickHouse by-reference select() params: applyPlaceholders derefs IS_REFERENCE buckets (typed NULL stays NULL, arrays are not stringified)
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());

$p1 = ["p" => null];
foreach ($p1 as $k => &$v) { $v = $v; }
unset($v);
$isNull = $c->select("SELECT {p:Nullable(String)} IS NULL AS n", $p1, ClickHouse::FETCH_ONE);
echo "by-ref NULL typed param IS NULL: ", ($isNull ? "yes" : "no"), "\n";

$p2 = ["ids" => [10, 20, 30]];
foreach ($p2 as $k => &$v) { $v = $v; }
unset($v);
$len = $c->select("SELECT length({ids:Array(UInt64)}) AS l", $p2, ClickHouse::FETCH_ONE);
echo "by-ref Array typed param length: ", $len, "\n";

$p3 = ["cols" => ["number"]];
foreach ($p3 as $k => &$v) { $v = $v; }
unset($v);
$rows = $c->select("SELECT {cols} FROM system.numbers LIMIT 1", $p3);
echo "by-ref identifier list key: ", implode(",", array_keys($rows[0])), "\n";

$isNull2 = $c->select("SELECT {p:Nullable(String)} IS NULL AS n", ["p" => null], ClickHouse::FETCH_ONE);
echo "plain NULL typed param IS NULL: ", ($isNull2 ? "yes" : "no"), "\n";
?>
--EXPECT--
by-ref NULL typed param IS NULL: yes
by-ref Array typed param length: 3
by-ref identifier list key: number
plain NULL typed param IS NULL: yes
