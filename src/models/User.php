<?php

require_once __DIR__ . '/../db.php';
class User {

  public function create($name, $email, $password) {

    global $conn;

    // check duplicate email first
    $check = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        return [
            "error" => "EMAIL_EXISTS"
        ];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $parts = explode(" ", $name);
    $nom = $parts[0];
    $prenom = $parts[1] ?? "";

    $stmt = $conn->prepare(
        "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe) VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param("ssss", $nom, $prenom, $email, $hashedPassword);

    $stmt->execute();

    return [
        "id" => $conn->insert_id,
        "nom" => $nom,
        "prenom" => $prenom,
        "email" => $email
    ];
    }
    public function findByEmail($email) {

    global $conn;

    $stmt = $conn->prepare(
        "SELECT * FROM utilisateur WHERE email = ? LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    return $result->fetch_assoc();
    }
}