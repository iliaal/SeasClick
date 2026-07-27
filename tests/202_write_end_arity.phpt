--TEST--
writeEnd rejects extra arguments before committing the pending insert
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());
$c->execute("DROP TABLE IF EXISTS test.write_end_arity");
$c->execute("CREATE TABLE test.write_end_arity (n UInt8) ENGINE=Memory");
$c->writeStart("test.write_end_arity", ["n"]);
$c->write([[7]]);

set_error_handler(function ($_n, $message) {
    throw new RuntimeException($message);
});
try {
    $c->writeEnd("ignored");
    echo "extra: accepted\n";
} catch (Throwable $e) {
    echo "extra: rejected\n";
}
restore_error_handler();

var_dump($c->writeEnd());
echo "rows=", $c->select(
    "SELECT count() FROM test.write_end_arity",
    [],
    ClickHouse::FETCH_ONE
), "\n";
$c->execute("DROP TABLE test.write_end_arity");
?>
--EXPECT--
extra: rejected
bool(true)
rows=1
