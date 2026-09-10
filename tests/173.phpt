--TEST--
DR-002: non-Nullable Decimal rejects over-precision / over-scale instead of silently storing a wrong value
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");

function try_insert($c, $label, $type, $val) {
    $c->execute("DROP TABLE IF EXISTS test.dr002");
    $c->execute("CREATE TABLE test.dr002 (v $type) ENGINE = Memory");
    try {
        $c->insert("test.dr002", array('v'), array(array($val)));
        $r = $c->select("SELECT toString(v) s FROM test.dr002");
        echo "$label: stored ", $r[0]['s'], "\n";
    } catch (ClickHouseException $e) {
        echo "$label: REJECTED\n";
    }
}

try_insert($c, "Dec(5,2) 1000.00", "Decimal(5,2)", "1000.00");
try_insert($c, "Dec(5,2) 12.999",  "Decimal(5,2)", "12.999");
try_insert($c, "Dec(5,2) 999.99",  "Decimal(5,2)", "999.99");
try_insert($c, "Dec(5,2) -999.99", "Decimal(5,2)", "-999.99");
try_insert($c, "Dec(5,2) 0.5",     "Decimal(5,2)", "0.5");

$c->execute("DROP TABLE IF EXISTS test.dr002");
?>
--EXPECT--
Dec(5,2) 1000.00: REJECTED
Dec(5,2) 12.999: REJECTED
Dec(5,2) 999.99: stored 999.99
Dec(5,2) -999.99: stored -999.99
Dec(5,2) 0.5: stored 0.5
