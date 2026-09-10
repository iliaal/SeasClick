--TEST--
ClickHouse with a self-capturing callback is reclaimed by the cycle collector
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

/* GC must see the client -> callback -> client cycle outside the property table. */
$ch = new ClickHouse(clickhouse_test_config());
$ch->setProgressCallback(function ($p) use ($ch) { /* keeps $ch alive */ });
$w = WeakReference::create($ch);

unset($ch);
/* PHP 7.4 needs one pass for __destruct and another to free the cycle. */
gc_collect_cycles();
gc_collect_cycles();

var_dump($w->get() === null);
?>
--EXPECT--
bool(true)
