--TEST--
String-column insert rejects array and resource cells instead of storing "Array" / "Resource id #N"
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

class _StrictStrBox {
    public function __toString() { return "boxed"; }
}

$c = new ClickHouse(clickhouse_test_config());
$c->execute("DROP TABLE IF EXISTS test.strict_str");
$c->execute("CREATE TABLE test.strict_str (s String) ENGINE = Memory");

error_reporting(E_ALL);

// An array cell used to land as the literal string "Array" after an
// E_WARNING; it must throw like every other strict coercion.
try {
    $c->insert('test.strict_str', ['s'], [[['nested', 'array']]]);
    echo "ARRAY: NO EXCEPTION\n";
} catch (ClickHouseException $e) {
    echo "ARRAY THROWS: ", strpos($e->getMessage(), "array cannot be assigned") !== false ? "clean" : $e->getMessage(), "\n";
}

// A resource cell used to land as "Resource id #N".
$r = fopen('php://memory', 'rb');
try {
    $c->insert('test.strict_str', ['s'], [[$r]]);
    echo "RESOURCE: NO EXCEPTION\n";
} catch (ClickHouseException $e) {
    echo "RESOURCE THROWS: ", strpos($e->getMessage(), "resource cannot be assigned") !== false ? "clean" : $e->getMessage(), "\n";
}
fclose($r);

// Stringable objects remain accepted (intentional support).
$obj = new _StrictStrBox();
$c->insert('test.strict_str', ['s'], [[$obj]]);
var_dump($c->select("SELECT s FROM test.strict_str"));

$c->execute("DROP TABLE test.strict_str");
?>
--EXPECT--
ARRAY THROWS: clean
RESOURCE THROWS: clean
array(1) {
  [0]=>
  array(1) {
    ["s"]=>
    string(5) "boxed"
  }
}
