--TEST--
ClickHouse insertAssoc rejects rows whose key set drifts from the first row
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.fr5_ia");
$c->execute("CREATE TABLE test.fr5_ia (a Int32, b Int32) ENGINE=Memory");

$probes = [
    "extra key on row 2" => [
        ["a" => 1, "b" => 2],
        ["a" => 3, "b" => 4, "c" => 9],
    ],
    "missing key on row 2" => [
        ["a" => 1, "b" => 2],
        ["a" => 3],
    ],
    "renamed key on row 2" => [
        ["a" => 1, "b" => 2],
        ["a" => 3, "x" => 4],
    ],
];
foreach ($probes as $label => $rows) {
    try { $c->insertAssoc("test.fr5_ia", $rows); echo "$label: NO THROW\n"; }
    catch (ClickHouseException $e) { echo "$label: REJECTED\n"; }
}

$c->insertAssoc("test.fr5_ia", [
    ["a" => 10, "b" => 20],
    ["a" => 11, "b" => 21],
]);
$cnt = $c->select("SELECT count() FROM test.fr5_ia", [], ClickHouse::FETCH_ONE);
echo "rowcount: $cnt\n";

$c->execute("DROP TABLE test.fr5_ia");
?>
--EXPECT--
extra key on row 2: REJECTED
missing key on row 2: REJECTED
renamed key on row 2: REJECTED
rowcount: 2
