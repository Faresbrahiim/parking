USE parking_db;
SET NAMES utf8mb4;
SELECT id_vehicule, matricule, HEX(matricule) AS hex_check, CHAR_LENGTH(matricule) AS nb_chars FROM vehicule WHERE id_vehicule >= 32;
