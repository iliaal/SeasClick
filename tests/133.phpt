--TEST--
Zero-arg accessors enforce arity (getServerInfo, getCurrentEndpoint, getStatistics, getLogQueries, ping, resetConnection)
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$ch = new ClickHouse(clickhouse_test_config());

/* Normalize PHP 7.4 arity warnings to exceptions, matching PHP 8 behavior. */
set_error_handler(function ($_n, $msg) { throw new RuntimeException($msg); });
foreach (["getServerInfo", "getCurrentEndpoint", "getStatistics", "getLogQueries", "ping", "resetConnection"] as $m) {
    try {
        $ch->$m("extra");
        echo "$m: no throw\n";
    } catch (Throwable $e) {
        echo "$m: REJECTED\n";
    }
}
restore_error_handler();
?>
--EXPECT--
getServerInfo: REJECTED
getCurrentEndpoint: REJECTED
getStatistics: REJECTED
getLogQueries: REJECTED
ping: REJECTED
resetConnection: REJECTED
