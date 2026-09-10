--TEST--
ClickHouse rejects non-array input on declared-array parameters with TypeError (no segfault)
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$probes_ctor = [
    "__construct(string)" => fn() => new ClickHouse("not-an-array"),
    "__construct(int)"    => fn() => new ClickHouse(42),
    "__construct(null)"   => fn() => new ClickHouse(null),
    "__construct(object)" => fn() => new ClickHouse(new stdClass()),
];
foreach ($probes_ctor as $label => $fn) {
    try { $fn(); echo "$label: NO THROW\n"; }
    catch (TypeError $e) { echo "$label: TypeError\n"; }
    catch (Throwable $e) { echo "$label: ", get_class($e), "\n"; }
}

// Method-level type guards. Build a real client so we exercise the
// per-method ZPP, not the constructor path again.
$c = new ClickHouse(clickhouse_test_config());
$probes_methods = [
    "insert values=string"      => fn() => $c->insert("t", ["c"], "not-an-array"),
    "insert columns=string"     => fn() => $c->insert("t", "not-an-array", [[1]]),
    "write(string)"             => fn() => $c->write("not-an-array"),
    "insertAssoc rows=string"   => fn() => $c->insertAssoc("t", "not-an-array"),
    "execute params=int"        => fn() => $c->execute("SELECT 1", 42),
    "select params=int"         => fn() => $c->select("SELECT 1", 42),
    "select settings=int"       => fn() => $c->select("SELECT 1", [], 0, "", 42),
    "writeStart columns=int"    => fn() => $c->writeStart("t", 42),
];
foreach ($probes_methods as $label => $fn) {
    try { $fn(); echo "$label: NO THROW\n"; }
    catch (TypeError $e) { echo "$label: TypeError\n"; }
    catch (Throwable $e) { echo "$label: ", get_class($e), "\n"; }
}
?>
--EXPECT--
__construct(string): TypeError
__construct(int): TypeError
__construct(null): TypeError
__construct(object): TypeError
insert values=string: TypeError
insert columns=string: TypeError
write(string): TypeError
insertAssoc rows=string: TypeError
execute params=int: TypeError
select params=int: TypeError
select settings=int: TypeError
writeStart columns=int: TypeError
