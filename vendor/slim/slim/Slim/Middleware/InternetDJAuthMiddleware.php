<?php
/**
 * Created by PhpStorm.
 * User: michaelbordash
 * Date: 5/15/15
 * Time: 9:21 PM
 */

class InternetDJAuthMiddleware extends \Slim\Middleware {

    public function call() {

        $app = $this->app;
        $env = $app->environment();

        $authHeader = $app->request->headers->get('authorization');
        $bearerArray = explode(' ', $authHeader);
        $bearerToken = $bearerArray[1];

        $apiReq = $app->request()->getPathInfo();
        $ip = $app->request()->getIp();

        $apiKeyId = $this->validateUserKey($bearerToken, $apiReq, $ip);

        if (!$apiKeyId) {
            $res = $app->response();
            $app->status(401);
            $res->setBody('Token invalid or you\'ve exceeded your request limit.');
        }

        //$body = $app->request->post();
        //$body['apiKeyId'] = $apiKeyId;

        $env['apiKeyId'] = $apiKeyId;

        $this->next->call();
    }


    private function validateUserKey($bearerToken, $apiReq, $ip) {

        try {

            $db = new MongoClient();

            $db = $db->upsert;

            $collection = $db->apikeys;

            $findApiKey = array(
                'api_key' => $bearerToken,
                'deleted_at' => array('$eq' => null)
            );

            $updateApiKey = array(
                '$inc' => array('request_count' => 1)
            );

            $cursor = $collection->findAndModify($findApiKey, $updateApiKey);

            $apiKeyId = $cursor['_id'];
            $apiKeyReqLimit = $cursor['request_limit_day'];
            $apiKeyReqCount = $cursor['request_count'];
            $apiKeyApproved = $cursor['approved'];
            $apiKeyCreatedAt = $cursor['created_at'];

            // TODO:: calculate days since created, check average count/day is < than req limit

        } catch(Exception $e) {
            echo '{"error":{"text":'. $e->getMessage() .'}}';
            return false;
        }

        if ( !$apiKeyApproved ) {
            return false;
        }

        return ($apiKeyId);
    }
}