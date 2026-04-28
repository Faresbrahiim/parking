<?php
/** Liste des véhicules — table `vehicule` (MCD). */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $db = pfa_db();
    $r = $db->query(
        'SELECT id_vehicule, matricule, marque, type FROM vehicule ORDER BY id_vehicule DESC'
    );
    if (!$r) {
        throw new Exception($db->error ?: 'Requête invalide');
    }
    $rows = [];
    while ($row = $r->fetch_assoc()) {
        $rows[] = [
            'id_vehicule' => (int) $row['id_vehicule'],
            'matricule' => $row['matricule'],
            'marque' => $row['marque'],
            'type' => $row['type'],
        ];
    }
    echo json_encode(['success' => true, 'vehicules' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
