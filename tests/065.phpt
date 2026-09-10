--TEST--
ClickHouse insert path rejects deeply-nested PHP input via depth guard
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.depth_t");

$c->execute("CREATE TABLE test.depth_t (t Tuple(Tuple(Tuple(Int32)))) ENGINE = Memory");
try {
    $c->insert("test.depth_t", ["t"], [[[[[42]]]]]);
    $rows = $c->select("SELECT t FROM test.depth_t");
    echo "shallow ok: ", count($rows), "\n";
} catch (ClickHouseException $e) {
    echo "shallow UNEXPECTED: ", $e->getMessage(), "\n";
}
$c->execute("DROP TABLE test.depth_t");

// Build Tuple(Tuple(... Int32 ...)) to depth 40. The guard fires at 32
// while createColumn walks the type tree during BeginInsert / insert.
$inner = "Int32";
for ($i = 0; $i < 40; $i++) {
    $inner = "Tuple($inner)";
}
$c->execute("CREATE TABLE test.depth_t (t $inner) ENGINE = Memory");

$val = 7;
for ($i = 0; $i < 40; $i++) {
    $val = [$val];
}

try {
    $c->insert("test.depth_t", ["t"], [[$val]]);
    echo "deep insert: no throw\n";
} catch (ClickHouseException $e) {
    $msg = $e->getMessage();
    if (stripos($msg, "depth") !== false || stripos($msg, "nested") !== false) {
        echo "deep insert: depth guard threw\n";
    } else {
        echo "deep insert: other throw: ", $msg, "\n";
    }
}

$c->execute("DROP TABLE test.depth_t");
?>
--EXPECT--
shallow ok: 1
deep insert: depth guard threw
