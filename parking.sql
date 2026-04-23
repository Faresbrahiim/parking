-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 14 avr. 2026 à 17:50
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `parking_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `prenom` varchar(50) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `archiver`
--

CREATE TABLE `archiver` (
  `id_historique` int(11) NOT NULL,
  `id_rapport` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `consulter`
--

CREATE TABLE `consulter` (
  `id_rapport` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique`
--

CREATE TABLE `historique` (
  `id_historique` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `matricule` varchar(128) DEFAULT NULL,
  `type_evenement` varchar(32) DEFAULT NULL,
  `id_visite` int(10) UNSIGNED DEFAULT NULL,
  `duree_minutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `historique`
--

INSERT INTO `historique` (`id_historique`, `date`, `matricule`, `type_evenement`, `id_visite`, `duree_minutes`) VALUES
(1, '2026-04-11', 'AUTO-1845-878d7f', 'entree', 1, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `inclure`
--

CREATE TABLE `inclure` (
  `id_service` int(11) NOT NULL,
  `id_reservation` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

CREATE TABLE `paiement` (
  `id_paiement` int(11) NOT NULL,
  `date_paiement` date DEFAULT NULL,
  `montant` int(11) DEFAULT NULL,
  `mode_de_paiement` varchar(25) DEFAULT NULL,
  `statut_paiement` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `place_parking`
--

CREATE TABLE `place_parking` (
  `id_place` int(11) NOT NULL,
  `statut` varchar(25) DEFAULT NULL,
  `niveau` varchar(25) DEFAULT NULL,
  `zone` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posseder`
--

CREATE TABLE `posseder` (
  `id_utilisateur` int(11) NOT NULL,
  `id_vehicule` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rapport`
--

CREATE TABLE `rapport` (
  `id_rapport` int(11) NOT NULL,
  `type_rapport` varchar(20) DEFAULT NULL,
  `date_generation` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reservation`
--

CREATE TABLE `reservation` (
  `id_reservation` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `id_paiement` int(11) DEFAULT NULL,
  `id_place` int(11) DEFAULT NULL,
  `date_reservation` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `statut_reservation` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

CREATE TABLE `service` (
  `id_service` int(11) NOT NULL,
  `type_service` varchar(30) DEFAULT NULL,
  `prix` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stocker`
--

CREATE TABLE `stocker` (
  `id_place` int(11) NOT NULL,
  `id_historique` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_utilisateur` int(11) NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `email` VARCHAR(100) UNIQUE,
  `prenom` varchar(50) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `vehicule`
--

CREATE TABLE `vehicule` (
  `id_vehicule` int(11) NOT NULL,
  `matricule` varchar(25) DEFAULT NULL,
  `marque` varchar(30) DEFAULT NULL,
  `type` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `visites_parking`
--

CREATE TABLE `visites_parking` (
  `id` int(10) UNSIGNED NOT NULL,
  `matricule` varchar(128) NOT NULL,
  `entree_le` datetime NOT NULL,
  `sortie_le` datetime DEFAULT NULL,
  `statut` enum('present','sorti') NOT NULL DEFAULT 'present',
  `source` varchar(64) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_path_sortie` varchar(255) DEFAULT NULL,
  `duree_minutes` int(11) DEFAULT NULL,
  `ocr_method` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `visites_parking`
--

INSERT INTO `visites_parking` (`id`, `matricule`, `entree_le`, `sortie_le`, `statut`, `source`, `image_path`, `image_path_sortie`, `duree_minutes`, `ocr_method`) VALUES
(1, 'AUTO-1845-878d7f', '2026-04-11 17:45:15', NULL, 'present', 'entree_dossier', 'entree_20260411_184515_resultat_voiture.jpeg', NULL, NULL, NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`);

--
-- Index pour la table `archiver`
--
ALTER TABLE `archiver`
  ADD PRIMARY KEY (`id_historique`,`id_rapport`),
  ADD KEY `id_rapport` (`id_rapport`);

--
-- Index pour la table `consulter`
--
ALTER TABLE `consulter`
  ADD PRIMARY KEY (`id_rapport`,`id_admin`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Index pour la table `historique`
--
ALTER TABLE `historique`
  ADD PRIMARY KEY (`id_historique`);

--
-- Index pour la table `inclure`
--
ALTER TABLE `inclure`
  ADD PRIMARY KEY (`id_service`,`id_reservation`);

--
-- Index pour la table `paiement`
--
ALTER TABLE `paiement`
  ADD PRIMARY KEY (`id_paiement`);

--
-- Index pour la table `place_parking`
--
ALTER TABLE `place_parking`
  ADD PRIMARY KEY (`id_place`);

--
-- Index pour la table `posseder`
--
ALTER TABLE `posseder`
  ADD PRIMARY KEY (`id_utilisateur`,`id_vehicule`),
  ADD KEY `id_vehicule` (`id_vehicule`);

--
-- Index pour la table `rapport`
--
ALTER TABLE `rapport`
  ADD PRIMARY KEY (`id_rapport`);

--
-- Index pour la table `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`id_reservation`),
  ADD KEY `id_utilisateur` (`id_utilisateur`),
  ADD KEY `id_place` (`id_place`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`id_service`);

--
-- Index pour la table `stocker`
--
ALTER TABLE `stocker`
  ADD PRIMARY KEY (`id_place`,`id_historique`),
  ADD KEY `id_historique` (`id_historique`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_utilisateur`);

--
-- Index pour la table `vehicule`
--
ALTER TABLE `vehicule`
  ADD PRIMARY KEY (`id_vehicule`);

--
-- Index pour la table `visites_parking`
--
ALTER TABLE `visites_parking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_matricule` (`matricule`),
  ADD KEY `idx_entree` (`entree_le`),
  ADD KEY `idx_statut` (`statut`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique`
--
ALTER TABLE `historique`
  MODIFY `id_historique` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `paiement`
--
ALTER TABLE `paiement`
  MODIFY `id_paiement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `place_parking`
--
ALTER TABLE `place_parking`
  MODIFY `id_place` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rapport`
--
ALTER TABLE `rapport`
  MODIFY `id_rapport` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `id_reservation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service`
--
ALTER TABLE `service`
  MODIFY `id_service` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `vehicule`
--
ALTER TABLE `vehicule`
  MODIFY `id_vehicule` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `visites_parking`
--
ALTER TABLE `visites_parking`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `archiver`
--
ALTER TABLE `archiver`
  ADD CONSTRAINT `archiver_ibfk_1` FOREIGN KEY (`id_historique`) REFERENCES `historique` (`id_historique`),
  ADD CONSTRAINT `archiver_ibfk_2` FOREIGN KEY (`id_rapport`) REFERENCES `rapport` (`id_rapport`);

--
-- Contraintes pour la table `consulter`
--
ALTER TABLE `consulter`
  ADD CONSTRAINT `consulter_ibfk_1` FOREIGN KEY (`id_rapport`) REFERENCES `rapport` (`id_rapport`),
  ADD CONSTRAINT `consulter_ibfk_2` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`);

--
-- Contraintes pour la table `posseder`
--
ALTER TABLE `posseder`
  ADD CONSTRAINT `posseder_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `posseder_ibfk_2` FOREIGN KEY (`id_vehicule`) REFERENCES `vehicule` (`id_vehicule`);

--
-- Contraintes pour la table `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `reservation_ibfk_2` FOREIGN KEY (`id_place`) REFERENCES `place_parking` (`id_place`);

--
-- Contraintes pour la table `stocker`
--
ALTER TABLE `stocker`
  ADD CONSTRAINT `stocker_ibfk_1` FOREIGN KEY (`id_place`) REFERENCES `place_parking` (`id_place`),
  ADD CONSTRAINT `stocker_ibfk_2` FOREIGN KEY (`id_historique`) REFERENCES `historique` (`id_historique`);
COMMIT;

-- seeds for parking
-- LEVEL A
INSERT INTO place_parking (statut, niveau, zone) VALUES
('libre', 'A', 'A1'),
('libre', 'A', 'A2'),
('libre', 'A', 'A3'),
('libre', 'A', 'A4'),
('libre', 'A', 'A5'),

('libre', 'A', 'A1'),
('libre', 'A', 'A2'),
('libre', 'A', 'A3'),
('libre', 'A', 'A4'),
('libre', 'A', 'A5');

-- LEVEL B
INSERT INTO place_parking (statut, niveau, zone) VALUES
('libre', 'B', 'B1'),
('libre', 'B', 'B2'),
('libre', 'B', 'B3'),
('libre', 'B', 'B4'),
('libre', 'B', 'B5'),

('libre', 'B', 'B1'),
('libre', 'B', 'B2'),
('libre', 'B', 'B3'),
('libre', 'B', 'B4'),
('libre', 'B', 'B5');

-- LEVEL C
INSERT INTO place_parking (statut, niveau, zone) VALUES
('libre', 'C', 'C1'),
('libre', 'C', 'C2'),
('libre', 'C', 'C3'),
('libre', 'C', 'C4'),
('libre', 'C', 'C5'),

('libre', 'C', 'C1'),
('libre', 'C', 'C2'),
('libre', 'C', 'C3'),
('libre', 'C', 'C4'),
('libre', 'C', 'C5');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


