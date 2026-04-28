USE parking_db;
SELECT id, matricule, entree_le, statut, source, image_path, ocr_method
FROM visites_parking
ORDER BY id DESC
LIMIT 10;
