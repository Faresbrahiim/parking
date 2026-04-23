<?php
/**
 * Logique métier scan entree/ et sortie/ (sans echo JSON).
 */
@set_time_limit(300);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plaque_helper.php';

/**
 * @return array{traitees: list<array>, avertissements: list<array>}
 */
function pfa_process_entree_folder(): array
{
    $entree_dir = pfa_parking_dir('entree');
    $traitees = [];
    $avertissements = [];

    if (!is_dir($entree_dir)) {
        @mkdir($entree_dir, 0777, true);
    }

    if (!is_dir($entree_dir)) {
        return ['traitees' => [], 'avertissements' => [['message' => 'Impossible de créer le dossier entree']]];
    }

    foreach (scandir($entree_dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $file_path = $entree_dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($file_path) || @getimagesize($file_path) === false) {
            continue;
        }

        $resultat = pfa_detect_matricule_from_image($file_path);
        $mat = trim((string) ($resultat['matricule'] ?? ''));

        if (($resultat['method'] ?? '') === 'ocr_failed' || $mat === '') {
            $moved = pfa_deplacer_echec_ocr($file_path);
            $avertissements[] = [
                'fichier' => $file,
                'message' => 'Matricule non lu sur la photo (OCR). Photo nette, plaque cadrée, ou installez Python : pip install easyocr opencv-python-headless',
                'detail' => $resultat['error'] ?? '',
                'deplacee' => $moved ? 'parking/echec_ocr/' . $moved : null,
            ];
            continue;
        }

        try {
            $db = pfa_db();

            $chk = $db->prepare('SELECT id FROM visites_parking WHERE matricule = ? AND statut = \'present\' LIMIT 1');
            $chk->bind_param('s', $mat);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $moved = pfa_deplacer_image_traitee($file_path, 'dup_entree');
                $avertissements[] = [
                    'fichier' => $file,
                    'matricule' => $mat,
                    'message' => 'Déjà présent au parking — image archivée sans nouvelle entrée.',
                    'deplacee' => $moved,
                ];
                $chk->close();
                continue;
            }
            $chk->close();

            $stmt = $db->prepare(
                'INSERT INTO visites_parking (matricule, entree_le, statut, source, image_path, ocr_method)
                 VALUES (?, NOW(), \'present\', \'entree_dossier\', ?, \'easyocr\')'
            );
            $imgRef = $file;
            $stmt->bind_param('ss', $mat, $imgRef);
            $stmt->execute();
            $newId = (int) $db->insert_id;
            $stmt->close();

            pfa_log_historique($db, $mat, 'entree', $newId, null);

            $moved = pfa_deplacer_image_traitee($file_path, 'entree');
            if ($moved) {
                $up = $db->prepare('UPDATE visites_parking SET image_path = ? WHERE id = ?');
                $up->bind_param('si', $moved, $newId);
                $up->execute();
                $up->close();
            }

            $traitees[] = [
                'fichier' => $file,
                'matricule' => $mat,
                'method' => $resultat['method'],
                'id_visite' => $newId,
                'deplacee_vers' => $moved ? 'processed/' . $moved : null,
            ];
        } catch (Throwable $e) {
            $avertissements[] = [
                'fichier' => $file,
                'matricule' => $mat,
                'error' => $e->getMessage(),
            ];
        }
    }

    return ['traitees' => $traitees, 'avertissements' => $avertissements];
}

/**
 * @return array{traitees: list<array>, avertissements: list<array>}
 */
function pfa_process_sortie_folder(): array
{
    $sortie_dir = pfa_parking_dir('sortie');
    $traitees = [];
    $avertissements = [];

    if (!is_dir($sortie_dir)) {
        @mkdir($sortie_dir, 0777, true);
    }

    if (!is_dir($sortie_dir)) {
        return ['traitees' => [], 'avertissements' => [['message' => 'Impossible de créer le dossier sortie']]];
    }

    foreach (scandir($sortie_dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $file_path = $sortie_dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($file_path) || @getimagesize($file_path) === false) {
            continue;
        }

        $resultat = pfa_detect_matricule_from_image($file_path);
        $mat = trim((string) ($resultat['matricule'] ?? ''));

        if (($resultat['method'] ?? '') === 'ocr_failed' || $mat === '') {
            $moved = pfa_deplacer_echec_ocr($file_path);
            $avertissements[] = [
                'fichier' => $file,
                'message' => 'Matricule non lu sur la photo — impossible d’enregistrer la sortie.',
                'detail' => $resultat['error'] ?? '',
                'deplacee' => $moved ? 'parking/echec_ocr/' . $moved : null,
            ];
            continue;
        }

        try {
            $db = pfa_db();

            $sel = $db->prepare(
                'SELECT id, entree_le FROM visites_parking
                 WHERE matricule = ? AND statut = \'present\'
                 ORDER BY entree_le DESC LIMIT 1'
            );
            $sel->bind_param('s', $mat);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();

            if (!$row) {
                $avertissements[] = [
                    'fichier' => $file,
                    'matricule' => $mat,
                    'message' => 'Aucune entrée « présent » pour ce matricule — image laissée dans sortie/.',
                ];
                continue;
            }

            $id = (int) $row['id'];

            $moved = pfa_deplacer_image_traitee($file_path, 'sortie');
            $imgSortie = $moved ?: $file;

            $up = $db->prepare(
                'UPDATE visites_parking SET
                    sortie_le = NOW(),
                    statut = \'sorti\',
                    duree_minutes = TIMESTAMPDIFF(MINUTE, entree_le, NOW()),
                    image_path_sortie = ?,
                    source = IFNULL(source, \'sortie_dossier\')
                 WHERE id = ?'
            );
            $up->bind_param('si', $imgSortie, $id);
            $up->execute();
            $up->close();

            $duree = null;
            $rd = $db->prepare('SELECT duree_minutes FROM visites_parking WHERE id = ?');
            $rd->bind_param('i', $id);
            $rd->execute();
            $dr = $rd->get_result()->fetch_assoc();
            $rd->close();
            if ($dr && $dr['duree_minutes'] !== null) {
                $duree = (int) $dr['duree_minutes'];
            }

            pfa_log_historique($db, $mat, 'sortie', $id, $duree);

            $traitees[] = [
                'fichier' => $file,
                'matricule' => $mat,
                'method' => $resultat['method'],
                'id_visite' => $id,
                'duree_minutes' => $duree,
                'deplacee_vers' => $moved ? 'processed/' . $moved : null,
            ];
        } catch (Throwable $e) {
            $avertissements[] = [
                'fichier' => $file,
                'matricule' => $mat,
                'error' => $e->getMessage(),
            ];
        }
    }

    return ['traitees' => $traitees, 'avertissements' => $avertissements];
}
