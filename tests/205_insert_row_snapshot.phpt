--TEST--
insert() does not mix cells from different versions of a referenced row
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

class ReplaceInsertRow {
    public function __toString() {
        $GLOBALS["snapshot_row"] = ["after", 99];
        return "before";
    }
}

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.insert_row_snapshot");
$c->execute("CREATE TABLE test.insert_row_snapshot (s String, n Int32) ENGINE=Memory");

$GLOBALS["snapshot_row"] = [new ReplaceInsertRow(), 1];
$rows = [&$GLOBALS["snapshot_row"]];
$c->insert("test.insert_row_snapshot", ["s", "n"], $rows);

$row = $c->select("SELECT s, n FROM test.insert_row_snapshot")[0];
echo $row["s"], ":", $row["n"], "\n";
echo $GLOBALS["snapshot_row"][0], ":", $GLOBALS["snapshot_row"][1], "\n";

$c->execute("DROP TABLE test.insert_row_snapshot");
?>
--EXPECT--
before:1
after:99
