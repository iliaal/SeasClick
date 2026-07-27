--TEST--
selectStream accepts Nullable(Nothing) columns while the memory guard is active
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--INI--
memory_limit=128M
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

/* A finite memory_limit is what arms the selectStream retained-bytes guard.
 * Its per-column estimator serializes unrecognised column types, and
 * ColumnNothing::SaveBody throws, so an untyped NULL used to abort the
 * stream under every php-fpm default. */
$c = new ClickHouse(clickhouse_test_config());

$queries = array(
    "bare null"     => "SELECT NULL AS n",
    "null array"    => "SELECT [NULL] AS a",
    "null beside"   => "SELECT number, NULL AS n FROM numbers(2)",
    "null in tuple" => "SELECT tuple(NULL) AS t",
);
foreach ($queries as $label => $sql) {
    try {
        $rows = 0;
        foreach ($c->selectStream($sql) as $row) {
            $rows++;
        }
        echo "$label: rows=$rows\n";
    } catch (ClickHouseException $e) {
        echo "$label: THROW ", $e->getMessage(), "\n";
    }
}

/* The guard itself must still fire on a genuinely oversized buffer. */
try {
    foreach ($c->selectStream(
        "SELECT repeat('x', 100000) AS s FROM numbers(20000)"
    ) as $row) {
    }
    echo "guard: not triggered\n";
} catch (ClickHouseException $e) {
    echo "guard: ",
        strpos($e->getMessage(), "memory_limit") !== false ? "triggered" : "other",
        "\n";
}
?>
--EXPECT--
bare null: rows=1
null array: rows=1
null beside: rows=2
null in tuple: rows=1
guard: triggered
