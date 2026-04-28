-- =====================================================================
-- Schéma MCD — parking_db
-- Fichier de RÉFÉRENCE pour vérifier que vos tables MySQL correspondent
-- exactement au MCD. À exécuter uniquement si la base est vide.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `parking_db`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `parking_db`;

-- ---------- Entités ----------

CREATE TABLE IF NOT EXISTS `utilisateur` (
    `id_utilisateur` INT NOT NULL AUTO_INCREMENT,
    `nom`            CHAR(50)    NOT NULL,
    `prenom`         CHAR(50)    NOT NULL,
    `mot_de_passe`   CHAR(20)    NOT NULL,
    PRIMARY KEY (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin` (
    `id_admin`     INT NOT NULL AUTO_INCREMENT,
    `nom`          CHAR(50) NOT NULL,
    `prenom`       CHAR(50) NOT NULL,
    `mot_de_passe` CHAR(20) NOT NULL,
    PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vehicule` (
    `id_vehicule` INT NOT NULL AUTO_INCREMENT,
    `matricule`   VARCHAR(25) NOT NULL,
    `marque`      CHAR(30)    NOT NULL,
    `type`        CHAR(30)    NOT NULL,
    PRIMARY KEY (`id_vehicule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `place_parking` (
    `id_place` INT NOT NULL AUTO_INCREMENT,
    `statut`   CHAR(25)    NOT NULL,
    `niveau`   VARCHAR(25) NOT NULL,
    `zone`     VARCHAR(25) NOT NULL,
    PRIMARY KEY (`id_place`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `paiement` (
    `id_paiement`      INT NOT NULL AUTO_INCREMENT,
    `date_paiement`    DATE     NOT NULL,
    `montant`          INT      NOT NULL,
    `mode_de_paiement` CHAR(25) NOT NULL,
    `statut_paiement`  CHAR(25) NOT NULL,
    PRIMARY KEY (`id_paiement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tarifs affichés côté admin / site (pas de traitement de paiement en ligne dans ce projet).
CREATE TABLE IF NOT EXISTS `revenu` (
    `id_revenu` INT NOT NULL AUTO_INCREMENT,
    `libelle`   VARCHAR(80) NOT NULL,
    `montant`   INT NOT NULL COMMENT 'DH',
    `unite`     VARCHAR(20) NOT NULL DEFAULT 'forfait',
    `ordre`     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_revenu`),
    KEY `idx_revenu_ordre` (`ordre`, `id_revenu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Exemples (optionnel) :
-- INSERT INTO revenu (libelle, montant, unite, ordre) VALUES
--   ('1ère heure', 5, 'heure', 1),
--   ('Forfait journée', 40, 'jour', 2);

CREATE TABLE IF NOT EXISTS `service` (
    `id_service`   INT NOT NULL AUTO_INCREMENT,
    `type_service` CHAR(30)    NOT NULL,
    `prix`         VARCHAR(30) NOT NULL,
    PRIMARY KEY (`id_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `historique` (
    `id_historique` INT NOT NULL AUTO_INCREMENT,
    `date`          DATE NOT NULL,
    PRIMARY KEY (`id_historique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rapport` (
    `id_rapport`      INT NOT NULL AUTO_INCREMENT,
    `type_rapport`    CHAR(20) NOT NULL,
    `date_generation` DATE     NOT NULL,
    PRIMARY KEY (`id_rapport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reservation` (
    `id_reservation`     INT NOT NULL AUTO_INCREMENT,
    `id_utilisateur`     INT NOT NULL,
    `id_paiement`        INT NOT NULL,
    `id_place`           INT NOT NULL,
    `date_reservation`   DATE NOT NULL,
    `heure_debut`        TIME NOT NULL,
    `heure_fin`          TIME NOT NULL,
    `statut_reservation` ENUM('en_attente','confirmee','annulee','terminee') NOT NULL,
    PRIMARY KEY (`id_reservation`),
    KEY `fk_r_user`  (`id_utilisateur`),
    KEY `fk_r_pay`   (`id_paiement`),
    KEY `fk_r_place` (`id_place`),
    CONSTRAINT `fk_r_user`  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur`    (`id_utilisateur`),
    CONSTRAINT `fk_r_pay`   FOREIGN KEY (`id_paiement`)    REFERENCES `paiement`       (`id_paiement`),
    CONSTRAINT `fk_r_place` FOREIGN KEY (`id_place`)       REFERENCES `place_parking`  (`id_place`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Tables associatives ----------

CREATE TABLE IF NOT EXISTS `posseder` (
    `id_utilisateur` INT NOT NULL,
    `id_vehicule`    INT NOT NULL,
    PRIMARY KEY (`id_utilisateur`, `id_vehicule`),
    CONSTRAINT `fk_po_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
    CONSTRAINT `fk_po_veh`  FOREIGN KEY (`id_vehicule`)    REFERENCES `vehicule`    (`id_vehicule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inclure` (
    `id_service`     INT NOT NULL,
    `id_reservation` INT NOT NULL,
    PRIMARY KEY (`id_service`, `id_reservation`),
    CONSTRAINT `fk_inc_srv` FOREIGN KEY (`id_service`)     REFERENCES `service`     (`id_service`),
    CONSTRAINT `fk_inc_res` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stocker` (
    `id_place`      INT NOT NULL,
    `id_historique` INT NOT NULL,
    PRIMARY KEY (`id_place`, `id_historique`),
    CONSTRAINT `fk_sto_place` FOREIGN KEY (`id_place`)      REFERENCES `place_parking` (`id_place`),
    CONSTRAINT `fk_sto_hist`  FOREIGN KEY (`id_historique`) REFERENCES `historique`    (`id_historique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `archiver` (
    `id_historique` INT NOT NULL,
    `id_rapport`    INT NOT NULL,
    PRIMARY KEY (`id_historique`, `id_rapport`),
    CONSTRAINT `fk_arc_hist` FOREIGN KEY (`id_historique`) REFERENCES `historique` (`id_historique`),
    CONSTRAINT `fk_arc_rap`  FOREIGN KEY (`id_rapport`)    REFERENCES `rapport`    (`id_rapport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `consulter` (
    `id_rapport` INT NOT NULL,
    `id_admin`   INT NOT NULL,
    PRIMARY KEY (`id_rapport`, `id_admin`),
    CONSTRAINT `fk_con_rap`   FOREIGN KEY (`id_rapport`) REFERENCES `rapport` (`id_rapport`),
    CONSTRAINT `fk_con_admin` FOREIGN KEY (`id_admin`)   REFERENCES `admin`   (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- Table TECHNIQUE (hors MCD) pour l'OCR des matricules
-- Créée automatiquement par le code PHP (pfa_ensure_schema),
-- vous n'avez rien à faire manuellement.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `visites_parking` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `matricule`         VARCHAR(128) NOT NULL,
    `entree_le`         DATETIME     NOT NULL,
    `sortie_le`         DATETIME     NULL,
    `statut`            ENUM('present','sorti') NOT NULL DEFAULT 'present',
    `source`            VARCHAR(64)  DEFAULT 'entree_photo',
    `image_path`        VARCHAR(255) DEFAULT NULL,
    `image_path_sortie` VARCHAR(255) DEFAULT NULL,
    `duree_minutes`     INT          DEFAULT NULL,
    `ocr_method`        VARCHAR(32)  DEFAULT NULL,
    `id_historique_mcd` INT          DEFAULT NULL COMMENT 'Lien optionnel vers historique (synchro OCR)',
    KEY `idx_matricule` (`matricule`),
    KEY `idx_entree`    (`entree_le`),
    KEY `idx_statut`    (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
