--TEST--
Int64 and UInt32 preserve values outside the 32-bit PHP integer range
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.integer_width");
$c->execute(
    "CREATE TABLE test.integer_width " .
    "(negative Int64, positive Int64, unsigned UInt32, plus_sign UInt32) " .
    "ENGINE=Memory"
);
$c->insert(
    "test.integer_width",
    ["negative", "positive", "unsigned", "plus_sign"],
    [["-2147483649", "2147483648", "4294967295", "+1"]]
);

$row = $c->select(
    "SELECT negative, positive, unsigned, plus_sign FROM test.integer_width"
)[0];
echo (string)$row["negative"], "\n";
echo (string)$row["positive"], "\n";
echo (string)$row["unsigned"], "\n";
echo (string)$row["plus_sign"], "\n";
$expectInt = PHP_INT_SIZE > 4;
var_dump(is_int($row["negative"]) === $expectInt);
var_dump(is_int($row["positive"]) === $expectInt);
var_dump(is_int($row["unsigned"]) === $expectInt);

$c->execute("DROP TABLE test.integer_width");
?>
--EXPECT--
-2147483649
2147483648
4294967295
1
bool(true)
bool(true)
bool(true)
