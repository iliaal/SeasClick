--TEST--
selectStream and selectToStream emit the standard verbose lifecycle
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

function runVerboseProbe($operation) {
    $events = [];
    $c = new ClickHouse(clickhouse_test_config());
    $c->setVerbose(function ($event) use (&$events) {
        $events[$event] = ($events[$event] ?? 0) + 1;
    });
    $operation($c);
    echo "start=", ($events["select_start"] ?? 0), "\n";
    echo "block=", (($events["data_block"] ?? 0) >= 1 ? "yes" : "no"), "\n";
    echo "finish=", ($events["select_finish"] ?? 0), "\n";
}

echo "selectStream\n";
runVerboseProbe(function ($c) {
    $it = $c->selectStream("SELECT number FROM numbers(2)");
    echo "rows=", count($it), "\n";
});

echo "selectToStream\n";
runVerboseProbe(function ($c) {
    $out = fopen("php://temp", "w+");
    echo "rows=", $c->selectToStream(
        "SELECT number FROM numbers(2)",
        [],
        $out
    ), "\n";
});
?>
--EXPECT--
selectStream
rows=2
start=1
block=yes
finish=1
selectToStream
rows=2
start=1
block=yes
finish=1
