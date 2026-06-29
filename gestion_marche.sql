-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 29, 2026 at 11:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gestion_marche`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrateur`
--

CREATE TABLE `administrateur` (
  `id_admin` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_marche`
--

CREATE TABLE `agent_marche` (
  `id_agent` int(11) NOT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_marche`
--

INSERT INTO `agent_marche` (`id_agent`, `matricule`, `id_user`) VALUES
(1, 'AGM2026810', 3),
(2, 'AGM2026687', 6);

-- --------------------------------------------------------

--
-- Table structure for table `caissier`
--

CREATE TABLE `caissier` (
  `id_caissier` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commercant`
--

CREATE TABLE `commercant` (
  `id_commercant` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `produits_vendu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commercant`
--

INSERT INTO `commercant` (`id_commercant`, `id_user`, `produits_vendu`) VALUES
(1, 1, ''),
(2, 2, ''),
(3, 4, 'Viande'),
(4, 5, 'Viande'),
(5, 7, 'Medicaments'),
(6, 8, 'legumes');

-- --------------------------------------------------------

--
-- Table structure for table `etalage`
--

CREATE TABLE `etalage` (
  `id_etalage` int(11) NOT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `localisation` varchar(150) DEFAULT NULL,
  `statut` varchar(50) DEFAULT NULL,
  `id_secteur` int(11) DEFAULT NULL,
  `id_commercant` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `etalage`
--

INSERT INTO `etalage` (`id_etalage`, `numero`, `localisation`, `statut`, `id_secteur`, `id_commercant`) VALUES
(1, 'ET001', 'Côte Nord', 'en_attente', 3, NULL),
(2, 'ET006', 'coté Sud', 'en_attente', 4, NULL),
(3, 'OOO6', 'cote ouest', 'en_attente', 4, 6);

-- --------------------------------------------------------

--
-- Table structure for table `location`
--

CREATE TABLE `location` (
  `id_location` int(11) NOT NULL,
  `montant_location` decimal(10,2) DEFAULT NULL,
  `duree_location` varchar(50) DEFAULT NULL,
  `status` enum('en_attente','approuve','refuse','annule') NOT NULL DEFAULT 'en_attente',
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `id_commercant` int(11) DEFAULT NULL,
  `id_etalage` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `location`
--

INSERT INTO `location` (`id_location`, `montant_location`, `duree_location`, `status`, `commentaire`, `created_at`, `date_debut`, `date_fin`, `id_commercant`, `id_etalage`) VALUES
(1, 16.00, '3 Mois', 'en_attente', NULL, '2026-06-29 07:43:40', '2026-06-24', '2026-09-24', 6, 3),
(6, 50000.00, '1 mois', 'en_attente', '', '2026-06-29 08:00:06', '2026-06-29', '2026-07-29', 5, 2),
(7, 50000.00, '1 mois', 'en_attente', '', '2026-06-29 08:09:05', '2026-06-29', '2026-07-29', 5, 1),
(8, 50000.00, '1 mois', 'en_attente', '', '2026-06-29 08:15:18', '2026-06-29', '2026-07-29', 5, 3),
(9, 50000.00, '1 mois', 'en_attente', '', '2026-06-29 09:12:30', '2026-06-29', '2026-07-29', 5, 2),
(10, 50000.00, '1 mois', 'en_attente', '', '2026-06-29 09:14:40', '2026-06-29', '2026-07-29', 5, 2),
(11, 50000.00, '1 mois', 'en_attente', '', '2026-06-29 09:35:06', '2026-06-29', '2026-07-29', 5, 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id_notification` int(11) NOT NULL,
  `id_commercant` int(11) NOT NULL,
  `id_agent` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paiement`
--

CREATE TABLE `paiement` (
  `id_paiement` int(11) NOT NULL,
  `date_paiement` date DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `mode_paiement` varchar(50) DEFAULT NULL,
  `periode` varchar(50) DEFAULT NULL,
  `id_location` int(11) DEFAULT NULL,
  `id_caissier` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produit`
--

CREATE TABLE `produit` (
  `id_produit` int(11) NOT NULL,
  `nom_produit` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `quantite_stock` int(11) DEFAULT 0,
  `unite` varchar(50) DEFAULT NULL,
  `id_commercant` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produit`
--

INSERT INTO `produit` (`id_produit`, `nom_produit`, `description`, `prix_unitaire`, `quantite_stock`, `unite`, `id_commercant`, `created_at`) VALUES
(5, 'Habits', 'Tous les categories disponibles', 10000.00, 24, 'pièce', 5, '2026-06-29 07:00:26');

-- --------------------------------------------------------

--
-- Table structure for table `secteur`
--

CREATE TABLE `secteur` (
  `id_secteur` int(11) NOT NULL,
  `designation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `secteur`
--

INSERT INTO `secteur` (`id_secteur`, `designation`) VALUES
(1, 'Legumes'),
(2, 'Legumes'),
(3, 'Souliers'),
(4, 'Pharmacie');

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_user` int(11) NOT NULL,
  `nom_complet` varchar(150) DEFAULT NULL,
  `sexe` varchar(10) DEFAULT NULL,
  `nationalite` varchar(100) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `nom_user` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_user`, `nom_complet`, `sexe`, `nationalite`, `date_naissance`, `adresse`, `matricule`, `nom_user`, `mot_de_passe`, `telephone`, `email`, `created_at`) VALUES
(1, 'HERVE IRAGI', 'Masculin', 'Congolaise', '1990-06-19', 'Himbi', 'COM20265343', 'Herva', '$2y$10$F5qzDoLlY5hJq.qXYw9d/.WjlzQM2TQ3qjVX3/O2rydWUvBxdue.K', '+243997236154', 'herva@gmail.com', '2026-06-19 16:44:38'),
(2, 'Othniel MUHINDO', 'Masculin', 'Congolaise', '2012-07-21', 'Ndosho', 'COM20262512', 'Othniel', '$2y$10$cZl9U6NWILYodGGulCu5KeHfIwDTs1wValc9uejD1UTuUSJyUPr6i', '+243826487074', 'avenyriaeducation@gmail.com', '2026-06-19 16:48:07'),
(3, 'Geremie', 'Masculin', 'Congolaise', '1998-06-19', 'Goma', 'AG20267466', 'GEREMIE', '$2y$10$PYgL5M4D3uXLZSpXZDu7Vu1XtJJjvOIVYQY9gZs/7jhxRBzK3.Cmy', '0987873546', 'geremie@gmail.com', '2026-06-19 19:08:41'),
(4, 'Glady babikwa', 'Masculin', 'Congolaise', '2002-12-21', 'KATOYI', 'COM20265704', 'Glady babikwa', '$2y$10$vzF1KkXLHKy3a1s0QWpmuOIgIN1DuPfhcvA8Kmt//4xuvCSI81Ivq', '+243991564955', 'bondababikwa@gmail.com', '2026-06-21 12:35:59'),
(5, 'Veloir babikwa', 'Masculin', 'Congolaise', '2004-06-13', 'KATOYI', 'COM20266951', 'Veloir Babikwa', '$2y$10$fsBkRc8GZ.D9wP.vtbpQ..eDm1QMKTWUziPOVLttS/L/n.t7LESQO', '+243991564955', 'veloirbabikwa@gmail.com', '2026-06-21 12:48:33'),
(6, 'GLADY', 'Masculin', 'Congolaise', '1999-06-08', 'Avenue Dikuta, Quartier Kasika, numero 04, Commune de Karisimbi', 'AG20264689', 'GLAD', '$2y$10$KbI.jR0QJq5tNpm.25/46uLyRatZ/zysN9PN2lc.LIK/nMiX/xaFW', '0990505916', 'glady@gmail.com', '2026-06-24 07:26:19'),
(7, 'DOCILE SIKAHWA', 'Féminin', 'Congolaise', '2004-02-10', 'KIMUTI', 'COM20263953', 'DOCILE', '$2y$10$tLKc359I5c2SFNHdCPCDquwrxW6yh7h/mR/0KQ6CxPCwt7J8jsEa.', '0994040345', 'docilesikawa@gmail.com', '2026-06-24 08:49:28'),
(8, 'naomie kanyere', 'f', 'congolaise', '2000-09-13', 'Q.NDOSHO AV.ITALA', 'N001KANYERE', 'naomie', 'nao.2000', '+243976581720', 'naomiekanyere@gmail.com', '2026-06-24 11:24:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrateur`
--
ALTER TABLE `administrateur`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `id_user` (`id_user`);

--
-- Indexes for table `agent_marche`
--
ALTER TABLE `agent_marche`
  ADD PRIMARY KEY (`id_agent`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD UNIQUE KEY `id_user` (`id_user`);

--
-- Indexes for table `caissier`
--
ALTER TABLE `caissier`
  ADD PRIMARY KEY (`id_caissier`),
  ADD UNIQUE KEY `id_user` (`id_user`);

--
-- Indexes for table `commercant`
--
ALTER TABLE `commercant`
  ADD PRIMARY KEY (`id_commercant`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `etalage`
--
ALTER TABLE `etalage`
  ADD PRIMARY KEY (`id_etalage`),
  ADD KEY `id_secteur` (`id_secteur`),
  ADD KEY `id_commercant` (`id_commercant`);

--
-- Indexes for table `location`
--
ALTER TABLE `location`
  ADD PRIMARY KEY (`id_location`),
  ADD KEY `id_commercant` (`id_commercant`),
  ADD KEY `id_etalage` (`id_etalage`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id_notification`),
  ADD KEY `id_commercant` (`id_commercant`),
  ADD KEY `idx_id_agent` (`id_agent`);

--
-- Indexes for table `paiement`
--
ALTER TABLE `paiement`
  ADD PRIMARY KEY (`id_paiement`),
  ADD KEY `id_location` (`id_location`),
  ADD KEY `id_caissier` (`id_caissier`);

--
-- Indexes for table `produit`
--
ALTER TABLE `produit`
  ADD PRIMARY KEY (`id_produit`),
  ADD KEY `id_commercant` (`id_commercant`);

--
-- Indexes for table `secteur`
--
ALTER TABLE `secteur`
  ADD PRIMARY KEY (`id_secteur`);

--
-- Indexes for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD UNIQUE KEY `nom_user` (`nom_user`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrateur`
--
ALTER TABLE `administrateur`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_marche`
--
ALTER TABLE `agent_marche`
  MODIFY `id_agent` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `caissier`
--
ALTER TABLE `caissier`
  MODIFY `id_caissier` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commercant`
--
ALTER TABLE `commercant`
  MODIFY `id_commercant` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `etalage`
--
ALTER TABLE `etalage`
  MODIFY `id_etalage` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `location`
--
ALTER TABLE `location`
  MODIFY `id_location` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id_notification` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `paiement`
--
ALTER TABLE `paiement`
  MODIFY `id_paiement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produit`
--
ALTER TABLE `produit`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `secteur`
--
ALTER TABLE `secteur`
  MODIFY `id_secteur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `administrateur`
--
ALTER TABLE `administrateur`
  ADD CONSTRAINT `administrateur_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `agent_marche`
--
ALTER TABLE `agent_marche`
  ADD CONSTRAINT `agent_marche_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `caissier`
--
ALTER TABLE `caissier`
  ADD CONSTRAINT `caissier_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `commercant`
--
ALTER TABLE `commercant`
  ADD CONSTRAINT `commercant_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `etalage`
--
ALTER TABLE `etalage`
  ADD CONSTRAINT `etalage_ibfk_1` FOREIGN KEY (`id_secteur`) REFERENCES `secteur` (`id_secteur`),
  ADD CONSTRAINT `etalage_ibfk_2` FOREIGN KEY (`id_commercant`) REFERENCES `commercant` (`id_commercant`) ON DELETE SET NULL;

--
-- Constraints for table `location`
--
ALTER TABLE `location`
  ADD CONSTRAINT `location_ibfk_1` FOREIGN KEY (`id_commercant`) REFERENCES `commercant` (`id_commercant`),
  ADD CONSTRAINT `location_ibfk_2` FOREIGN KEY (`id_etalage`) REFERENCES `etalage` (`id_etalage`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`id_commercant`) REFERENCES `commercant` (`id_commercant`);

--
-- Constraints for table `paiement`
--
ALTER TABLE `paiement`
  ADD CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (`id_location`) REFERENCES `location` (`id_location`),
  ADD CONSTRAINT `paiement_ibfk_2` FOREIGN KEY (`id_caissier`) REFERENCES `caissier` (`id_caissier`);

--
-- Constraints for table `produit`
--
ALTER TABLE `produit`
  ADD CONSTRAINT `produit_ibfk_1` FOREIGN KEY (`id_commercant`) REFERENCES `commercant` (`id_commercant`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
