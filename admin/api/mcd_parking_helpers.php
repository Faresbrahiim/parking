<?php
/**
 * Synchronisation entre la table technique `visites_parking` et le MCD :
 * vehicule, historique, stocker, place_parking.
 * Détecte les colonnes réelles de `historique` (schéma minimal ou étendu).
 */

require_once __DIR__ . '/plaque_helper.php';

/**
 * @return array<string, array{type:string, null:string}>
 */
function pfa_mcd_table_columns(mysqli $db, string $table): array
{
    $out = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($t === '') {
        return $out;
    }
    $r = $db->query('SHOW COLUMNS FROM `' . $t . '`');
    if (!$r) {
        return $out;
    }
    while ($row = $r->fetch_assoc()) {
        $out[$row['Field']] = [
            'type' => strtolower((string) ($row['Type'] ?? '')),
            'null' => strtoupper((string) ($row['Null'] ?? 'YES')),
        ];
    }
    return $out;
}

function pfa_mcd_has_table(mysqli $db, string $table): bool
{
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($t === '') {
        return false;
    }
    $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

/**
 * Assure une ligne vehicule pour le matricule (MCD : marque, type NOT NULL).
 */
function pfa_mcd_ensure_vehicule_id(mysqli $db, string $matricule): ?int
{
    if (!pfa_mcd_has_table($db, 'vehicule')) {
        return null;
    }
    $mat = trim($matricule);
    if ($mat === '') {
        return null;
    }
    $tryMats = [$mat];
    foreach (pfa_ma_matricule_yeh_alif_alternates($mat) as $alt) {
        if ($alt !== '' && !in_array($alt, $tryMats, true)) {
            $tryMats[] = $alt;
        }
    }
    $st = $db->prepare('SELECT id_vehicule FROM vehicule WHERE matricule = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    foreach ($tryMats as $try) {
        $st->bind_param('s', $try);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $st->close();
            return (int) $row['id_vehicule'];
        }
    }
    $st->close();
    $ins = $db->prepare('INSERT INTO vehicule (matricule, marque, type) VALUES (?, ?, ?)');
    if (!$ins) {
        return null;
    }
    $m = 'N/A';
    $ins->bind_param('sss', $mat, $m, $m);
    if (!$ins->execute()) {
        $ins->close();
        return null;
    }
    $id = (int) $db->insert_id;
    $ins->close();
    return $id > 0 ? $id : null;
}

/**
 * Choisit une place libre (statuts usuels du MCD / démo).
 */
function pfa_mcd_pick_free_place_id(mysqli $db): ?int
{
    if (!pfa_mcd_has_table($db, 'place_parking')) {
        return null;
    }
    $sql = "SELECT id_place FROM place_parking
            WHERE LOWER(TRIM(statut)) IN ('disponible','libre','free','vide')
            ORDER BY id_place ASC
            LIMIT 1";
    $r = $db->query($sql);
    if (!$r) {
        return null;
    }
    $row = $r->fetch_assoc();
    return $row ? (int) $row['id_place'] : null;
}

/**
 * @param array<string, array{type:string, null:string}> $hcols
 * @return array{sql: string, types: string, params: list<mixed>}
 */
function pfa_mcd_build_historique_insert(mysqli $db, array $hcols, string $matricule, int $id_visite, string $typeEvenement): ?array
{
    $fields = [];
    $values = [];
    $types = '';
    $params = [];

    if (isset($hcols['date'])) {
        $fields[] = '`date`';
        $t = $hcols['date']['type'];
        if (strpos($t, 'datetime') !== false || strpos($t, 'timestamp') !== false) {
            $values[] = 'NOW()';
        } else {
            $values[] = 'CURDATE()';
        }
    }

    if (isset($hcols['matricule'])) {
        $fields[] = '`matricule`';
        $values[] = '?';
        $types .= 's';
        $params[] = $matricule;
    }

    if (isset($hcols['type_evenement'])) {
        $fields[] = '`type_evenement`';
        $values[] = '?';
        $types .= 's';
        $params[] = $typeEvenement;
    }

    if (isset($hcols['id_visite'])) {
        $fields[] = '`id_visite`';
        $values[] = '?';
        $types .= 'i';
        $params[] = $id_visite;
    }

    if (empty($fields)) {
        return null;
    }

    $sql = 'INSERT INTO historique (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
    return ['sql' => $sql, 'types' => $types, 'params' => $params];
}

/**
 * Après INSERT dans visites_parking : historique + stocker + occupation place.
 */
