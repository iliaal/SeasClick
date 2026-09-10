--TEST--
fetchOne returns the full row for a multi-column result with duplicate column names
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$ch = new ClickHouse(clickhouse_test_config());

/* Duplicate names collapse in assoc rows but still represent two columns. */
$dup = $ch->selectStatement("SELECT number, number FROM system.numbers LIMIT 1 OFFSET 41");
var_dump(is_array($dup->fetchOne()));

$one = $ch->selectStatement("SELECT 7 AS x");
var_dump($one->fetchOne());

$multi = $ch->selectStatement("SELECT 1 AS a, 2 AS b");
var_dump($multi->fetchOne());
?>
--EXPECT--
bool(true)
int(7)
array(2) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
}
