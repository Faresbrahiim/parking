<?php
/**
 * Occupation des places — alimente la page « Occupation » (résumé + tableau par zone).
 *
 * Table MySQL : place_parking (id_place, statut, niveau, zone).
 *   - Colonne Zone : libellé `zone` (GROUP BY).
 *   - Occupées : nombre de lignes dont statut = occupée (variantes : occupee, occupée…).
 *   - Libres : nombre de lignes dont statut = disponible / libre / free…
 * Totaux en tête de page : même table, agrégats globaux.
 * Réservations actives : table reservation (si présente), statuts en_attente / confirmee.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $db = pfa_db();
    $out = [
        'success' => true,
        'totals' => [
            'total' => 0,
            'disponible' => 0,
            'occupee' => 0,
            'reservee' => 0,
            'autre' => 0,
        ],
        'by_zone' => [],
        'active_reservations' => 0,
    ];

    $chk = $db->query("SHOW TABLES LIKE 'place_parking'");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $r = $db->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN LOWER(TRIM(statut)) IN ('disponible','libre','free','vide') THEN 1 ELSE 0 END) AS disponible,
            SUM(CASE WHEN LOWER(TRIM(statut)) IN ('occupee','occupée','occupe') THEN 1 ELSE 0 END) AS occupee,
            SUM(CASE WHEN LOWER(TRIM(statut)) IN ('reservee','réservée','reserve') THEN 1 ELSE 0 END) AS reservee
         FROM place_parking"
    );
    if ($r && ($row = $r->fetch_assoc())) {
        $out['totals']['total'] = (int) ($row['total'] ?? 0);
        $out['totals']['disponible'] = (int) ($row['disponible'] ?? 0);
        $out['totals']['occupee'] = (int) ($row['occupee'] ?? 0);
        $out['totals']['reservee'] = (int) ($row['reservee'] ?? 0);
        $out['totals']['autre'] = max(
            0,
            $out['totals']['total'] - $out['totals']['disponible'] - $out['totals']['occupee'] - $out['totals']['reservee']
        );
    }

    $rz = $db->query(
        "SELECT zone,
            SUM(CASE WHEN LOWER(TRIM(statut)) IN ('occupee','occupée','occupe') THEN 1 ELSE 0 END) AS occupees,
            SUM(CASE WHEN LOWER(TRIM(statut)) IN ('disponible','libre','free','vide') THEN 1 ELSE 0 END) AS libres
         FROM place_parking
         GROUP BY zone
         ORDER BY zone"
    );
    if ($rz) {
        while ($z = $rz->fetch_assoc()) {
            $out['by_zone'][] = [
                'zone' => $z['zone'],
                'occupees' => (int) $z['occupees'],
                'libres' => (int) $z['libres'],
            ];
        }
    }

    $chkR = $db->query("SHOW TABLES LIKE 'reservation'");
    if ($chkR && $chkR->num_rows > 0) {
        $rr = $db->query(
            "SELECT COUNT(*) AS n FROM reservation
             WHERE statut_reservation IN ('en_attente','confirmee')"
        );
        if ($rr && ($rw = $rr->fetch_assoc())) {
            $out['active_reservations'] = (int) ($rw['n'] ?? 0);
        }
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
