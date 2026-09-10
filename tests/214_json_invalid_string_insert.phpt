--TEST--
JSON column insert rejects an invalid raw JSON string with a clean exception
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
require __DIR__ . "/_clickhouse.inc";
clickhouse_skip_if_no_server();
clickhouse_skip_if_no_json();
?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";
$c = new ClickHouse(clickhouse_test_config());
$c->setSettings([
    "allow_experimental_json_type"              => 1,
    "output_format_native_write_json_as_string" => 1,
]);
$c->execute("DROP TABLE IF EXISTS test.json_bad_str");
$c->execute("CREATE TABLE test.json_bad_str (j JSON) ENGINE = Memory");

// PHP < 8.3 uses json_decode, which leaves its output untouched on failure.
try {
    $c->insert('test.json_bad_str', ['j'], [['{not json']]);
    echo "NO EXCEPTION\n";
} catch (ClickHouseException $e) {
    echo "THROWS: ", strpos($e->getMessage(), "not valid JSON") !== false ? "clean" : $e->getMessage(), "\n";
}

$c->insert('test.json_bad_str', ['j'], [['{"a":1}']]);
$rows = $c->select("SELECT j FROM test.json_bad_str");
echo "isstr=", is_string($rows[0]["j"]) ? 1 : 0, " ", $rows[0]["j"], "\n";

// A valid server JSON column cannot exercise decode failure without a corrupt peer.
$dec = $c->select("SELECT j FROM test.json_bad_str", [], ClickHouse::JSON_AS_ARRAY);
echo "isarr=", is_array($dec[0]["j"]) ? 1 : 0, " a=", $dec[0]["j"]["a"], "\n";

$c->execute("DROP TABLE test.json_bad_str");
?>
--EXPECT--
THROWS: clean
isstr=1 {"a":1}
isarr=1 a=1
