
<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\JsonResponse;

// Create a simple JSON response
$response = new JsonResponse([
    'name' => 'John',
    'age' => 30,
    'city' => 'New York'
]);

// Set custom headers
$response->headers->set('Content-Type', 'application/json');

// Add data to the response
$response->setData([
    'message' => 'Hello, world!'
]);

// Set the HTTP status code
$response->setStatusCode(200);

// Send the response back to the client
$response->send();

//
//var_dump($response->getQueryString());

//$response->setCharset('ISO-8859-1');
//
//$response->prepare($request);
//
//$response->send();
//
//$path = $request->getPathInfo();
//$name = $request->query->get('name', 'World');
//var_dump($name);
//$basePath = $request->getBasePath();
//var_dump($basePath);
//
//$request = Request::create(
//    '/a/b/c/d',
//    'GET',
//    ['name' => 'Fabien']
//);
//
//var_dump($request->getRelativeUriForPath("/a/b/c/other"));
//if (isset($map[$path])) {
//    require $map[$path];
//} else {
//    $response->setStatusCode(404);
//    $response->setContent('Not Found');
//}
//
//$response->send();