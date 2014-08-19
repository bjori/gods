<?php // vim: ft=php

$RESPONSES = array(
    '{ "isMaster" : 1 }' => array(
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
    '{ "ping" : 1 }' => array(
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
    "ok" : 1
}
EJSON
    ),
    '{ "GODS" : 1 }' => array(
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
        
    "tests" : [
        {
            "description" : "Insert this document into namespace: dbtest.colltest",
            "expect": {
                "headers": { "opCode": 2004, "fullCollectionName": "dbtest.colltest", "numberToSkip": 0, "numberToReturn": 1 },
                "data": {
                    "insert": "dbtest.colltest",
                    "writeConcern": { "w": 1 },
                    "options": {"ordered": true },
                    "documents": [
                        { "_id": { "$oid": "feedbadfacebabedeaddecaf"}, "congrats": "My first successful testrun"}
                    ]
                }
            }
        },
        {
            "description" : "findById() this document in namespace: dbtest.colltest",
            "expect": {
                "headers": { "opCode": 2004, "fullCollectionName": "dbtest.colltest", "numberToSkip": 0, "numberToReturn": 1 },
                "data": {
                    "$query": { "_id": { "$oid": "feedbadfacebabedeaddecaf" } }
                }
            }
        }
    ],
    "ok" : 1
}
EJSON
    ),
);


