--TEST--
Successful query logs and verbose starts retain pre-substitution SQL
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$verboseSql = [];
$c = new ClickHouse(clickhouse_test_config());
$c->enableLogQueries(true);
$c->setVerbose(function ($event, $ctx) use (&$verboseSql) {
    if ($event === "select_start" || $event === "execute_start") {
        $verboseSql[] = $ctx["sql"];
    }
});

$c->select("SELECT {v}", ["v" => "101"]);
$c->execute("SELECT {v}", ["v" => "202"]);
$stream = $c->selectStream("SELECT {v}", ["v" => "303"]);
foreach ($stream as $row) {}
$c->selectStreamCallback("SELECT {v}", function ($row) {}, ["v" => "404"]);
$out = fopen("php://temp", "w+");
$c->selectToStream("SELECT {v}", ["v" => "505"], $out);

foreach ($c->getLogQueries() as $log) {
    echo $log["sql"], "\n";
}
foreach ($verboseSql as $sql) {
    echo "verbose:", $sql, "\n";
}
?>
--EXPECT--
SELECT {v}
SELECT {v}
SELECT {v}
SELECT {v}
SELECT {v}
verbose:SELECT {v}
verbose:SELECT {v}
verbose:SELECT {v}
verbose:SELECT {v}
verbose:SELECT {v}
