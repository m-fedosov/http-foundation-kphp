<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\Response;

$request = Request::createFromGlobals();
//$response = new Response();
//
//$map = [
//    '/hello' => __DIR__.'/hello.php',
//    '/bye'   => __DIR__.'/bye.php',
//];
//
$path = $request->getPathInfo();
var_dump($path);
$name = $request->query->get('name', 'World');
var_dump($name);

$request = Request::create(
    '/hello-world/enter',
    'GET',
    ['name' => 'Fabien']
);

$path = $request->getPathInfo();
var_dump($path);
$name = $request->query->get('name', 'World');
var_dump($name);
$basePath = $request->getBasePath();
var_dump($basePath);

$request = Request::create(
    '/a/b/c/d',
    'GET',
    ['name' => 'Fabien']
);

var_dump($request->getRelativeUriForPath("/a/b/c/other"));
//if (isset($map[$path])) {
//    require $map[$path];
//} else {
//    $response->setStatusCode(404);
//    $response->setContent('Not Found');
//}
//
//$response->send();