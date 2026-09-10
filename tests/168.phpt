--TEST--
DR-nested-zval-leak: a throw mid-build of a nested Array/Tuple read frees the partial array
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";


$c = new ClickHouse(clickhouse_test_config());

// A Tuple nested deeper than the depth cap (32) makes convertToZval throw
// partway through the recursive read.
$expr = "1";
for ($i = 0; $i < 40; $i++) { $expr = "tuple($expr)"; }

$threw = false;
try { $c->select("SELECT $expr AS t"); }
catch (ClickHouseException $e) { $threw = true; }
echo "deep tuple threw: ", $threw ? "yes" : "no", "\n";

for ($w = 0; $w < 50; $w++) { try { $c->select("SELECT $expr AS t"); } catch (ClickHouseException $e) {} }
$base = memory_get_usage();
for ($i = 0; $i < 500; $i++) { try { $c->select("SELECT $expr AS t"); } catch (ClickHouseException $e) {} }
$growth = memory_get_usage() - $base;

// Allow allocator bookkeeping slack while detecting per-call leaks.
echo "leak-free: ", ($growth < 8192) ? "yes" : "no ($growth bytes)", "\n";
?>
--EXPECT--
deep tuple threw: yes
leak-free: yes
