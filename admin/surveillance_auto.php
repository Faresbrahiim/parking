<?php
/**
 * Surveillance web : appelle le traitement entree/ + sortie/, stats MySQL, rafraîchissement auto.
 */
require_once __DIR__ . '/api/db.php';

function surveillance_get_stats(): array
{
    $db = pfa_db();

    $aujourd_hui = 0;
    $r = $db->query('SELECT COUNT(*) AS n FROM visites_parking WHERE DATE(entree_le) = CURDATE()');
    if ($r) {
        $aujourd_hui = (int) ($r->fetch_assoc()['n'] ?? 0);
    }

    $presents = 0;
    $r = $db->query('SELECT COUNT(*) AS n FROM visites_parking WHERE statut = \'present\'');
    if ($r) {
        $presents = (int) ($r->fetch_assoc()['n'] ?? 0);
    }

    $dernieres = [];
    $r = $db->query('SELECT * FROM visites_parking ORDER BY entree_le DESC LIMIT 8');
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $dernieres[] = $row;
        }
    }

    return [
        'aujourd_hui' => $aujourd_hui,
        'presents' => $presents,
        'dernieres' => $dernieres,
    ];
}

function surveillance_scan_dir(string $sub): array
{
    $dir = __DIR__ . '/parking/' . $sub;
    $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    foreach (scandir($dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $file_path = $dir . '/' . $file;
        if (is_file($file_path) && @getimagesize($file_path) !== false) {
            $files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'modified' => date('H:i:s', filemtime($file_path)),
            ];
        }
    }
    return $files;
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$proto = $https ? 'https' : 'http';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$api_url = $proto . '://' . $host . $basePath . '/api/traiter_parking_auto.php';

$traitement_result = null;
$ctx = stream_context_create(['http' => ['timeout' => 120]]);
$raw = @file_get_contents($api_url, false, $ctx);
if ($raw !== false) {
    $traitement_result = json_decode($raw, true);
}

$stats = surveillance_get_stats();
$files_entree = surveillance_scan_dir('entree');
$files_sortie = surveillance_scan_dir('sortie');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance parking — entrée / sortie</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; color: #3498db; }
        .files-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .file-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .status-present { color: #27ae60; font-weight: bold; }
        .status-sorti { color: #e74c3c; font-weight: bold; }
        .refresh-info { background: #ecf0f1; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warn { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        h3 small { font-weight: normal; opacity: .85; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Surveillance parking</h1>
            <p>Déposez une photo dans <code>parking/entree</code> (entrée) ou <code>parking/sortie</code> (sortie). Les images vont dans <code>parking/processed</code>.</p>
        </div>

        <div class="refresh-info">
            <strong>Rafraîchissement automatique toutes les 5 s</strong> |
            Dernière actualisation: <?php echo htmlspecialchars(date('H:i:s'), ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php if (is_array($traitement_result) && !empty($traitement_result['ok'])): ?>
            <?php
            $te = (int) ($traitement_result['total_traitees'] ?? 0);
            $ne = (int) ($traitement_result['entree']['total'] ?? 0);
            $ns = (int) ($traitement_result['sortie']['total'] ?? 0);
            ?>
            <div class="alert <?php echo $te > 0 ? 'alert-success' : 'alert-info'; ?>">
                <strong>Traitement :</strong>
                <?php echo $te; ?> fichier(s) traité(s) cette passe
                (entrée: <?php echo $ne; ?>, sortie: <?php echo $ns; ?>).
            </div>
        <?php elseif ($traitement_result === null): ?>
            <div class="alert alert-warn">
                <strong>Attention :</strong> impossible d’appeler l’API
                <code><?php echo htmlspecialchars($api_url, ENT_QUOTES, 'UTF-8'); ?></code>
                (vérifiez l’URL ou <code>allow_url_fopen</code>).
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo (int) $stats['aujourd_hui']; ?></div>
                <div>Entrées aujourd’hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo (int) $stats['presents']; ?></div>
                <div>Véhicules présents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($files_entree); ?></div>
                <div>Images en attente (entrée)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($files_sortie); ?></div>
                <div>Images en attente (sortie)</div>
            </div>
        </div>

        <div class="files-section">
            <h3>Dossier <code>entree</code></h3>
            <?php if (empty($files_entree)): ?>
                <p>Aucune image en attente</p>
            <?php else: ?>
                <?php foreach ($files_entree as $file): ?>
                    <div class="file-item">
                        <span><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo round($file['size'] / 1024, 2); ?> Ko · <?php echo htmlspecialchars($file['modified'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="files-section">
            <h3>Dossier <code>sortie</code></h3>
            <?php if (empty($files_sortie)): ?>
                <p>Aucune image en attente</p>
            <?php else: ?>
                <?php foreach ($files_sortie as $file): ?>
                    <div class="file-item">
                        <span><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo round($file['size'] / 1024, 2); ?> Ko · <?php echo htmlspecialchars($file['modified'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="files-section">
            <h3>Dernières visites</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Matricule</th>
                        <th>Entrée</th>
                        <th>Sortie</th>
                        <th>Durée (min)</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['dernieres'] as $entree): ?>
                        <tr>
                            <td><?php echo (int) $entree['id']; ?></td>
                            <td><?php echo htmlspecialchars((string) $entree['matricule'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $entree['entree_le'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo !empty($entree['sortie_le']) ? htmlspecialchars((string) $entree['sortie_le'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td><?php echo isset($entree['duree_minutes']) && $entree['duree_minutes'] !== null ? (int) $entree['duree_minutes'] : '—'; ?></td>
                            <td><span class="status-<?php echo htmlspecialchars((string) $entree['statut'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper((string) $entree['statut']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        setTimeout(function () { window.location.reload(); }, 5000);
    </script>
</body>
</html>
