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
}