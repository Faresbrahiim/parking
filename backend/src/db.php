<?php

$host = "127.0.0.1";
$db   = "parking_db";
$user = "root";
$pass = "";
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode([
        "error" => "DB connection failed",
        "details" => $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");