<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

// Create a new Request object
$request = Request::createFromGlobals();

$request->headers->addCacheControlDirective('max-age', '3600');
$request->headers->addCacheControlDirective('public');

echo $request->headers->get('Cache-Control');

echo ("\n");

$request->headers->removeCacheControlDirective('max-age');

echo $request->headers->get('Cache-Control');


//// Example 1: Retrieving Query Parameters
//$request = Request::createFromGlobals();
//
//// Retrieve a query parameter named "id"
//$id = $request->query->get('id');
//
//// Check if a query parameter named "sort" exists
//if ($request->query->has('sort')) {
//    // ...
//}
//
//// Example 2: Retrieving POST Data
//$request = Request::createFromGlobals();
//
//// Retrieve a POST parameter named "username"
//$username = $request->request->get('username');
//
//// Check if a POST parameter named "password" exists
//if ($request->request->has('password')) {
//    // ...
//}

//// Example 3: Retrieving Uploaded Files
//$request = Request::createFromGlobals();
//
//// Retrieve an uploaded file named "avatar"
//$avatarFile = $request->files->get('avatar');
//
//// Check if a file was uploaded with the request
//if ($request->files->count() > 0) {
//    // ...
//}

// Example 4: Retrieving Headers
$request = Request::createFromGlobals();

echo($request->headers);

//// Retrieve the value of the "User-Agent" header
//$userAgent = $request->headers->get('User-Agent');
//
//// Check if a "Authorization" header exists
//if ($request->headers->has('Authorization')) {
//    // ...
//}

//// Example 5: Retrieving Server Information
//$request = Request::createFromGlobals();
//
//// Retrieve the request URI
//$uri = $request->server->get('REQUEST_URI');
//
//// Retrieve the IP address of the client
//$clientIp = $request->server->get('REMOTE_ADDR');
