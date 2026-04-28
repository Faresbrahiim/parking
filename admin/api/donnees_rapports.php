<?php
/**
 * Rapports + liaison `consulter` (admins) et `archiver` (historique).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $db = pfa_db();

    $tr = $db->query("SHOW TABLES LIKE 'rapport'");
    $hasR = $tr && $tr->num_rows > 0;
    if (!$hasR) {
        echo json_encode(['success' => true, 'rapports' => [], 'message' => 'Table rapport absente.']);
        exit;
    }

    $tc = $db->query("SHOW TABLES LIKE 'consulter'");
    $ta = $db->query("SHOW TABLES LIKE 'archiver'");
    $hasCon = $tc && $tc->num_rows > 0;
    $hasArc = $ta && $ta->num_rows > 0;

    if ($hasCon && $hasArc) {
        $sql = 'SELECT r.id_rapport, r.type_rapport, r.date_generation,
                (SELECT COUNT(*) FROM consulter c WHERE c.id_rapport = r.id_rapport) AS nb_consultations,
                (SELECT COUNT(*) FROM archiver a WHERE a.id_rapport = r.id_rapport) AS nb_lignes_historique
                FROM rapport r
                ORDER BY r.date_generation DESC, r.id_rapport DESC
                LIMIT 200';
    } elseif ($hasCon) {
        $sql = 'SELECT r.id_rapport, r.type_rapport, r.date_generation,
                (SELECT COUNT(*) FROM consulter c WHERE c.id_rapport = r.id_rapport) AS nb_consultations,
                0 AS nb_lignes_historique
                FROM rapport r
                ORDER BY r.date_generation DESC, r.id_rapport DESC
                LIMIT 200';
    } elseif ($hasArc) {
        $sql = 'SELECT r.id_rapport, r.type_rapport, r.date_generation,
                0 AS nb_consultations,
                (SELECT COUNT(*) FROM archiver a WHERE a.id_rapport = r.id_rapport) AS nb_lignes_historique
                FROM rapport r
                ORDER BY r.date_generation DESC, r.id_rapport DESC
                LIMIT 200';
    } else {
        $sql = 'SELECT id_rapport, type_rapport, date_generation, 0 AS nb_consultations, 0 AS nb_lignes_historique
                FROM rapport
                ORDER BY date_generation DESC, id_rapport DESC
                LIMIT 200';
    }

    $res = $db->query($sql);
    if (!$res) {
        throw new Exception($db->error ?: 'Requête invalide');
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id_rapport' => (int) $row['id_rapport'],
            'type_rapport' => $row['type_rapport'],
            'date_generation' => $row['date_generation'],
            'nb_consultations' => (int) $row['nb_consultations'],
            'nb_lignes_historique' => (int) $row['nb_lignes_historique'],
        ];
    }

    echo json_encode(['success' => true, 'rapports' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
