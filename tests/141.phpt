--TEST--
Typed placeholder values passed by reference are dereferenced (null and array shapes)
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());


$n = null;
$pn = ["v" => &$n];
$r = $c->select("SELECT {v:Nullable(UInt8)} AS x", $pn, ClickHouse::FETCH_ONE);
echo "byref null is null=", (is_null($r) ? "yes" : "no"), "\n";

$arr = [1, 2, 3];
$pa = ["v" => &$arr];
$sum = $c->select("SELECT arraySum({v:Array(UInt8)}) AS x", $pa, ClickHouse::FETCH_ONE);
echo "byref array sum=", $sum, "\n";
?>
--EXPECT--
byref null is null=yes
byref array sum=6
