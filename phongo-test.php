<?php
if(defined("STDOUT") && function_exists("posix_isatty") && posix_isatty(STDOUT)) {
    define("PRETTY", 1);
    define("RETURN_OR_NEWLINE", "\r");
} else {
    define("PRETTY", 0);
    define("RETURN_OR_NEWLINE", "\n");
}

function testing($desc, $count = false) {
    static $i = 1;

    if ($count) {
        return $i;
    }

    printf("TEST#%3d [%'.-60.60s] ", $i++, $desc);
}
function ok($getstats = false) {
    static $ok = 0;

    if ($getstats) {
        return $ok;
    }
    $ok++;

    echo ".. OK", RETURN_OR_NEWLINE;

    return true;
}
function fail($errmsg, $getstats = false) {
    static $fail = 0;

    if ($getstats) {
        return $fail;
    }
    $fail++;

    echo RETURN_OR_NEWLINE;
    printf("TEST#%3d [%'.-60.60s] ", testing(null, true)-1, "FAILED: $errmsg");
    echo "NOTOK", RETURN_OR_NEWLINE;
    return false;
}
function done() {
    echo str_repeat("-", 77), "\n";
}
function summary() {
    $ok = ok(true);
    $fail = fail(null, true);

    $total = $ok+$fail;

    printf("SUCCESS: [%'.66d]\n", $ok);
    printf("FAILURE: [%'.66d]\n", $fail);
    echo str_repeat("-", 77), "\n";
    printf("TOTAL  : [%'.66d]\n", $total);
}

function GODS_1_part_1($testcase, $mm) {
    testing($testcase->description);
    $batch = new MongoDB\WriteBatch(true);
    $batch->insert($testcase->expect->data->documents);
    $response = $mm->executeWriteBatch($testcase->expect->data->insert, $batch);

    return ok();
}
function GODS_1_part_2($testcase, $mm) {
    testing($testcase->description);
    $query = new MongoDB\Query($testcase->expect->data, array(), 0, 0, 1);
    $retval = $mm->executeQuery($testcase->expect->headers->fullCollectionName, $query);

    foreach($retval as $n => $record) {
        if ($record["ok"]) {
            return ok();
        } else {
            throw new Exception($record["errmsg"]);
        }
    }
}

$mm = new MongoDB\Manager("mongodb://localhost:8000");


$retval = $mm->executeCommand("dbname.collname", new MongoDB\Command(["GODS" => 1]));
$tests = $retval->getResponseDocument()["tests"];
$results = array(0 => 0, 1 => 0);
foreach($tests as $testcase) {
    $funcname = $testcase->id;
    if (function_exists($funcname)) {
        try {
            $retval = call_user_func_array($funcname, array($testcase, $mm));
        } catch(Exception $e) {
            fail($e->getMessage());
        }
    } else {
        echo "Cannot find testfunction for $funcname\n";
        exit;
    }
}
done();
summary();


