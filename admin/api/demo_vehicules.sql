-- =====================================================================
-- Véhicules réels pour la démo PFA — UNIQUEMENT les photos de l'utilisateur
-- =====================================================================
-- Pour la démo jury : on enregistre les véhicules dont on a la VRAIE photo.
-- L'OCR + fuzzy matching (Levenshtein) renverra le matricule exact même si
-- la lecture brute a quelques caractères de différence.
-- =====================================================================

USE `parking_db`;

-- Nettoie tout ce qui a été ajouté précédemment pour la démo
DELETE FROM `vehicule` WHERE `matricule` IN (
    -- ancienne génération synthétique (suffixes 1-chiffre)
    '12345ا12','47832ب5','9087ج44','23456د7','65432ه21',
    '11122و33','78901ط9','34567ي18','80808ل6','55667م27',
    -- nouvelle génération synthétique (suffixes 2-chiffres)
    '12345ب12','47832ج15','90876د44','23456و17','65432ط21',
    '11122ل33','78901م19','34567ن18','80808ه16','55667ي27',
    -- vraies photos (rechargées plus bas)
    '3938و6','73242ج55','74904ا6','78904ه6'
);

-- Les VRAIES plaques visibles sur tes photos :
INSERT INTO `vehicule` (`matricule`, `marque`, `type`) VALUES
    ('3938و6',   'Renault', 'Symbol'),    -- photo Capture 195121 (Renault grise)
    ('78904ه6',  'Peugeot', '301'),       -- photo Capture 195243
    ('73242ج55', 'Dacia',   'Sandero');   -- petite photo qui marchait déjà

-- Vérification
SELECT id_vehicule, matricule, marque, type FROM vehicule
WHERE matricule IN ('3938و6','78904ه6','73242ج55')
ORDER BY id_vehicule;
