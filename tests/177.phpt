--TEST--
DR-C6: Array(String) typed parameters round-trip quotes and backslashes correctly
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// Bound arrays require doubled quotes and literal backslashes.

$c = new ClickHouse(clickhouse_test_config());

$cases = array("it's", "c\\d", "plain", 'a"b', "both'\\x", "''");
foreach ($cases as $s) {
    $r = $c->select("SELECT arrayJoin({p:Array(String)}) AS v", array("p" => array($s)));
    $got = $r[0]['v'];
    echo bin2hex($s), " -> ", bin2hex($got), " ", ($got === $s ? "MATCH" : "DIFF"), "\n";
}

$r = $c->select("SELECT arrayStringConcat({p:Array(String)}, '|') AS v",
                array("p" => array("a'b", "c")));
echo "multi: ", $r[0]['v'], "\n";
?>
--EXPECT--
69742773 -> 69742773 MATCH
635c64 -> 635c64 MATCH
706c61696e -> 706c61696e MATCH
612262 -> 612262 MATCH
626f7468275c78 -> 626f7468275c78 MATCH
2727 -> 2727 MATCH
multi: a'b|c
