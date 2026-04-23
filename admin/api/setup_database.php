<?php
/**
 * À ouvrir UNE FOIS dans le navigateur après avoir démarré MySQL dans XAMPP :
 * http://localhost/PFA/admin/api/setup_database.php
 */
header('Content-Type: text/html; charset=utf-8');
try {
    require_once __DIR__ . '/db.php';
    $cfg = pfa_db_config();
    pfa_ensure_schema();
    $dbName = htmlspecialchars($cfg['name'], ENT_QUOTES, 'UTF-8');
    echo '<p>Base <strong>' . $dbName . '</strong> et table <strong>visites_parking</strong> : OK.</p>';
    echo '<p>Les colonnes manquantes sont complétées automatiquement au premier accès à l’API.</p>';
    echo '<p><a href="../admin.html#historique">Retour admin — Historique</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Vérifiez que <strong>MySQL</strong> est démarré dans XAMPP.</p>';
}