function pfa_mcd_sync_after_entree(mysqli $db, string $matricule, int $id_visite): void
{
    if (!pfa_mcd_has_table($db, 'historique')) {
        return;
    }

    pfa_mcd_ensure_vehicule_id($db, $matricule);

    $hcols = pfa_mcd_table_columns($db, 'historique');
    $built = pfa_mcd_build_historique_insert($db, $hcols, $matricule, $id_visite, 'entree');
    if (!$built) {
        return;
    }

    $stmt = $db->prepare($built['sql']);
    if (!$stmt) {
        return;
    }
    if ($built['types'] !== '') {
        $stmt->bind_param($built['types'], ...$built['params']);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $idHistorique = (int) $db->insert_id;
    $stmt->close();

    if ($idHistorique <= 0) {
        return;
    }

    $up = $db->prepare('UPDATE visites_parking SET id_historique_mcd = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('ii', $idHistorique, $id_visite);
        $up->execute();
        $up->close();
    }

    if (!pfa_mcd_has_table($db, 'stocker') || !pfa_mcd_has_table($db, 'place_parking')) {
        return;
    }

    $idPlace = pfa_mcd_pick_free_place_id($db);
    if ($idPlace === null) {
        return;
    }

    $st = $db->prepare('INSERT INTO stocker (id_place, id_historique) VALUES (?, ?)');
    if (!$st) {
        return;
    }
    $st->bind_param('ii', $idPlace, $idHistorique);
    if (!$st->execute()) {
        $st->close();
        return;
    }
    $st->close();

    $occ = $db->prepare("UPDATE place_parking SET statut = 'occupee' WHERE id_place = ?");
    if ($occ) {
        $occ->bind_param('i', $idPlace);
        $occ->execute();
        $occ->close();
    }
}

/**
 * Après clôture visite : libère la place, met à jour historique.
 */
function pfa_mcd_sync_after_sortie(mysqli $db, int $id_visite, ?int $duree_minutes): void
{
    if (!pfa_mcd_has_table($db, 'historique')) {
        return;
    }

    $idHistorique = null;
    $chk = $db->prepare('SELECT id_historique_mcd FROM visites_parking WHERE id = ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('i', $id_visite);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($row && $row['id_historique_mcd'] !== null && $row['id_historique_mcd'] !== '') {
            $idHistorique = (int) $row['id_historique_mcd'];
        }
    }

    $hcols = pfa_mcd_table_columns($db, 'historique');
    if ($idHistorique === null || $idHistorique <= 0) {
        if (isset($hcols['id_visite'])) {
            $q = $db->prepare('SELECT id_historique FROM historique WHERE id_visite = ? ORDER BY id_historique DESC LIMIT 1');
            if ($q) {
                $q->bind_param('i', $id_visite);
                $q->execute();
                $r2 = $q->get_result()->fetch_assoc();
                $q->close();
                if ($r2) {
                    $idHistorique = (int) $r2['id_historique'];
                }
            }
        }
    }

    if ($idHistorique === null || $idHistorique <= 0) {
        return;
    }

    $idPlace = null;
    if (pfa_mcd_has_table($db, 'stocker')) {
        $sp = $db->prepare('SELECT id_place FROM stocker WHERE id_historique = ? LIMIT 1');
        if ($sp) {
            $sp->bind_param('i', $idHistorique);
            $sp->execute();
            $rp = $sp->get_result()->fetch_assoc();
            $sp->close();
            if ($rp) {
                $idPlace = (int) $rp['id_place'];
            }
        }
    }

    if (pfa_mcd_has_table($db, 'stocker')) {
        $del = $db->prepare('DELETE FROM stocker WHERE id_historique = ?');
        if ($del) {
            $del->bind_param('i', $idHistorique);
            $del->execute();
            $del->close();
        }
    }

    if ($idPlace !== null && $idPlace > 0 && pfa_mcd_has_table($db, 'place_parking')) {
        $fr = $db->prepare("UPDATE place_parking SET statut = 'disponible' WHERE id_place = ?");
        if ($fr) {
            $fr->bind_param('i', $idPlace);
            $fr->execute();
            $fr->close();
        }
    }

    $sets = [];
    $types = '';
    $params = [];

    if (isset($hcols['duree_minutes']) && $duree_minutes !== null) {
        $sets[] = '`duree_minutes` = ?';
        $types .= 'i';
        $params[] = $duree_minutes;
    }

    if (isset($hcols['type_evenement'])) {
        $sets[] = '`type_evenement` = ?';
        $types .= 's';
        $params[] = 'sortie';
    }

    if (!empty($sets)) {
        $types .= 'i';
        $params[] = $idHistorique;
        $sql = 'UPDATE historique SET ' . implode(', ', $sets) . ' WHERE id_historique = ?';
        $uph = $db->prepare($sql);
        if ($uph) {
            $uph->bind_param($types, ...$params);
            $uph->execute();
            $uph->close();
        }
    }
}
