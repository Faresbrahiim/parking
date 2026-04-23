<?php
/**
 * Scan parking/entree : détecte le matricule, INSERT visite (présent), journal historique, déplace l’image.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/parking_folder_processor.php';

$r = pfa_process_entree_folder();
echo json_encode([
    'ok' => true,
    'dossier' => 'entree',
    'traitees' => $r['traitees'],
    'avertissements' => $r['avertissements'],
    'total' => count($r['traitees']),
], JSON_UNESCAPED_UNICODE);
