<?php

function pfa_db_config(): array
{
    return require __DIR__ . '/config_db.php';
}

/** Connexion MySQL (sans sélection de base) */
function pfa_mysqli_server(): mysqli
{
    $c = pfa_db_config();
    $m = new mysqli($c['host'], $c['user'], $c['pass']);
    if ($m->connect_error) {
        throw new RuntimeException('Conn.MySQL: ' . $m->connect_error);
    }
    $m->set_charset('utf8mb4');
    return $m;
}

/** Connexion à la base configurée (ex. parking_db) */
function pfa_db(): mysqli
{
    static $conn = null;
    static $migrated = false;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    $c = pfa_db_config();
    $conn = new mysqli($c['host'], $c['user'], $c['pass'], $c['name']);
    if ($conn->connect_error) {
        throw new RuntimeException('Conn.base: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    if (!$migrated) {
        pfa_migrate_schema($conn);
        $migrated = true;
    }
    return $conn;
}

/** Colonnes optionnelles pour images, durée, source + table historique (événements). */
function pfa_migrate_schema(mysqli $db): void
{
    $r = $db->query("SHOW TABLES LIKE 'visites_parking'");
    if ($r && $r->num_rows > 0) {
        $have = [];
        $c = $db->query('SHOW COLUMNS FROM visites_parking');
        if ($c) {
            while ($row = $c->fetch_assoc()) {
                $have[$row['Field']] = true;
            }
        }
        $add = [
            'source' => 'VARCHAR(64) NULL DEFAULT NULL',
            'image_path' => 'VARCHAR(255) NULL DEFAULT NULL',
            'image_path_sortie' => 'VARCHAR(255) NULL DEFAULT NULL',
            'duree_minutes' => 'INT NULL DEFAULT NULL',
            'ocr_method' => 'VARCHAR(32) NULL DEFAULT NULL',
        ];
        foreach ($add as $field => $def) {
            if (empty($have[$field])) {
                $db->query("ALTER TABLE visites_parking ADD COLUMN `{$field}` {$def}");
            }
        }
    }

    $h = $db->query("SHOW TABLES LIKE 'historique'");
    if ($h && $h->num_rows > 0) {
        $have = [];
        $c = $db->query('SHOW COLUMNS FROM historique');
        if ($c) {
            while ($row = $c->fetch_assoc()) {
                $have[$row['Field']] = true;
            }
        }
        $add2 = [
            'matricule' => 'VARCHAR(128) NULL DEFAULT NULL',
            'type_evenement' => 'VARCHAR(32) NULL DEFAULT NULL',
            'id_visite' => 'INT UNSIGNED NULL DEFAULT NULL',
            'duree_minutes' => 'INT NULL DEFAULT NULL',
        ];
        foreach ($add2 as $field => $def) {
            if (empty($have[$field])) {
                $db->query("ALTER TABLE historique ADD COLUMN `{$field}` {$def}");
            }
        }
    }
}

function pfa_ensure_schema(): void
{
    $c = pfa_db_config();
    $db = $c['name'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
        throw new InvalidArgumentException('Nom de base invalide.');
    }
    $m = pfa_mysqli_server();
    $m->query("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $m->select_db($db);
    
    // Table des visites (améliorée avec images)
    $m->query(
        'CREATE TABLE IF NOT EXISTS visites_parking (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            matricule VARCHAR(128) NOT NULL,
            entree_le DATETIME NOT NULL,
            sortie_le DATETIME NULL DEFAULT NULL,
            statut ENUM("present","sorti") NOT NULL DEFAULT "present",
            source VARCHAR(64) DEFAULT "entree_photo",
            image_path VARCHAR(255) DEFAULT NULL,
            image_path_sortie VARCHAR(255) DEFAULT NULL,
            duree_minutes INT DEFAULT NULL,
            ocr_method VARCHAR(32) DEFAULT NULL,
            KEY idx_matricule (matricule),
            KEY idx_entree (entree_le),
            KEY idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    
    $m->close();
}
