--TEST--
TLS CA-file conversion retains the original outer array
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php
require __DIR__ . "/_clickhouse.inc";
clickhouse_skip_if_no_tls_server();
$ca = getenv("CLICKHOUSE_TLS_CA_FILE");
if (!$ca || !is_file($ca)) {
    print "skip TLS CA fixture not configured";
}
?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

class ReplaceCaFiles {
    public function __toString() {
        $GLOBALS["ca_files"] = array_fill(0, 4096, "/does/not/exist");
        return getenv("CLICKHOUSE_TLS_CA_FILE");
    }
}

$config = clickhouse_tls_test_config();
$GLOBALS["ca_files"] = [new ReplaceCaFiles()];
$config["ssl_ca_files"] = &$GLOBALS["ca_files"];
$c = new ClickHouse($config);
echo $c->ping() ? "ok\n" : "fail\n";
echo count($GLOBALS["ca_files"]), "\n";
?>
--EXPECT--
ok
4096
