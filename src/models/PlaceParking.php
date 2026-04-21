<?php

require_once __DIR__ . '/../db.php';

class PlaceParking {

    public function getAll() {

        global $conn;

        $result = $conn->query("SELECT * FROM place_parking");

        $places = [];

        while ($row = $result->fetch_assoc()) {
            $places[] = $row;
        }

        return $places;
    }
    public function occupyAfterPayment($placeId) {

    global $conn;

    // 1. Check if place exists and is free
    $stmt = $conn->prepare("
        SELECT statut FROM place_parking WHERE id_place = ?
    ");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();

    $result = $stmt->get_result();
    $place = $result->fetch_assoc();

    if (!$place) {
        return ["error" => "NOT_FOUND"];
    }

    if ($place['statut'] !== 'libre') {
        return ["error" => "NOT_AVAILABLE"];
    }

    // 2. Mark as occupied
    $stmt = $conn->prepare("
        UPDATE place_parking 
        SET statut = 'occupe' 
        WHERE id_place = ?
    ");

    $stmt->bind_param("i", $placeId);
    $stmt->execute();

    return [
        "place_id" => $placeId,
        "status" => "occupe"
    ];
}
}