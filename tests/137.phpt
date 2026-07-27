--TEST--
insert() converts a stable snapshot when a by-ref row is reassigned mid-conversion
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.byref_row");
$c->execute("CREATE TABLE test.byref_row (s String, n Int32) ENGINE=Memory");

class Evil {
    public $ref;
    public function __toString(): string {
        $this->ref = 42;   // row 0 stops being an array
        return "x";
    }
}
$e = new Evil();
$rows = [[$e, 1], ["y", 2]];
$e->ref = &$rows[0];       // row 0 is now a by-ref bucket Evil writes through

$c->insert("test.byref_row", ["s", "n"], $rows);
$result = $c->select("SELECT s, n FROM test.byref_row ORDER BY n");
foreach ($result as $row) {
    echo $row["s"], ":", $row["n"], "\n";
}

echo "ping=", ($c->ping() ? "ok" : "fail"), "\n";

$c->execute("DROP TABLE test.byref_row");
?>
--EXPECT--
x:1
y:2
ping=ok
