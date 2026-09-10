--TEST--
DR-005: a clean server error (bad SQL) does not reconnect and drop session state
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

// Clean ServerException responses must preserve session tables and settings.

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");

$c->execute("CREATE TEMPORARY TABLE dr005_tmp (n UInt8)");
$c->execute("INSERT INTO dr005_tmp VALUES (7)");

try { $c->execute("SELECT * FROM test.dr005_no_such_table_xyz"); echo "execute bad: NO THROW\n"; }
catch (ClickHouseException $e) { echo "execute bad: threw\n"; }

try { $c->select("SELECT bad_col_xyz FROM numbers(1)"); echo "select bad: NO THROW\n"; }
catch (ClickHouseException $e) { echo "select bad: threw\n"; }

try {
    $r = $c->select("SELECT n FROM dr005_tmp");
    echo "temp table survives: ", json_encode(array_column($r, 'n')), "\n";
} catch (ClickHouseException $e) {
    echo "temp table GONE (reconnected)\n";
}

$r = $c->select("SELECT 1 AS ok");
echo "handle usable: ", $r[0]['ok'], "\n";
?>
--EXPECT--
execute bad: threw
select bad: threw
temp table survives: [7]
handle usable: 1
