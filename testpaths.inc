<?php // vim: ft=php

$RESPONSES = array(
    "isMaster" => array(
        "headers" => array(
            /* std header */
            "messageLength"  => 0,
            "requestID"      => 0,
            "responseTo"     => 0,
            "opCode"         => 0,

            /* OP_REPLY */
            "responseFlags"  => 0,
            "cursorID"       => 0,
            "startingFrom"   => 0,
            "numberReturned" => 1,
        ),
        "content" => <<< 'EJSON'
{
        "ismaster" : true,
        "maxBsonObjectSize" : 16777216,
        "maxMessageSizeBytes" : 48000000,
        "maxWriteBatchSize" : 1000,
        "localTime" : { "$date" : 1408036924521 },
        "maxWireVersion" : 2,
        "minWireVersion" : 0,
        "ok" : 1
}
EJSON
    ),
);


