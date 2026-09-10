--TEST--
DR-008: a reentrant insert on a second client does not inherit the allow-null guard
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// __toString reentry must not carry client A's relaxed Nullable state into client B.

$cfg = clickhouse_test_config();
$a = new ClickHouse($cfg);
$b = new ClickHouse($cfg);
$a->execute("CREATE DATABASE IF NOT EXISTS test");
$a->execute("DROP TABLE IF EXISTS test.dr008_nn");
$a->execute("CREATE TABLE test.dr008_nn (i Int32) ENGINE = Memory");
$a->execute("DROP TABLE IF EXISTS test.dr008_null");
$a->execute("CREATE TABLE test.dr008_null (s Nullable(String)) ENGINE = Memory");

class Reenter {
    public $b;
    public function __construct($b) { $this->b = $b; }
    public function __toString() {
        try { $this->b->insert("test.dr008_nn", ['i'], [[null]]); echo "reentrant null: NO THROW\n"; }
        catch (ClickHouseException $e) { echo "reentrant null: REJECTED\n"; }
        return "a-value";
    }
}

// Client A builds a Nullable(String) column; during the child build its
// __toString reenters client B's non-Nullable insert.
$a->insert("test.dr008_null", ['s'], [[new Reenter($b)]]);

$r = $b->select("SELECT count() c FROM test.dr008_nn");
echo "second-client rows: ", $r[0]['c'], "\n";

$a->execute("DROP TABLE test.dr008_nn");
$a->execute("DROP TABLE test.dr008_null");
?>
--EXPECT--
reentrant null: REJECTED
second-client rows: 0
