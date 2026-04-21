<?php

$host = "db";        // IMPORTANT: docker service name
$db   = "parking_db";
$user = "root";
$pass = "root";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode([
        "error" => "DB connection failed",
        "details" => $conn->connect_error
    ]));
}