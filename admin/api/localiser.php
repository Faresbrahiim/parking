<?php
/**
 * Localisation : visite en cours (visites_parking) + place MCD (stocker, place_parking).
 * GET ?q=matricule
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mcd_parking_helpers.php';
require_once __DIR__ . '/place_parking_map.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if ($q === '') {
    echo json_encode([
        'success' => false,
        'error' => 'Paramètre q (matricule) requis.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = pfa_db();

    $tv = $db->query("SHOW TABLES LIKE 'visites_parking'");
    if (!$tv || $tv->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Table visites_parking absente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $db->prepare(
        'SELECT id, matricule, entree_le, statut, id_historique_mcd
         FROM visites_parking
         WHERE matricule = ?
         ORDER BY entree_le DESC
         LIMIT 1'
    );
    $st->bind_param('s', $q);
    $st->execute();
    $vis = $st->get_result()->fetch_assoc();
    $st->close();

    $response = [
        'success' => true,
        'query' => $q,
        'in_parking' => false,
        'matricule' => $vis['matricule'] ?? $q,
        'zone' => null,
        'niveau' => null,
        'id_place' => null,
        'message' => 'Aucune visite enregistrée pour ce matricule.',
    ];

    if (!$vis) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response['matricule'] = $vis['matricule'];
    $response['last_entree'] = $vis['entree_le'];
    $response['statut_visite'] = $vis['statut'];

    if (($vis['statut'] ?? '') === 'present') {
        $response['in_parking'] = true;
        $response['message'] = 'Véhicule actuellement dans le parking.';
    } else {
        $response['message'] = 'Dernière visite terminée (sortie enregistrée).';
    }

    $idH = isset($vis['id_historique_mcd']) ? (int) $vis['id_historique_mcd'] : 0;
    $ppm = pfa_place_parking_map($db);
    if ($idH > 0 && pfa_mcd_has_table($db, 'stocker') && pfa_mcd_has_table($db, 'place_parking') && $ppm !== null) {
        $pid = pfa_pp_tick($ppm['id']);
        $zn = pfa_pp_tick($ppm['zone']);
        $nv = pfa_pp_tick($ppm['niveau']);
        $pst = pfa_pp_tick($ppm['statut']);
        $jq = $db->prepare(
            "SELECT p.$pid AS id_place, p.$zn AS zone, p.$nv AS niveau, p.$pst AS place_statut
             FROM stocker s
             INNER JOIN place_parking p ON p.$pid = s.id_place
             WHERE s.id_historique = ?
             LIMIT 1"
        );
        $jq->bind_param('i', $idH);
        $jq->execute();
        $pl = $jq->get_result()->fetch_assoc();
        $jq->close();
        if ($pl) {
            $response['id_place'] = (int) $pl['id_place'];
            $response['zone'] = $pl['zone'];
            $response['niveau'] = $pl['niveau'];
            $response['place_statut'] = $pl['place_statut'];
        } elseif ($response['in_parking']) {
            $response['message'] = 'Dans le parking — place MCD non encore attribuée (aucune place libre au moment de l’entrée ou synchro MCD désactivée).';
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
