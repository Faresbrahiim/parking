<?php

require_once __DIR__ . '/controllers/AuthController.php';
require_once 'controllers/PlaceController.php';
function route($method, $uri) {

    $uri = rtrim($uri, '/');

    if ($method === 'GET' && $uri === '') {
        echo json_encode(["message" => "API is working 🚀"]);
        return;
    }

    if ($method === 'POST' && $uri === '/register') {
        $controller = new AuthController();
        $controller->register();
        return;
    }
    if ($method === 'POST' && $uri === '/login') {
    $controller = new AuthController();
    $controller->login();
    return;
    }

    if ($method === 'GET' && $uri === '/places') {
    $controller = new PlaceController();
    $controller->index();
    return;
    }

    http_response_code(404);
    echo json_encode([
        "error" => "Route not found",
        "uri" => $uri
    ]);


}