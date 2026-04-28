<?php
/**
 * Traite entree/ puis sortie/ en une requête (pour surveillance_auto ou cron).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/parking_folder_processor.php';

$e = pfa_process_entree_folder();
$s = pfa_process_sortie_folder();

echo json_encode([
    'ok' => true,
    'entree' => [
        'ok' => true,
        'dossier' => 'entree',
        'traitees' => $e['traitees'],
        'avertissements' => $e['avertissements'],
        'total' => count($e['traitees']),
    ],
    'sortie' => [
        'ok' => true,
        'dossier' => 'sortie',
        'traitees' => $s['traitees'],
        'avertissements' => $s['avertissements'],
        'total' => count($s['traitees']),
    ],
    'total_traitees' => count($e['traitees']) + count($s['traitees']),
], JSON_UNESCAPED_UNICODE);
