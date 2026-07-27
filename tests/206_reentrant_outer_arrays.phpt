--TEST--
Endpoint and placeholder list conversion retain the original outer arrays
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

class ReplaceOuterArray {
    private $name;
    private $result;

    public function __construct($name, $result) {
        $this->name = $name;
        $this->result = $result;
    }

    public function __toString() {
        $GLOBALS[$this->name] = array_fill(0, 4096, [
            "host" => "127.0.0.1",
            "port" => 1,
        ]);
        return $this->result;
    }
}

$base = clickhouse_test_config();
$GLOBALS["outer_endpoints"] = [
    [
        "host" => new ReplaceOuterArray("outer_endpoints", $base["host"]),
        "port" => $base["port"],
    ],
    ["host" => $base["host"], "port" => $base["port"]],
];
unset($base["host"], $base["port"]);
$base["endpoints"] = &$GLOBALS["outer_endpoints"];
$c = new ClickHouse($base);
echo "endpoint=", $c->ping() ? "ok" : "fail", "\n";

$GLOBALS["outer_columns"] = [
    new ReplaceOuterArray("outer_columns", "number"),
    "number",
];
$row = $c->select(
    "SELECT {columns} FROM numbers(1)",
    ["columns" => &$GLOBALS["outer_columns"]]
)[0];
echo "placeholder=", count($row), "\n";
echo "replacement=", count($GLOBALS["outer_columns"]), "\n";
?>
--EXPECT--
endpoint=ok
placeholder=1
replacement=4096
