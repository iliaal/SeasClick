--TEST--
JSON column insert rejects an invalid raw JSON string with a clean exception
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
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

// Pre-fix, on PHP < 8.3 this path reached php_json_decode() FAILURE and ran
// zval_ptr_dtor() over an UNINITIALIZED stack zval (php_json_decode does not
// touch its output on failure). It must throw cleanly on every PHP version.
try {
    $c->insert('test.json_bad_str', ['j'], [['{not json']]);
    echo "NO EXCEPTION\n";
} catch (ClickHouseException $e) {
    echo "THROWS: ", strpos($e->getMessage(), "not valid JSON") !== false ? "clean" : $e->getMessage(), "\n";
}

// A valid JSON object string still inserts; reads surface the raw string.
$c->insert('test.json_bad_str', ['j'], [['{"a":1}']]);
$rows = $c->select("SELECT j FROM test.json_bad_str");
echo "isstr=", is_string($rows[0]["j"]) ? 1 : 0, " ", $rows[0]["j"], "\n";

$c->execute("DROP TABLE test.json_bad_str");
?>
--EXPECT--
THROWS: clean
isstr=1 {"a":1}
