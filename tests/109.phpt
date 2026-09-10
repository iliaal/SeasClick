--TEST--
ClickHouse insertFromStream parser strictness: \N vs non-Nullable, quoted-empty at EOF, post-quote bytes
--EXTENSIONS--
clickhouse
--SKIPIF--
<?php require __DIR__ . "/_clickhouse.inc"; clickhouse_skip_if_no_server(); ?>
--FILE--
<?php
require __DIR__ . "/_clickhouse.inc";

$c = new ClickHouse(clickhouse_test_config());
$c->execute("CREATE DATABASE IF NOT EXISTS test");
$c->execute("DROP TABLE IF EXISTS test.crfix");
$c->execute("CREATE TABLE test.crfix (id UInt32, s String, n Nullable(String)) ENGINE=Memory");

function probe(string $label, callable $fn): void {
    try {
        $fn();
        echo "$label: NO THROW\n";
    } catch (ClickHouseException $e) {
        echo "$label: REJECTED\n";
    }
}


$mem = fopen("php://memory", "w+b");
fwrite($mem, "1\t\\N\tnote\n");  // \N targets `s` (non-Nullable String)
rewind($mem);
probe("cr001-string", fn() =>
    $c->insertFromStream("test.crfix", ["id", "s", "n"], $mem));
fclose($mem);

$c->execute("DROP TABLE IF EXISTS test.crfix2");
$c->execute("CREATE TABLE test.crfix2 (id UInt32) ENGINE=Memory");
$mem = fopen("php://memory", "w+b");
fwrite($mem, "\\N\n");
rewind($mem);
probe("cr001-numeric", fn() =>
    $c->insertFromStream("test.crfix2", ["id"], $mem));
fclose($mem);

$mem = fopen("php://memory", "w+b");
fwrite($mem, "10\tplain\t\\N\n");
rewind($mem);
$n = $c->insertFromStream("test.crfix", ["id", "s", "n"], $mem);
fclose($mem);
echo "cr001-nullable-ok rows: $n\n";

// Quoted empty cells need to survive EOF even when both parser buffers are empty.

$c->execute("DROP TABLE IF EXISTS test.crfix_csv");
$c->execute("CREATE TABLE test.crfix_csv (s String) ENGINE=Memory");
$mem = fopen("php://memory", "w+b");
fwrite($mem, "\"\"");  // two bytes: open-quote close-quote, NO newline
rewind($mem);
$n = $c->insertFromStream("test.crfix_csv", ["s"], $mem, "CSV");
fclose($mem);
$row = $c->select("SELECT s, length(s) AS l FROM test.crfix_csv")[0];
echo "cr002-quoted-empty rows: $n s='{$row['s']}' len={$row['l']}\n";

$c->execute("TRUNCATE TABLE test.crfix_csv");
$mem = fopen("php://memory", "w+b");
fwrite($mem, "\"value\"");
rewind($mem);
$n = $c->insertFromStream("test.crfix_csv", ["s"], $mem, "CSV");
fclose($mem);
$row = $c->select("SELECT s, length(s) AS l FROM test.crfix_csv")[0];
echo "cr002-quoted-value rows: $n s='{$row['s']}' len={$row['l']}\n";


$mem = fopen("php://memory", "w+b");
fwrite($mem, "\"ab\"c\n");
rewind($mem);
probe("cr003-bytes-after-quote", fn() =>
    $c->insertFromStream("test.crfix_csv", ["s"], $mem, "CSV"));
fclose($mem);

$mem = fopen("php://memory", "w+b");
fwrite($mem, "\"ab\" \n");
rewind($mem);
probe("cr003-space-after-quote", fn() =>
    $c->insertFromStream("test.crfix_csv", ["s"], $mem, "CSV"));
fclose($mem);

$c->execute("TRUNCATE TABLE test.crfix_csv");
$mem = fopen("php://memory", "w+b");
fwrite($mem, "\"a\"\"b\"\n");  // "" -> single literal "
rewind($mem);
$n = $c->insertFromStream("test.crfix_csv", ["s"], $mem, "CSV");
fclose($mem);
$last = $c->select("SELECT s FROM test.crfix_csv", [], ClickHouse::FETCH_ONE);
echo "cr003-doubled-quote rows: $n s='$last'\n";

$cnt_main = $c->select("SELECT count() FROM test.crfix", [], ClickHouse::FETCH_ONE);
$cnt_csv  = $c->select("SELECT count() FROM test.crfix_csv", [], ClickHouse::FETCH_ONE);
echo "main rows: $cnt_main, csv rows: $cnt_csv\n";

$c->execute("DROP TABLE test.crfix");
$c->execute("DROP TABLE test.crfix2");
$c->execute("DROP TABLE test.crfix_csv");
?>
--EXPECT--
cr001-string: REJECTED
cr001-numeric: REJECTED
cr001-nullable-ok rows: 1
cr002-quoted-empty rows: 1 s='' len=0
cr002-quoted-value rows: 1 s='value' len=5
cr003-bytes-after-quote: REJECTED
cr003-space-after-quote: REJECTED
cr003-doubled-quote rows: 1 s='a"b'
main rows: 1, csv rows: 1
