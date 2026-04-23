<?php
/**
 * API simple pour enregistrer les sorties
 * POST JSON : { "matricule": "1234-A-45", "image_path": "/path/to/image.jpg" }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST uniquement']);
    exit;
}

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON invalide']);
    exit;
}

$mat = isset($data['matricule']) ? trim((string) $data['matricule']) : '';
$image_path = isset($data['image_path']) ? trim((string) $data['image_path']) : '';

if (empty($mat)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Matricule requis']);
    exit;
}

try {
    $db = pfa_db();

    $stmt = $db->prepare(
        'UPDATE visites_parking v
         INNER JOIN (
             SELECT id FROM visites_parking
             WHERE matricule = ? AND statut = \'present\'
             ORDER BY entree_le DESC LIMIT 1
         ) t ON v.id = t.id
         SET v.sortie_le = NOW(),
             v.statut = \'sorti\',
             v.image_path_sortie = ?,
             v.source = IFNULL(v.source, \'sortie_photo\'),
             v.duree_minutes = TIMESTAMPDIFF(MINUTE, v.entree_le, NOW())'
    );
    $stmt->bind_param('ss', $mat, $image_path);
    $stmt->execute();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Sortie enregistrée avec succès',
        'matricule' => $mat,
        'date_sortie' => date('Y-m-d H:i:s'),
        'image_path' => $image_path
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur base: ' . $e->getMessage()]);
}
