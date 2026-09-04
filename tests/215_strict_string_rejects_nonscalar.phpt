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

// FixedString and IPv4 share the strict string path: array/resource cells
// throw instead of landing as "Array" / "Resource id #N".
$c->execute("DROP TABLE IF EXISTS test.strict_other");
$c->execute("CREATE TABLE test.strict_other (f FixedString(8), ip IPv4, m Map(String, String)) ENGINE = Memory");

foreach (["f", "ip"] as $col) {
    $valid = ["f" => "oktext", "ip" => "127.0.0.1", "m" => []];
    $row = [$valid["f"], $valid["ip"], $valid["m"]];
    $row[array_search($col, ["f", "ip"])] = ["nested", "array"];
    try {
        $c->insert("test.strict_other", ["f", "ip", "m"], [$row]);
        echo strtoupper($col), " ARRAY: NO EXCEPTION\n";
    } catch (ClickHouseException $e) {
        echo strtoupper($col), " ARRAY THROWS: ", strpos($e->getMessage(), "cannot be assigned") !== false ? "clean" : $e->getMessage(), "\n";
    }
    $r2 = fopen("php://memory", "rb");
    $row[array_search($col, ["f", "ip"])] = $r2;
    try {
        $c->insert("test.strict_other", ["f", "ip", "m"], [$row]);
        echo strtoupper($col), " RESOURCE: NO EXCEPTION\n";
    } catch (ClickHouseException $e) {
        echo strtoupper($col), " RESOURCE THROWS: ", strpos($e->getMessage(), "cannot be assigned") !== false ? "clean" : $e->getMessage(), "\n";
    }
    fclose($r2);
}

// Map(String, String) values share it too: a nested array or a resource as
// the map value throws instead of coercing.
$r = fopen("php://memory", "rb");
foreach ([["k" => ["nested"]], ["k" => $r]] as $i => $cell) {
    try {
        $c->insert("test.strict_other", ["f", "ip", "m"], [["oktext", "127.0.0.1", $cell]]);
        echo "MAP $i: NO EXCEPTION\n";
    } catch (ClickHouseException $e) {
        echo "MAP $i THROWS: ", strpos($e->getMessage(), "cannot be assigned") !== false ? "clean" : $e->getMessage(), "\n";
    }
}
fclose($r);

$c->execute("DROP TABLE test.strict_other");
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
F ARRAY THROWS: clean
F RESOURCE THROWS: clean
IP ARRAY THROWS: clean
IP RESOURCE THROWS: clean
MAP 0 THROWS: clean
MAP 1 THROWS: clean
