<?php
/**
 * Enregistre les 3 plaques de démonstration avec la VRAIE lettre arabe.
 * À exécuter une seule fois depuis la CLI :
 *   "C:\xampp\php\php.exe" _register.php
 */
require_once __DIR__ . '/api/db.php';

$db = pfa_db();

// Supprime les anciennes insertions foireuses (avec '??')
$db->query("DELETE FROM vehicule WHERE matricule LIKE '%??%' AND id_vehicule >= 32");

$plates = [
    ['23242أ55', 'Renault', 'Clio'],
    ['3938و6',   'Peugeot', '208'],
    ['78904ه6',  'Dacia',   'Logan'],
];

foreach ($plates as [$m, $marque, $type]) {
    $chk = $db->prepare('SELECT id_vehicule FROM vehicule WHERE matricule = ?');
    $chk->bind_param('s', $m);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing) {
        echo "Déjà enregistré : $m (id=" . $existing['id_vehicule'] . ")\n";
        continue;
    }

    $ins = $db->prepare('INSERT INTO vehicule (matricule, marque, type) VALUES (?, ?, ?)');
    $ins->bind_param('sss', $m, $marque, $type);
    $ins->execute();
    $id = $db->insert_id;
    $ins->close();
    echo "Enregistré : $m → id=$id ($marque $type)\n";
}

echo "\n--- Liste finale ---\n";
$r = $db->query('SELECT id_vehicule, matricule, marque, type, CHAR_LENGTH(matricule) AS nb FROM vehicule ORDER BY id_vehicule');
while ($row = $r->fetch_assoc()) {
    echo sprintf("  #%d  %s  (%d chars)  %s %s\n",
        $row['id_vehicule'], $row['matricule'], $row['nb'], $row['marque'], $row['type']);
}
