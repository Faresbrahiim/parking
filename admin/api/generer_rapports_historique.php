<?php
/**
 * Crée ou met à jour les lignes `rapport` du jour et synchronise `archiver`
 * avec les mêmes fenêtres que `dashboard_complete.php` (30 j., zones, 12 mois).
 *
 * POST uniquement. Idempotent par jour : un rapport par (type_rapport, date_generation).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Utilisez POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @return array{0: int, 1: int} [id_rapport, lignes_archiver]
 */
function pfa_sync_rapport_archiver(
    mysqli $db,
    string $typeRapport,
    string $dateGeneration,
    ?string $insertArchiverSql
): array {
    $typeRapport = substr($typeRapport, 0, 20);

    $sel = $db->prepare(
        'SELECT id_rapport FROM rapport WHERE type_rapport = ? AND date_generation = ? ORDER BY id_rapport ASC LIMIT 1'
    );
    $sel->bind_param('ss', $typeRapport, $dateGeneration);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($row) {
        $id = (int) $row['id_rapport'];
    } else {
        $ins = $db->prepare('INSERT INTO rapport (type_rapport, date_generation) VALUES (?, ?)');
        $ins->bind_param('ss', $typeRapport, $dateGeneration);
        $ins->execute();
        $id = (int) $db->insert_id;
        $ins->close();
    }

    $linked = 0;
    if ($insertArchiverSql !== null) {
        $del = $db->prepare('DELETE FROM archiver WHERE id_rapport = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        $sql = 'INSERT INTO archiver (id_historique, id_rapport) ' . $insertArchiverSql;
        $st = $db->prepare($sql);
        $st->bind_param('i', $id);
        $st->execute();
        $linked = (int) $st->affected_rows;
        $st->close();
    }

    return [$id, $linked];
}

function pfa_table_exists(mysqli $db, string $name): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    if ($safe === '' || $safe !== $name) {
        return false;
    }
    $r = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $r && $r->num_rows > 0;
}

try {
    $db = pfa_db();
    $warnings = [];

    if (!pfa_table_exists($db, 'rapport')) {
        throw new RuntimeException('Table rapport absente.');
    }
    if (!pfa_table_exists($db, 'historique')) {
        throw new RuntimeException('Table historique absente.');
    }

    $hasArchiver = pfa_table_exists($db, 'archiver');
    $hasStocker = pfa_table_exists($db, 'stocker');
    $hasPlace = pfa_table_exists($db, 'place_parking');

    $today = date('Y-m-d');
    $rapportsOut = [];

    // Entrées 30 j. (graphique occupation / historique)
    $sqlOcc = 'SELECT h.id_historique, ? FROM historique h
        WHERE h.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
    if ($hasArchiver) {
        [$id, $n] = pfa_sync_rapport_archiver($db, 'OCCUPATION_30J', $today, $sqlOcc);
        $rapportsOut[] = ['type' => 'OCCUPATION_30J', 'id_rapport' => $id, 'lignes_archiver' => $n];
    } else {
        $warnings[] = 'Table archiver absente : OCCUPATION_30J créé sans liaisons.';
        [$id,] = pfa_sync_rapport_archiver($db, 'OCCUPATION_30J', $today, null);
        $rapportsOut[] = ['type' => 'OCCUPATION_30J', 'id_rapport' => $id, 'lignes_archiver' => 0];
    }

    // Zones 30 j. (doughnut) — même jointure que dashboard_complete.php
    if ($hasArchiver && $hasStocker && $hasPlace) {
        $sqlZones = 'SELECT DISTINCT h.id_historique, ? FROM historique h
            INNER JOIN stocker s ON s.id_historique = h.id_historique
            INNER JOIN place_parking p ON p.id_place = s.id_place
            WHERE h.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        [$id, $n] = pfa_sync_rapport_archiver($db, 'ZONES_30J', $today, $sqlZones);
        $rapportsOut[] = ['type' => 'ZONES_30J', 'id_rapport' => $id, 'lignes_archiver' => $n];
    } else {
        $warnings[] = 'Zones : tables stocker ou place_parking manquantes — ZONES_30J sans archiver.';
        [$id,] = pfa_sync_rapport_archiver($db, 'ZONES_30J', $today, null);
        $rapportsOut[] = ['type' => 'ZONES_30J', 'id_rapport' => $id, 'lignes_archiver' => 0];
    }

    // Tendances 12 mois
    $sqlTrend = 'SELECT h.id_historique, ? FROM historique h
        WHERE h.date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)';
    if ($hasArchiver) {
        [$id, $n] = pfa_sync_rapport_archiver($db, 'TENDANCES_12M', $today, $sqlTrend);
        $rapportsOut[] = ['type' => 'TENDANCES_12M', 'id_rapport' => $id, 'lignes_archiver' => $n];
    } else {
        [$id,] = pfa_sync_rapport_archiver($db, 'TENDANCES_12M', $today, null);
        $rapportsOut[] = ['type' => 'TENDANCES_12M', 'id_rapport' => $id, 'lignes_archiver' => 0];
    }

    // Grille tarifaire (table revenu) : méta-rapport sans archiver (pas d’id_historique)
    [$id,] = pfa_sync_rapport_archiver($db, 'GRILLE_TARIFS', $today, null);
    $rapportsOut[] = ['type' => 'GRILLE_TARIFS', 'id_rapport' => $id, 'lignes_archiver' => 0];

    echo json_encode(
        [
            'success' => true,
            'date_generation' => $today,
            'rapports' => $rapportsOut,
            'warnings' => $warnings,
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
