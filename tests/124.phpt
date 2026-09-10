--TEST--
ClickHouseException getters deref a property held as a reference
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$ch = new ClickHouse(clickhouse_test_config());
try {
    $ch->execute("SELECT throwIf(1, 'boom')");
    echo "no exception\n";
} catch (ClickHouseException $e) {
    /* A referenced property exposes IS_REFERENCE to the native getter. */
    $codeRef = &$e->server_code;
    $nameRef = &$e->server_name;
    echo "code matches: ", ($e->getServerCode() === $e->server_code ? "yes" : "no"), "\n";
    echo "code nonzero: ", ($e->getServerCode() !== 0 ? "yes" : "no"), "\n";
    echo "name matches: ", ($e->getServerName() === $e->server_name ? "yes" : "no"), "\n";
}
?>
--EXPECT--
code matches: yes
code nonzero: yes
name matches: yes
