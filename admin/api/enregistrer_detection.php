<?php
/**
 * POST JSON : { "matricule": "120624آ6", "source": "camera" }
 * Logique : si la matricule est déjà "present" -> enregistre une sortie, sinon entrée.
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
if ($mat === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'matricule vide']);
    exit;
}

$source = isset($data['source']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $data['source']) : 'camera';
if ($source === '') {
    $source = 'camera';
}

try {
    pfa_ensure_schema();
    $db = pfa_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

$stmt = $db->prepare(
    'SELECT id FROM visites_parking WHERE matricule = ? AND statut = \'present\' ORDER BY entree_le DESC LIMIT 1'
);
$stmt->bind_param('s', $mat);
$stmt->execute();
$res = $stmt->get_result();
$open = $res ? $res->fetch_assoc() : null;
$stmt->close();

$now = date('Y-m-d H:i:s');

if ($open) {
    $id = (int) $open['id'];
    $u = $db->prepare('UPDATE visites_parking SET sortie_le = ?, statut = \'sorti\' WHERE id = ?');
    $u->bind_param('si', $now, $id);
    $u->execute();
    $u->close();
    echo json_encode([
        'ok' => true,
        'evenement' => 'sortie',
        'matricule' => $mat,
        'entree_le' => null,
        'sortie_le' => $now,
        'statut' => 'sorti',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$i = $db->prepare(
    'INSERT INTO visites_parking (matricule, entree_le, sortie_le, statut, source) VALUES (?, ?, NULL, \'present\', ?)'
);
$i->bind_param('sss', $mat, $now, $source);
$i->execute();
$i->close();

echo json_encode([
    'ok' => true,
    'evenement' => 'entree',
    'matricule' => $mat,
    'entree_le' => $now,
    'sortie_le' => null,
    'statut' => 'present',
], JSON_UNESCAPED_UNICODE);
