<?php
/**
 * Détection matricule (Python optionnel) + repli nom de fichier / AUTO.
 * Déplacement des images traitées vers parking/processed.
 */

function pfa_parking_dir(string $sub): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'parking' . DIRECTORY_SEPARATOR . $sub;
}

function pfa_python_binary(): string
{
    $env = getenv('PFA_PYTHON');
    if ($env !== false && $env !== '') {
        return $env;
    }
    $cfgFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'parking' . DIRECTORY_SEPARATOR . 'python_exe_path.txt';
    if (is_file($cfgFile)) {
        $line = '';
        $raw = @file($cfgFile, FILE_IGNORE_NEW_LINES);
        if (is_array($raw)) {
            foreach ($raw as $row) {
                $row = trim((string) $row);
                if ($row !== '' && $row[0] !== '#') {
                    $line = $row;
                    break;
                }
            }
        }
        if ($line !== '' && is_file($line)) {
            return $line;
        }
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $local = getenv('LOCALAPPDATA');
        if ($local !== false && $local !== '') {
            foreach (['Python312', 'Python311', 'Python310'] as $dir) {
                $candidate = $local . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python'
                    . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'python.exe';
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }
        return 'python';
    }
    return 'python3';
}

/** Préfixe shell : chemin ou commande avec espaces (ex. py -3) non quoté en un seul bloc. */
function pfa_python_shell_prefix(string $py): string
{
    if (preg_match('/\s/', $py)) {
        return $py;
    }
    return escapeshellarg($py);
}

/** Si false (défaut) : seul le texte lu sur la photo (EasyOCR) est accepté — pas AUTO ni nom de fichier. */
function pfa_allow_non_ocr_matricule(): bool
{
    $v = getenv('PFA_ALLOW_NON_OCR_MATRICULE');
    if ($v === false || $v === '') {
        return false;
    }
    $v = strtolower(trim((string) $v));
    return $v === '1' || $v === 'true' || $v === 'yes';
}

/**
 * @return array{matricule: string, confidence: float, method: string, error?: string}
 */
function pfa_detect_matricule_from_image(string $absPath): array
{
    $basename = basename($absPath);
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'parking' . DIRECTORY_SEPARATOR . 'detect_plaque_one.py';
    $ocrError = null;

    if (is_file($script)) {
        $py = pfa_python_binary();
        $cmd = pfa_python_shell_prefix($py) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($absPath);
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd .= ' 2>nul';
        } else {
            $cmd .= ' 2>/dev/null';
        }
        $out = shell_exec($cmd);
        if (is_string($out) && $out !== '') {
            $j = json_decode(trim($out), true);
            if (is_array($j) && !empty($j['ok']) && !empty($j['matricule'])) {
                $m = pfa_normalize_matricule((string) $j['matricule']);
                if ($m !== '' && strlen($m) >= 2) {
                    return [
                        'matricule' => $m,
                        'confidence' => isset($j['confidence']) ? (float) $j['confidence'] : 0.85,
                        'method' => 'easyocr',
                    ];
                }
            }
            if (is_array($j) && isset($j['error'])) {
                $ocrError = (string) $j['error'];
            }
        }
    } else {
        $ocrError = 'script_detect_manquant';
    }

    if (pfa_allow_non_ocr_matricule()) {
        $fromName = pfa_matricule_from_filename($basename);
        if ($fromName !== null) {
            return [
                'matricule' => $fromName,
                'confidence' => 0.5,
                'method' => 'filename',
            ];
        }

        return [
            'matricule' => 'AUTO-' . date('Hi') . '-' . substr(md5($basename), 0, 6),
            'confidence' => 0.2,
            'method' => 'auto_generated',
        ];
    }

    return [
        'matricule' => '',
        'confidence' => 0.0,
        'method' => 'ocr_failed',
        'error' => $ocrError ?? 'ocr_vide',
    ];
}

/** Déplace une image dont la plaque n’a pas pu être lue (hors processed). */
function pfa_deplacer_echec_ocr(string $source_path): ?string
{
    $dir = pfa_parking_dir('echec_ocr');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $filename = 'echec_' . date('Ymd_His') . '_' . basename($source_path);
    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    if (rename($source_path, $dest)) {
        return $filename;
    }
    return null;
}

function pfa_normalize_matricule(string $s): string
{
    $s = trim(preg_replace('/\s+/u', '', $s));
    return $s;
}

/** Matricule si le nom du fichier ressemble à « 12345-A-12 » ou « 12345ب12 » (sans espaces). */
function pfa_matricule_from_filename(string $basename): ?string
{
    $name = pathinfo($basename, PATHINFO_FILENAME);
    $name = trim($name);
    if (strlen($name) < 4 || strlen($name) > 40) {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9_\u0600-\u06FF\-\.]+$/u', $name)) {
        return null;
    }
    if (preg_match('/^(IMG|DSC|Photo|image|WP_|Screenshot|resultat_|capture_|voiture)/i', $name)) {
        return null;
    }
    return pfa_normalize_matricule($name);
}

function pfa_deplacer_image_traitee(string $source_path, string $prefix): ?string
{
    $processed_dir = pfa_parking_dir('processed');
    if (!is_dir($processed_dir)) {
        mkdir($processed_dir, 0777, true);
    }
    $filename = $prefix . '_' . date('Ymd_His') . '_' . basename($source_path);
    $dest_path = $processed_dir . DIRECTORY_SEPARATOR . $filename;
    if (rename($source_path, $dest_path)) {
        return $filename;
    }
    return null;
}

/** Ajoute une ligne dans la table historique si elle existe et contient les colonnes attendues. */
function pfa_log_historique(mysqli $db, string $matricule, string $type_evenement, ?int $id_visite, ?int $duree_minutes): void
{
    $t = $db->query("SHOW TABLES LIKE 'historique'");
    if (!$t || $t->num_rows === 0) {
        return;
    }
    $cols = [];
    $r = $db->query('SHOW COLUMNS FROM historique');
    if (!$r) {
        return;
    }
    while ($row = $r->fetch_assoc()) {
        $cols[$row['Field']] = true;
    }
    if (!isset($cols['matricule']) || !isset($cols['type_evenement'])) {
        return;
    }

    $fields = ['`date`', '`matricule`', '`type_evenement`'];
    $placeholders = ['NOW()', '?', '?'];
    $types = 'ss';
    $params = [$matricule, $type_evenement];

    if (isset($cols['id_visite']) && $id_visite !== null) {
        $fields[] = '`id_visite`';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $id_visite;
    }
    if (isset($cols['duree_minutes']) && $duree_minutes !== null) {
        $fields[] = '`duree_minutes`';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $duree_minutes;
    }

    $sql = 'INSERT INTO historique (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}
