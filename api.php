<?php 

require 'vendor/autoload.php';
require 'global.php';
require_once('vendor/slim/slim/Slim/Middleware/InternetDJAuthMiddleware.php');

$app = new \Slim\Slim();
$env = $app->environment();
$app->add(new \InternetDJAuthMiddleware());

// supporting classes & functions
class ResourceNotFoundException extends Exception {}

// start api methods

$app->put('/event/put(/:action)(/:scope)(/:key)', function () {
    global $env, $app;

    $apiKeyId = $env['apiKeyId'];

    // required params
    if (!empty($app->request()->params('action'))) {
        $inputAction = $app->request()->params('action');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"action required."}');
    }

    if (!empty($app->request()->params('scope'))) {
        $inputScope = $app->request()->params('scope');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"scope required."}');
    }

    if (!empty($app->request()->params('key'))) {
        $inputKey = $app->request()->params('key');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"key required."}');
    }

    switch ($inputAction) {
        case "inc":
            $updateApiKey = array(
                '$inc' => array('count' => 1)
            );
            break;

        case "dec":
            $updateApiKey = array(
                '$inc' => array('count' => -1)
            );
            break;

        default:
            $app->contentType('application/json');
            $app->halt(500, '{"error":"action must be inc or dec."}');
            break;
    }

    // setup db & query
    $db = new MongoClient();
    $db = $db->DayScopeKeyMetrics;
    $collection = $db->$apiKeyId;
    $collection->ensureIndex(array('created_on' => 1));
    $collection->ensureIndex(array('key' => 1));
    $collection->ensureIndex(array('scope' => 1));
    $collection->ensureIndex(array('count' => 1));

    $dt = new DateTime(date('Y-m-d'), new DateTimeZone('UTC'));
    $ts = $dt->getTimestamp();
    $today = new MongoDate($ts);

    $findApiKey = array(
        'created_on' => $today,
        'scope' => $inputScope,
        'key' => $inputKey
    );


    $updateOptions = array(
        'upsert' => true
    );

    $collection->findAndModify($findApiKey, $updateApiKey, null, $updateOptions);
});


$app->put('/event/set(/:scope)(/:key)(/:val)(/:date)', function () {
    global $env, $app;

    $apiKeyId = $env['apiKeyId'];

    // required params
    if (!empty($app->request()->params('scope'))) {
        $inputScope = $app->request()->params('scope');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"scope required."}');
    }

    if (!empty($app->request()->params('key'))) {
        $inputKey = $app->request()->params('key');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"key required."}');
    }

    if (!empty($app->request()->params('val'))) {
        $inputVal = (int)$app->request()->params('val');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"val required."}');
    }

    if (!empty($app->request()->params('date'))) {
        $inputDate = $app->request()->params('date');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"date required."}');
    }

    // setup db & query
    $db = new MongoClient();
    $db = $db->DayScopeKeyMetrics;
    $collection = $db->$apiKeyId;
    $collection->ensureIndex(array('created_on' => 1));
    $collection->ensureIndex(array('key' => 1));
    $collection->ensureIndex(array('scope' => 1));
    $collection->ensureIndex(array('count' => 1));

    // Find, Modify, Upsert

    $findApiKey = array(
        'created_on' => new MongoDate(strtotime($inputDate)),
        'scope' => $inputScope,
        'key' => $inputKey
    );

    $updateApiKey = array(
        'created_on' => new MongoDate(strtotime($inputDate)),
        'scope' => $inputScope,
        'key' => $inputKey,
        'count' => $inputVal
    );

    $updateOptions = array(
        'upsert' => true
    );

    $collection->findAndModify($findApiKey, $updateApiKey, null, $updateOptions);
});

