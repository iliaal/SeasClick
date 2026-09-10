--TEST--
ClickHouse server-side typed Float params are locale-independent
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
require __DIR__ . "/_clickhouse.inc";
clickhouse_skip_if_no_server();
// de_DE.UTF-8 has to actually exist for the locale switch to take. Skip
// when the runner image doesn't ship it (Alpine, minimal Docker, etc.).
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

$c = new ClickHouse(clickhouse_test_config());

$probe = sprintf('%g', 1.5);
echo "php sprintf 1.5 under locale: ", $probe, "\n";

// PHP < 8 renders floats through LC_NUMERIC; compare numerically to isolate wire formatting.
$res = $c->select("SELECT {x:Float64} AS x", ["x" => 1.5], ClickHouse::FETCH_ONE);
echo "float64 1.5 round-trip: ", ($res === 1.5 ? "1.5" : "WRONG ($res)"), "\n";

$res = $c->select("SELECT {x:Float64} AS x", ["x" => 0.1], ClickHouse::FETCH_ONE);
echo "float64 0.1 round-trip: ", ($res === 0.1 ? "0.1" : "WRONG ($res)"), "\n";

setlocale(LC_NUMERIC, $prev);
?>
--EXPECTF--
locale: %s
php sprintf 1.5 under locale: %s
float64 1.5 round-trip: 1.5
float64 0.1 round-trip: 0.1
