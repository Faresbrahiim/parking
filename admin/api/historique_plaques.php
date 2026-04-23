<?php
/**
 * Historique parking : lit MySQL (visites). Affiche les images avec statuts.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $db = pfa_db();

    $sql = 'SELECT id, matricule, entree_le, sortie_le, statut, source, image_path, image_path_sortie, duree_minutes, ocr_method
            FROM visites_parking
            ORDER BY id DESC
            LIMIT 50';
    $res = $db->query($sql);
    if (!$res) {
        $sql = 'SELECT id, matricule, entree_le, sortie_le, statut, source, image_path, image_path_sortie, duree_minutes
                FROM visites_parking ORDER BY id DESC LIMIT 50';
        $res = $db->query($sql);
    }
    if (!$res) {
        $sql = 'SELECT id, matricule, entree_le, sortie_le, statut FROM visites_parking ORDER BY id DESC LIMIT 50';
        $res = $db->query($sql);
    }
    if (!$res) {
        throw new Exception('Erreur requête: ' . $db->error);
    }

    $historique = [];
    while ($row = $res->fetch_assoc()) {
        $statut = $row['statut'] === 'present' ? 'Dans le parking' : 'Sorti';

        $dm = $row['duree_minutes'] ?? null;
        if ($dm !== null && $dm !== '') {
            $dm = (int) $dm;
        } else {
            $dm = null;
        }

        $item = [
            'id' => (int) $row['id'],
            'matricule' => $row['matricule'],
            'date_entree' => $row['entree_le'],
            'date_sortie' => $row['sortie_le'],
            'statut' => $statut,
            'statut_code' => $row['statut'],
            'source' => $row['source'] ?? null,
            'ocr_method' => $row['ocr_method'] ?? null,
            'duree_minutes' => $dm,
            'date' => $row['sortie_le'] ?: $row['entree_le'],
            'action' => $statut,
        ];

        if (!empty($row['image_path'])) {
            $item['image_entree'] = 'parking/processed/' . $row['image_path'];
        }
        if (!empty($row['image_path_sortie'])) {
            $item['image_sortie'] = 'parking/processed/' . $row['image_path_sortie'];
        }

        $historique[] = $item;
    }

    echo json_encode($historique, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
