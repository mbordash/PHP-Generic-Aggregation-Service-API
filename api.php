<?php 

require 'vendor/autoload.php';
require 'global.php';
require_once('vendor/slim/slim/Slim/Middleware/InternetDJAuthMiddleware.php');

$app = new \Slim\Slim();
$env = $app->environment();
$app->add(new \InternetDJAuthMiddleware());

// supporting classes & functions
class ResourceNotFoundException extends Exception {}

// put metric upsert KeyMetricsDaily
// apiKeyId, daydate, scope, key, count

$app->put('/event/inc/:scope/:key', function ($incScope, $incKey) {
    global $env;

    $apiKeyId = $env['apiKeyId'];

    $db = new MongoClient();

    // Select the store DB
    $db = $db->DayScopeKeyMetrics;

    // Select a collection
    $collection = $db->$apiKeyId;

    // Find, Modify, Upsert

    $dt = new DateTime(date('Y-m-d'), new DateTimeZone('UTC'));
    $ts = $dt->getTimestamp();
    $today = new MongoDate($ts);

    $findApiKey = array(
        'created_on' => $today,
        'scope' => $incScope,
        'key' => $incKey
    );

    $updateApiKey = array(
        '$inc' => array('count' => 1)
    );

    $updateOptions = array(
        'upsert' => true
    );

    $collection->findAndModify($findApiKey, $updateApiKey, null, $updateOptions);
});

$app->put('/event/dec/:scope/:key', function ($incScope, $incKey) {
    global $env;

    $apiKeyId = $env['apiKeyId'];

    $db = new MongoClient();

    // Select the store DB
    $db = $db->DayScopeKeyMetrics;

    // Select a collection
    $collection = $db->$apiKeyId;

    // Find, Modify, Upsert

    $dt = new DateTime(date('Y-m-d'), new DateTimeZone('UTC'));
    $ts = $dt->getTimestamp();
    $today = new MongoDate($ts);

    $findApiKey = array(
        'created_on' => $today,
        'scope' => $incScope,
        'key' => $incKey
    );

    $updateApiKey = array(
        '$inc' => array('count' => -1)
    );

    $updateOptions = array(
        'upsert' => true
    );

    $collection->findAndModify($findApiKey, $updateApiKey, null, $updateOptions);
});

$app->put('/event/set/:scope/:key/:val/:date', function ($incScope, $incKey, $incVal, $incDate) {
    global $env;

    $apiKeyId = $env['apiKeyId'];
    $incVal = (int)$incVal;

    $db = new MongoClient();

    // Select the store DB
    $db = $db->DayScopeKeyMetrics;

    // Select a collection
    $collection = $db->$apiKeyId;

    // Find, Modify, Upsert

    $findApiKey = array(
        'created_on' => new MongoDate(strtotime($incDate)),
        'scope' => $incScope,
        'key' => $incKey
    );

    $updateApiKey = array(
        'created_on' => new MongoDate(strtotime($incDate)),
        'scope' => $incScope,
        'key' => $incKey,
        'count' => $incVal
    );

    $updateOptions = array(
        'upsert' => true
    );

    $collection->findAndModify($findApiKey, $updateApiKey, null, $updateOptions);
});

// get metrics
$app->get('/event/count/:scope(/:start)(/:end)(/:key)', function ($incScope, $incStart = '', $incEnd = '', $incKey = null) {
    global $env;

    $apiKeyId = $env['apiKeyId'];

    $db = new MongoClient();
    $db = $db->DayScopeKeyMetrics;
    $collection = $db->$apiKeyId;

    $countQuery = array(
        'scope' => $incScope
    );

    if (!empty(trim($incKey))) {
        $countQuery['key'] = $incKey;
    }

    //$incStart = new MongoDate(strtotime("2010-01-15 00:00:00"));
    //$incEnd = new MongoDate(strtotime("2016-01-15 00:00:00"));

    if ($incStart && $incEnd) {

        $incStart = new MongoDate(strtotime($incStart));
        $incEnd = new MongoDate(strtotime($incEnd));

        $dateRange = array(
            '$gt' => $incStart,
            '$lte' => $incEnd
        );

        $countQuery['created_on'] = $dateRange;
    }

    $countFields = array(
        'count' => true
    );

//    var_dump($countQuery);

    (int)$totalCount = 0;
    $cursor = $collection->find($countQuery,$countFields);
    foreach ($cursor as $doc) {
        $totalCount = $totalCount + (int)$doc['count'];
    }
    echo jsonpWrap('{"count": ' . json_encode($totalCount) . '}');
});

// get query result
$app->get('/event/query/:inputOperator/:inputOperand', function ($inputOperator, $inputOperand) {
    global $env;

    $apiKeyId = $env['apiKeyId'];

    $queryOperand = (int)$inputOperand;

    switch ($inputOperator) {

        case "lt":
            $queryWhere = array('count' => array('$lt' => $queryOperand));
            break;
        case "lte":
            $queryWhere = array('count' => array('$lte' => $queryOperand));
            break;
        case "gt":
            $queryWhere = array('count' => array('$gt' => $queryOperand));
            break;
        case "gte":
            $queryWhere = array('count' => array('$gte' => $queryOperand));
            break;
        default:
            exit();
    }

    $db = new MongoClient();
    $db = $db->DayScopeKeyMetrics;
    $collection = $db->$apiKeyId;

    $queryFields = array(
        'scope' => true,
        'key' => true,
        'count' => true,
        'created_on' => true
    );

    $resultsArray = array();

    $cursor = $collection->find($queryWhere,$queryFields);
    foreach ($cursor as $doc) {
        $temp = array(
            'scope' => $doc['scope'],
            'key' => $doc['key'],
            'count' => $doc['count'],
            'created_on' => date('Y-m-d H:i:s', $doc['created_on']->sec)
        );
        array_push($resultsArray, $temp);
    }
    if (!$resultsArray) {
        $resultsArray = "nothing found matching your query";
    }
    echo jsonpWrap('{"results": ' . json_encode($resultsArray) . '}');

});


$app->run();