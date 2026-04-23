<?php
/**
 * Diagnostic OCR : Python détecté, imports EasyOCR, script plaque présent.
 * Ouvrir dans le navigateur : .../admin/api/ocr_status.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/plaque_helper.php';

$py = pfa_python_binary();
$prefix = pfa_python_shell_prefix($py);
$script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'parking' . DIRECTORY_SEPARATOR . 'detect_plaque_one.py';
$selftest = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'parking' . DIRECTORY_SEPARATOR . 'ocr_selftest.py';

$out = [];
$out['python_command'] = $py;
$out['python_file_exists'] = is_file($py);
$out['detect_script_exists'] = is_file($script);

$verCmd = $prefix . ' --version 2>&1';
$out['python_version'] = trim((string) shell_exec($verCmd));

$stCmd = $prefix . ' ' . escapeshellarg($selftest) . ' 2>&1';
$stRaw = shell_exec($stCmd);
$out['selftest_raw'] = is_string($stRaw) ? trim($stRaw) : '';
$stJson = json_decode((string) $stRaw, true);
$out['easyocr_import_ok'] = is_array($stJson) && !empty($stJson['ok']);

$out['dossiers'] = [
    'entree' => is_dir(pfa_parking_dir('entree')),
    'sortie' => is_dir(pfa_parking_dir('sortie')),
    'processed' => is_dir(pfa_parking_dir('processed')),
    'echec_ocr' => is_dir(pfa_parking_dir('echec_ocr')),
];

$out['pret'] = $out['detect_script_exists'] && $out['easyocr_import_ok'] && $out['dossiers']['entree'];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
