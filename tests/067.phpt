--TEST--
ClickHouse selectStreamCallback row callback exception aborts the stream and surfaces to caller
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());

$seen = 0;
try {
    $c->selectStreamCallback(
        "SELECT number FROM numbers(1000)",
        function ($row) use (&$seen) {
            $seen++;
            if ($seen >= 3) {
                throw new RuntimeException("stop at row " . $seen);
            }
        }
    );
    echo "no throw\n";
} catch (RuntimeException $e) {
    echo "user exception: ", $e->getMessage(), "\n";
}

echo "rows seen: ", $seen, "\n";
echo "stream stopped early: ", ($seen < 1000 ? "yes" : "no"), "\n";
?>
--EXPECT--
user exception: stop at row 3
rows seen: 3
stream stopped early: yes
