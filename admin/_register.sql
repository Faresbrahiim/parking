USE parking_db;

-- Affiche ce qui est déjà enregistré
SELECT id_vehicule, matricule, marque, type FROM vehicule ORDER BY id_vehicule;

-- Enregistre les 3 plaques de démonstration (avec la VRAIE lettre arabe)
-- Le fuzzy match (Levenshtein ≤ 4) snappera automatiquement dessus même
-- si Plate Recognizer se trompe sur la lettre arabe (ex: أ au lieu de ه).
INSERT IGNORE INTO vehicule (matricule, marque, type) VALUES
('23242أ55', 'Renault',   'Clio'),
('3938و6',   'Peugeot',   '208'),
('78904ه6',  'Dacia',     'Logan');

-- Vérification
SELECT id_vehicule, matricule, marque, type FROM vehicule ORDER BY id_vehicule;
