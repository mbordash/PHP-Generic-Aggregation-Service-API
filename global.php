<?php

ini_set('memory_limit', '128M');
ini_set('date.timezone', 'UTC');


function jsonpWrap($jsonp) {
    global $app;

    if (($jsonCallback = $app->request()->get('callback')) !== null) {
        $jsonp = sprintf("%s(%s);", $jsonCallback, $jsonp);
        $app->response()->header('Content-type', 'application/javascript');
    }
    return $jsonp;
}