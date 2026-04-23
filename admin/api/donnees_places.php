<?php
/** Liste des places — table `place_parking` (colonnes détectées automatiquement). */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/place_parking_map.php';

try {
    $db = pfa_db();
    $map = pfa_place_parking_map($db);
    if ($map === null) {
        echo json_encode(['success' => true, 'places' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $i = pfa_pp_tick($map['id']);
    $st = pfa_pp_tick($map['statut']);
    $nv = pfa_pp_tick($map['niveau']);
    $zn = pfa_pp_tick($map['zone']);
    $sql = "SELECT $i AS id_place, $st AS statut, $nv AS niveau, $zn AS zone
            FROM place_parking ORDER BY $i ASC";
    $r = $db->query($sql);
    if (!$r) {
        throw new Exception($db->error ?: 'Requête invalide');
    }
    $rows = [];
    while ($row = $r->fetch_assoc()) {
        $rows[] = [
            'id_place' => (int) $row['id_place'],
            'statut' => $row['statut'],
            'niveau' => $row['niveau'],
            'zone' => $row['zone'],
        ];
    }
    echo json_encode(['success' => true, 'places' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
