<?php
session_start(); // MUST be first


header("Content-Type: application/json");

require_once 'routes.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

route($method, $uri);