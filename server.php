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
define("OFF", 7);
define("DEBUG_LEVEL", 6);

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

function pad($txt) {
    $len = strlen($txt);
    $pad = 80-$len-2;
    $sides = floor($pad / 2);
    echo str_repeat("=", $sides);
    echo " ", $txt, " ";
    if ($pad % 2) {
        echo "=";
    }
    echo str_repeat("=", $sides);
    echo "\n";
}
function hex_dump_wrap($d) {
    if (HDUMP > DEBUG_LEVEL) {
        echo str_repeat("=", 80), "\n";
        hex_dump($d);
        echo str_repeat("=", 80), "\n";
    }
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
        $prefix = str_repeat("\t", $level-1);
        echo $prefix . "[" . level2name($level). "] ";
        echo str_replace("\n", "\n$prefix", $txt), "\n";
    }
}

function read($conn, $bytes) {
    d(FENTRY, __METHOD__);
    $consumed = 0;
    $data = "";
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
        }
    } while($consumed < $bytes);

    hex_dump_wrap($data);
    return $data;
}

function write($conn, $data) {
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

    hex_dump_wrap($data);
    return $data;
}

function makeMessageHeader($len, $reqid, $replyid, $flags, $cursorid, $starts, $total) {
    d(FENTRY, __METHOD__);
    pad("CREATING PACKET");
    d(EJSON, json_encode(array(
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
    ), JSON_PRETTY_PRINT));
    $header = pack("VVVVVV2VV", $len, $reqid, $replyid, OP_REPLY, $flags, $cursorid >> 32, $cursorid & 0xffffffff, $starts, $total);
    return $header;
}
function getQueryHeaders($data, &$currentpos = 0) {
    d(FENTRY, __METHOD__);
    /* find the end of the cstring+\0, skipping the flags(int32)*/
    $pos = strpos($data, 0x00, 4)+1;
    $str = substr($data, 0, $pos);

    /* get the next two int32 */
    $ints = substr($data, $pos, 8);

    $headers = unpack('Vflags/a*fullCollectionName', $str);
    $headers2 = unpack('VnumberToSkip/lnumberToReturn', $ints);

    $currentpos = $pos+8;
    $retval = array_merge($headers, $headers2);
    d(EJSON, json_encode($retval, JSON_PRETTY_PRINT));
    return $retval;
}

function runQuery($messageh, $queryh, $v) {
    d(FENTRY, __METHOD__);
    global $RESPONSES;

    $array = BSON\toJSON($v);
    d(EJSON, $array);
    $array = BSON\toArray($v);
    var_dump($array);
    $response = $RESPONSES[key($array)];
    return $response;
}
function replyTo($conn, $in, $reply) {
    d(FENTRY, __METHOD__);

    $bson = BSON\fromJSON($reply["content"]);
    $bson_len = strlen($bson);
    d(EJSON, $reply["content"]);

    $bin = makeMessageHeader($bson_len+REPLY_HEADER_LEN, 42, $in["requestId"], 0, 0, 0, 1);
    write($conn, $bin);
    write($conn, $bson);
}

function dealWithQuery($conn, $headers) {
    d(FENTRY, __METHOD__);

    $data = read($conn, $headers["messageLength"]-MESSAGE_HEADER_LEN);
    $queryHeaders = getQueryHeaders($data, $pos);
    $v = substr($data, $pos);
    hex_dump_wrap($v);

    pad("EXECUTE REQUEST");
    $reply = runQuery($headers, $queryHeaders, $v);
    pad("REPLY TO REQUEST");
    replyTo($conn, $headers, $reply);

}
abstract class Server {
    abstract function getBootstrapSteps();

    function getMessageHeader() {
        d(FENTRY, __METHOD__);
        pad("PARSING PACKET");

        $data = read($this->conn, MESSAGE_HEADER_LEN);
        $headers = unpack('VmessageLength/VrequestId/VresponseTo/Vopcode', $data);

        d(EJSON, json_encode($headers, JSON_PRETTY_PRINT));
        return $headers;
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
    function opQuery($headers) {
        $data = read($this->conn, $headers["messageLength"]-MESSAGE_HEADER_LEN);
        $queryHeaders = getQueryHeaders($data, $pos);
        $v = substr($data, $pos);
        hex_dump_wrap($v);

        return $this->runQuery($headers, $queryHeaders, $v);
    }
    function runQuery($headers, $queryHeaders, $q) {
        pad("EXECUTE REQUEST");
        $reply = runQuery($headers, $queryHeaders, $q);
        return $reply;
    }
    function opReply($headers, $reply) {
        pad("REPLY TO REQUEST");
        return replyTo($this->conn, $headers, $reply);
    }
    function bootstrapped() {
        return true;
    }

    function getTestSuite() {
        $headers = $this->getMessageHeader();
        if ($headers["opcode"] != OP_QUERY) {
            throw InvalidArgumentException("Expected OP_QUERY when picking testsuite to run");
        }
        pad("Fetching TEST SUITE");
        $result = $this->opQuery($headers);
        sleep(10);
        if ($result) {
            $this->opReply($headers, $result);
        }
    }
}
function handleConnection($conn) {
    d(FENTRY, __METHOD__);

    $standalone = new StandaloneServer($conn);
    if (bootstrap($standalone)) {
        $suite = $standalone->getTestSuite();
        var_dump($suite);
    }
}
function bootstrap(Server $server) {
    $steps = $server->getBootstrapSteps();
    for($i=0; $i<$steps; $i++) {
        $headers = $server->getMessageHeader();
        switch($headers["opcode"]) {
        case OP_QUERY:
            $result = $server->opQuery($headers);
            if ($result) {
                $server->opReply($headers, $result);
            }
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
    fclose($conn);
}

fclose($socket);