// get metrics
$app->get('/event/count(/:scope)(/:start)(/:end)(/:key)', function () {
    global $env, $app;

    $apiKeyId = $env['apiKeyId'];
    $inputKey = null;
    $inputStart = null;
    $inputEnd = null;

    // required params
    if (!empty($app->request()->params('scope'))) {
        $inputScope = $app->request()->params('scope');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"scope required."}');
    }

    // optional params
    if (!empty($app->request()->params('start'))) {
        $inputStart = (string)$app->request()->params('start');
    }

    if (!empty($app->request()->params('end'))) {
        $inputEnd = (string)$app->request()->params('end');
    }

    if (!empty($app->request()->params('key'))) {
        $inputKey = (string)$app->request()->params('key');
    }

    // setup db & query

    $db = new MongoClient();
    $db = $db->DayScopeKeyMetrics;
    $collection = $db->$apiKeyId;

    $countQuery = array(
        'scope' => $inputScope
    );

    if (!empty(trim($inputKey))) {
        $countQuery['key'] = $inputKey;
    }

    if ($inputStart && $inputEnd) {

        $inputStart = new MongoDate(strtotime($inputStart));
        $inputEnd = new MongoDate(strtotime($inputEnd));

        $dateRange = array(
            '$gt' => $inputStart,
            '$lte' => $inputEnd
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

// get list by operator/operand
$app->get('/event/query(/:operator)(/:operand)(/:scope)(/:key)(/:group_by)(/:page)', function () {
    global $env, $app;

    $apiKeyId = $env['apiKeyId'];
    $docsPerPage = 100;

    //setup db

    $db = new MongoClient();
    $db = $db->DayScopeKeyMetrics;
    $collection = $db->$apiKeyId;

    // setup params
    $arrayInputKey = null;
    $inputPage = 0;

    // required params
    if (!empty($app->request()->params('scope'))) {
        $inputScope = $app->request()->params('scope');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"scope required."}');
    }

    if (null !== $app->request()->params('operand')) {
        $queryOperand = (int)$app->request()->params('operand');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"operand required."}');
    }

    if (!empty($app->request()->params('operator'))) {
        $queryOperator = $app->request()->params('operator');
    }  else {
        $app->contentType('application/json');
        $app->halt(500, '{"error":"operator required."}');
    }

    // optional params

    if (!empty($app->request()->params('key'))) {
        $inputKey = (string)$app->request()->params('key');
        $arrayInputKey = array('key' => $inputKey);
    }

    if (!empty($app->request()->params('group_by'))) {
        $inputGroupBy = $app->request()->params('group_by');
    } else {
        $inputGroupBy = false;
    }

    if ( !empty($app->request()->params('page') )) {
        $inputPage = (int)($docsPerPage * ($app->request()->params('page') -1));
    }

    switch ($queryOperator) {

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

    if($arrayInputKey) {
        $queryWhere = array_merge($queryWhere, $arrayInputKey);
    }

    $queryWhere = array_merge(array('scope' => $inputScope), $queryWhere);

    $queryMatch = array('$match' =>
        array(
            '$and' => array(
                array_merge($queryWhere)
            )
        )
    );

    $querySort = array( '$sort' => array( '_id' => -1) );
    $querySkip = array( '$skip' => $inputPage );
    $queryLimit = array( '$limit' => $docsPerPage);

    $resultsArray = array();

    //setup group by & execute
    if ($inputGroupBy) {
        $queryGroup = array(
            '$group' => array(
                '_id' => '$created_on',
                'count' => array( '$sum' => '$count'),
                'countKeys' => array( '$sum' => 1 )
            )
        );
        $cursor = $collection->aggregate($queryMatch, $queryGroup, $querySort, $querySkip, $queryLimit);

        foreach ((array)$cursor['result'] as $doc) {

            $temp = array(
                'count' => $doc['count'],
                'created_on' => date('Y-m-d H:i:s', $doc['_id']->sec),
                'countKeys' => $doc['countKeys']
            );

            array_push($resultsArray, $temp);

        }

    } else {
        $cursor = $collection->aggregate($queryMatch, $querySort, $querySkip, $queryLimit);

        foreach ((array)$cursor['result'] as $doc) {

            $temp = array(
                'count' => $doc['count'],
                'created_on' => date('Y-m-d H:i:s', $doc['created_on']->sec)
            );

            if (!$arrayInputKey) {
                $temp['key'] = $doc['key'];

            }
            array_push($resultsArray, $temp);
        }
    }


    if (!$resultsArray) {
        $resultsArray = "nothing found matching your query";
    }

    if($arrayInputKey) {
        $resultsLabel = '"key" : "' . $inputKey .'",';
    } else {
        $resultsLabel = '"scope" : "' . $inputScope .'",';
    }
    echo jsonpWrap('{'. $resultsLabel .'"results": ' . json_encode($resultsArray) . '}');

});


$app->run();
