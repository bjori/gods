<?php
include "testpaths.inc";

define("MESSAGE_HEADER_LEN", 16);
define("REPLY_HEADER_LEN", 36);
define("OP_REPLY", 1);
define("OP_QUERY", 2004);

define("FENTRY", 1);
define("DMSG", 2);
define("FATAL", 6);
define("WARN", 4);
define("HDUMP", 5);
define("EJSON", 6);
define("TDESC", 7);
define("TOUT", 8);
define("OFF", 9);
define("DEBUG_LEVEL", 4);

/* Stolen from the intertubes */
function hex_dump($data)
{
    static $from = '';
    static $to = '';

    static $width = 16; # number of bytes per line

    static $pad = '.'; # padding for non-visible characters

    if ($from==='')
    {
        for ($i=0; $i<=0xFF; $i++)
        {
            $from .= chr($i);
            $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
        }
    }

    $hex = str_split(bin2hex($data), $width*2);
    $chars = str_split(strtr($data, $from, $to), $width);

    $offset = 0;
    foreach ($hex as $i => $line)
    {
        $length = $width * 3;
        $dump = sprintf("%-{$length}s", implode(' ', str_split($line,2)));
        echo sprintf('%6X',$offset).' : '.$dump . ' [' . $chars[$i] . ']'."\n";
        $offset += $width;
    }
}

function pad($txt = "") {
    $len = strlen($txt);
    $pad = 80-$len-($len ? 2 : 0);
    $sides = floor($pad / 2);
    echo str_repeat("=", $sides);
    echo $txt ? " " . $txt . " " : "";
    if ($pad % 2) {
        echo "=";
    }
    echo str_repeat("=", $sides);
    echo "\n";
}
function level2name($level) {
    switch($level) {
    case FENTRY:
        return "FENTRY";
    case DMSG:
        return "DMSG";
    case EJSON:
        return "EJSON";
    }
    return "IDK".$level;
}
function d($level, $txt) {
    if ($level >= DEBUG_LEVEL) {
        $prefix = str_repeat("\t", (OFF-1)-$level);
        switch($level) {
        case FENTRY:
        case DMSG:
            $header = $prefix . "[" . level2name($level). "] ";
            break;
        case EJSON:
        default:
            $header = "";
        }

        echo $header;
        echo str_replace("\n", "\n$prefix", $txt), "\n";
    }
}
function jd($data) {
    if (is_string($data)) {
        $data = json_encode(json_decode($data), JSON_PRETTY_PRINT);
    } else {
        $data = json_encode($data, JSON_PRETTY_PRINT);
    }
    return d(EJSON, $data);
}
function hd($d, $title = "") {
    if (HDUMP > DEBUG_LEVEL) {
        pad($title);
        hex_dump($d);
        pad();
    }
}

function td($n, $title = "") {
    if (TDESC > DEBUG_LEVEL) {
        pad("test#$n: $title");
    }
}

function read($conn, $bytes, $label = "") {
    d(FENTRY, __METHOD__);
    $consumed = 0;
    $data = "";

    $left = 3;

    do {
        $read   = array($conn);
        $write  = NULL;
        $except = NULL;

        d(DMSG, "Waiting to read $bytes");
        if (false === stream_select($read, $write, $except, 0)) {
            d("Timedout, closing connection");
            fclose($conn);
            return false;
        }

        d(DMSG, "Reading...");
        $data .= stream_get_contents($conn, $bytes-$consumed);
        $consumed += strlen($data);
        d(DMSG, "Read $consumed out of $bytes");

        if ($consumed == 0) {
            sleep(1);

            if ($left-- > 0) {
                d(TOUT, "Timing out in $left seconds");
                continue;
            }
            d(TOUT, "Timed out reading $bytes");
            break;
        }
    } while($consumed < $bytes);

    hd($data, $label);
    return $data;
}

function write($conn, $data, $label = "") {
    d(FENTRY, __METHOD__);

    $data_len = strlen($data);
    $read   = NULL;
    $write  = array($conn);
    $except = NULL;

    d(DMSG, "Waiting to write $data_len data");
    if (false === stream_select($read, $write, $except, 0)) {
        d(DMSG, "Timedout, closing connection");
        fclose($conn);
        return false;
    }

    $written = fputs($conn, $data, $data_len);
    d(DMSG, "wrote $written");

    hd($data, $label);
    return $data;
}

function makeMessageHeader($len, $reqid, $replyid, $flags, $cursorid, $starts, $total) {
    d(FENTRY, __METHOD__);
    jd(array(
        "header"=> array(
            "messageLength" => $len,
            "requestID" => $reqid,
            "responseTo" => $replyid,
            "opCode" => OP_REPLY,
        ),
        "responseFlags" => $flags,
        "cursorID" => $cursorid,
        "startingFrom" => $starts,
        "numberReturned" => $total,
    ));
    $header = pack("VVVVVV2VV", $len, $reqid, $replyid, OP_REPLY, $flags, $cursorid >> 32, $cursorid & 0xffffffff, $starts, $total);
    return $header;
}
function getQueryHeaders($data, &$currentpos = 0) {
    d(FENTRY, __METHOD__);
    /* find the end of the cstring+\0, skipping the flags(int32)*/
    $pos = strpos($data, 0x00, 4)+1;
    $str = substr($data, 0, $pos-1);

    /* get the next two int32 */
    $ints = substr($data, $pos, 8);

    $headers = unpack('Vflags/a*fullCollectionName', $str);
    $headers2 = unpack('VnumberToSkip/lnumberToReturn', $ints);

    $currentpos = $pos+8;
    $retval = array_merge($headers, $headers2);
    return $retval;
}

