--TEST--
Check for ClickHouse extension presence
--EXTENSIONS--
clickhouse
--FILE--
<?php 
echo "ClickHouse extension is available";
?>
--EXPECT--
ClickHouse extension is available
