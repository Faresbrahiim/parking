<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://127.0.0.1:5500");  
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");    

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
header("Content-Type: application/json");

require_once 'routes.php';

$method = $_SERVER['REQUEST_METHOD'];

// Strip the base path so /parking/user/backend/src/places becomes /places
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = dirname($_SERVER['SCRIPT_NAME']); // /parking/user/backend/src
$uri = substr($uri, strlen($scriptDir));
$uri = '/' . ltrim($uri, '/');
if ($uri === '/') $uri = '';

route($method, $uri);