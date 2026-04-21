<?php

require_once 'models/PlaceParking.php';

class PlaceController {

    public function index() {

        header("Content-Type: application/json");

        $model = new PlaceParking();
        $places = $model->getAll();

        echo json_encode([
            "status" => "success",
            "data" => $places
        ]);
    }
}