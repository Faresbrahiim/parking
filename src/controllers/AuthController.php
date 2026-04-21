<?php

require_once 'models/User.php';

class AuthController {

    public function register() {

    header("Content-Type: application/json");

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['email'], $data['password'], $data['name'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields"]);
        return;
    }

    $user = new User();

    $result = $user->create(
        $data['name'],
        $data['email'],
        $data['password']
    );

    // EMAIL EXISTS
    if (isset($result["error"]) && $result["error"] === "EMAIL_EXISTS") {
        http_response_code(409);

        echo json_encode([
            "status" => "error",
            "message" => "Email already exists"
        ]);
        return;
    }

    // ANY OTHER FAILURE
    if (!$result || !isset($result["id"])) {
        http_response_code(500);

        echo json_encode([
            "error" => "Failed to create user"
        ]);
        return;
    }

    // SUCCESS
    http_response_code(201);

    echo json_encode([
        "status" => "success",
        "message" => "User created successfully",
        "user" => [
            "id" => $result["id"],
            "nom" => $result["nom"],
            "prenom" => $result["prenom"],
            "email" => $result["email"]
        ]
    ]);
}
    
    public function login() {

    header("Content-Type: application/json");

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['email'], $data['password'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields"]);
        return;
    }

    $userModel = new User();
    $user = $userModel->findByEmail($data['email']);

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid credentials"]);
        return;
    }

    // verify password
    if (!password_verify($data['password'], $user['mot_de_passe'])) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid credentials"]);
        return;
    }
   session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id_utilisateur'];
    $_SESSION['email'] = $user['email'];
    http_response_code(200);

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "user" => [
            "id" => $user['id_utilisateur'],
            "nom" => $user['nom'],
            "prenom" => $user['prenom'],
            "email" => $user['email']
        ]
    ]);
    }
}