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

    
    public function payAndOccupy() {

    header("Content-Type: application/json");

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['place_id'], $data['user_id'], $data['amount'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields"]);
        return;
    }

    // --> Here you will later integrate real payment gatewayn use stripe api or something

    $model = new PlaceParking();
    $result = $model->occupyAfterPayment($data['place_id']);

    if (isset($result["error"])) {

        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Place not available or not found"
        ]);
        return;
    }

    http_response_code(200);

    echo json_encode([
        "status" => "success",
        "message" => "Payment successful, place occupied",
        "data" => $result
    ]);
}
}