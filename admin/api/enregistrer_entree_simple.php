<?php
/**
 * API simple pour enregistrer les entrées
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
    
    // Enregistrer l'entrée avec date et heure actuelles
    $stmt = $db->prepare(
        'INSERT INTO visites_parking (matricule, entree_le, statut, source, image_path) 
         VALUES (?, NOW(), "present", "entree_photo", ?)'
    );
    $stmt->bind_param('ss', $mat, $image_path);
    $stmt->execute();
    
    echo json_encode([
        'ok' => true,
        'message' => 'Entrée enregistrée avec succès',
        'matricule' => $mat,
        'date_entree' => date('Y-m-d H:i:s'),
        'image_path' => $image_path
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur base: ' . $e->getMessage()]);
}
