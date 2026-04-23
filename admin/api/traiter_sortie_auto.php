<?php
/**
 * Scan parking/sortie : détecte le matricule, clôture la visite (sorti + durée), historique, déplace l’image.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/parking_folder_processor.php';

$r = pfa_process_sortie_folder();
echo json_encode([
    'ok' => true,
    'dossier' => 'sortie',
    'traitees' => $r['traitees'],
    'avertissements' => $r['avertissements'],
    'total' => count($r['traitees']),
], JSON_UNESCAPED_UNICODE);
