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

$app->put('/aggr/inc/:scope/:key', function ($incScope, $incKey) {
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

$app->put('/aggr/dec/:scope/:key', function ($incScope, $incKey) {
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

$app->put('/aggr/set/:scope/:key/:val/:date', function ($incScope, $incKey, $incVal, $incDate) {
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
$app->get('/aggr/count/:scope(/:start)(/:end)(/:key)', function ($incScope, $incStart = '', $incEnd = '', $incKey = null) {
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
    echo '{"count": ' . jsonpWrap(json_encode($totalCount)) . '}';
});


$app->run();