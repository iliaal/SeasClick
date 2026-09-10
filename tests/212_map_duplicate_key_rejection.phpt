--TEST--
Duplicate Map keys are rejected for every key kind, and non-colliding keys keep their PHP key type
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());

/* _add_new bypasses duplicate checks and can create two buckets for one key. */
$collisions = array(
    "string"          => "SELECT map('a','1','a','2','b','3') AS m",
    "packed int"      => "SELECT map(toInt64(1),'x',toInt64(1),'y') AS m",
    "sparse int"      => "SELECT map(toInt64(5),'a',toInt64(5),'b') AS m",
    "large int"       => "SELECT map(toInt64(1000000),'x',toInt64(1000000),'y') AS m",
    "negative int"    => "SELECT map(toInt64(-5),'x',toInt64(-5),'y') AS m",
    "promoted uint64" => "SELECT map(toUInt64(18446744073709551615),'a'," .
                         "toUInt64(18446744073709551615),'b') AS m",
    "numeric string"  => "SELECT map('7','1','7','2') AS m",
    "nested in array" => "SELECT [map('a','1','a','2')] AS m",
    "nested in tuple" => "SELECT tuple(map('a','1','a','2')) AS m",
);
foreach ($collisions as $label => $sql) {
    try {
        $c->select($sql);
        echo "$label: accepted\n";
    } catch (ClickHouseException $e) {
        echo "$label: ",
            strpos($e->getMessage(), "MAP_AS_PAIRS") !== false ? "rejected" : "other",
            "\n";
    }
}

$stream = $c->selectStream("SELECT map('a','1','a','2') AS m");
try {
    $stream->current();
    echo "stream: accepted\n";
} catch (ClickHouseException $e) {
    echo "stream: ",
        strpos($e->getMessage(), "MAP_AS_PAIRS") !== false ? "rejected" : "other",
        "\n";
}

/* Canonical decimal keys become integers; other strings retain their identity. */
$shapes = array(
    "numeric string"  => "SELECT map('123','a','x','b') AS m",
    "leading zero"    => "SELECT map('01','a','1','b') AS m",
    "negative string" => "SELECT map('-5','a','x','b') AS m",
    "oversize string" => "SELECT map('99999999999999999999','a') AS m",
    "float"           => "SELECT map(toFloat64(1),'a',toFloat64(2.5),'b') AS m",
    "int"             => "SELECT map(toInt64(7),'a',toInt64(8),'b') AS m",
    "empty string"    => "SELECT map('','a','x','b') AS m",
);
foreach ($shapes as $label => $sql) {
    $m = $c->select($sql);
    $m = $m[0]["m"];
    $rendered = array();
    foreach (array_keys($m) as $k) {
        $rendered[] = gettype($k) . ":" . var_export($k, true);
    }
    /* Type-tagged so '01' and 1 stay distinct; a raw array_unique() would
     * compare them numerically and hide a genuine duplicate. */
    if (count($rendered) !== count(array_unique($rendered))) {
        echo "$label: DUPLICATE KEYS\n";
        continue;
    }
    echo "$label: ", implode(", ", $rendered), "\n";
}
?>
--EXPECT--
string: rejected
packed int: rejected
sparse int: rejected
large int: rejected
negative int: rejected
promoted uint64: rejected
numeric string: rejected
nested in array: rejected
nested in tuple: rejected
stream: rejected
numeric string: integer:123, string:'x'
leading zero: string:'01', integer:1
negative string: integer:-5, string:'x'
oversize string: string:'99999999999999999999'
float: integer:1, string:'2.5'
int: integer:7, integer:8
empty string: string:'', string:'x'