abstract class Server {
    abstract function getBootstrapSteps();

    function getMessageHeader() {
        d(FENTRY, __METHOD__);

        $data = read($this->conn, MESSAGE_HEADER_LEN, "MSG HEADER");
        if ($data) {
            $headers = unpack('VmessageLength/VrequestId/VresponseTo/Vopcode', $data);
            return $headers;
        }
    }

    function getOpcode() {
        d(FENTRY, __METHOD__);
        echo "\n";
        pad("PARSING OPCODE");

        $msgheaders = $this->getMessageHeader();
        if (!$msgheaders) {
            return false;
        }
        $data = read($this->conn, $msgheaders["messageLength"]-MESSAGE_HEADER_LEN, "OPCODE");

        switch($msgheaders["opcode"]) {
            case OP_QUERY:
                $opcode = getQueryHeaders($data, $pos);
                /* inject the msgheaders at the beginning of the assoc array */
                $opcode = array_merge(array("header" => $msgheaders), $opcode);

                $querydoc = substr($data, $pos);
                $opcode["document"] = BSON\toArray($querydoc);
                break;
            default:
                var_dump($msgheaders);
        }

        jd($opcode);
        return $opcode;
    }
}

class StandaloneServer extends Server {
    protected $conn;

    function __construct($conn) {
        $this->conn = $conn;
    }

    function getBootstrapSteps() {
        return 2;
    }
    function executeOpcode($opcode) {
        switch($opcode["header"]["opcode"]) {
        case OP_QUERY:
            $response = $this->opQuery($opcode);
            return $this->opReply($opcode, $response);
        }
    }
    function opQuery($opcode) {
        pad("EXECUTE REQUEST");
        global $RESPONSES;

        $data = $opcode["document"];
        jd($data);
        $response = $RESPONSES[BSON\toJSON(BSON\fromArray($data))];

        return $response;
    }
    function opReply($opcode, $reply) {
        d(FENTRY, __METHOD__);

        $bson = BSON\fromJSON($reply["content"]);
        $bson_len = strlen($bson);

        echo "\n";
        pad("SENDING OP_REPLY");
        $bin = makeMessageHeader($bson_len+REPLY_HEADER_LEN, 42, $opcode["header"]["requestId"], 0, 0, 0, 1);
        jd($reply["content"]);
        write($this->conn, $bin, "HEADER");
        write($this->conn, $bson, "BSON REPLY");

        return $reply["content"];
    }
    function bootstrapped() {
        return true;
    }

    function getTestSuite() {
        pad("Fetching TEST SUITE");
        $opcode = $this->getOpcode();
        $result = $this->executeOpcode($opcode);

        return $result;
    }
}
function matcheq($supposed, $got, &$errmsg = "") {
    if (fnmatch($supposed, $got)) {
        return true;
    }

    $errmsg = "'$supposed' != '$got'";
    return false;
}
function matchOpcodeWithTest($opcode, $test) {
    $headers = $test->expect->headers;

    if (!matcheq($headers->fullCollectionName, $opcode["fullCollectionName"], $errmsg)) {
        return "fullCollectionName: " . $errmsg;
    }
    if (!matcheq($headers->numberToReturn, $opcode["numberToReturn"], $errmsg)) {
        return "numberToReturn: " . $errmsg;
    }
}
function ok($server, $responseId) {
$str = <<< 'EJSON'
{
    "ok" : 1
}
EJSON;
    $server->opReply(array("header" => array("requestId" => $responseId)), array("content" => $str));
}
function fail($server, $responseId, $errmsg) {
$str = <<< EJSON
{
    "errmsg": "$errmsg",
    "ok" : 0
}
EJSON;
    $server->opReply(array("header" => array("requestId" => $responseId)), array("content" => $str));
}
function runTest($n, $test, Server $server) {
    td($n, $test->description);
    $opcode = $server->getOpcode();
    if (!$opcode) {
        echo "Couldn't get opcode.. cancelling\n";
        return false;
    }
    if ($errmsg = matchOpcodeWithTest($opcode, $test)) {
        return fail($server, $opcode["header"]["requestId"], $errmsg);
    } else {
        return ok($server, $opcode["header"]["requestId"]);
    }
}
function handleConnection($conn) {
    d(FENTRY, __METHOD__);

    $standalone = new StandaloneServer($conn);
    if (!bootstrap($standalone)) {
        echo "This guy failed.\n";
    }
    $suite = $standalone->getTestSuite();
    $suite = BSON\toArray(BSON\fromJSON($suite));
    foreach($suite["tests"] as $n => $test) {
        runTest($n, $test, $standalone);
    }
}
function bootstrap(Server $server) {
    $steps = $server->getBootstrapSteps();
    for($i=0; $i<$steps; $i++) {
        $opcode = $server->getOpcode();
        if ($opcode) {
            $retval = $server->executeOpcode($opcode);
        } else {
            echo "Couldn't find opcode..\n";
            break;
        }
    }
    if (!$server->bootstrapped()) {
        return false;
    }
    return true;
}

$socket = stream_socket_server("tcp://0.0.0.0:8000", $errno, $errstr);

if (!$socket) {
    echo "$errstr ($errno)\n";
    exit;
}

while ($conn = stream_socket_accept($socket)) {
    handleConnection($conn);
    echo "Closing connection\n";
    fclose($conn);
}

fclose($socket);

