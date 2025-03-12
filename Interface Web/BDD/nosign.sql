-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : mer. 12 mars 2025 à 13:20
-- Version du serveur : 11.5.2-MariaDB
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `nosign`
--

-- --------------------------------------------------------

--
-- Structure de la table `etudiants`
--

DROP TABLE IF EXISTS `etudiants`;
CREATE TABLE IF NOT EXISTS `etudiants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `uid` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `etudiants`
--

INSERT INTO `etudiants` (`id`, `nom`, `prenom`, `uid`) VALUES
(1, 'Alexandre', 'Louis', '04113b827e6780'),
(2, 'Brunel', 'Enzo', '04315f7a816780'),
(3, 'Riccardi', 'Marino', '041e11927e6780'),
(4, 'Yorn', 'Vicheth', '041e0d927e6780');

-- --------------------------------------------------------

--
-- Structure de la table `presences`
--

DROP TABLE IF EXISTS `presences`;
CREATE TABLE IF NOT EXISTS `presences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) NOT NULL,
  `date_scan` date NOT NULL DEFAULT curdate(),
  `heure_scan` time NOT NULL DEFAULT curtime(),
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `presences`
--

INSERT INTO `presences` (`id`, `uid`, `date_scan`, `heure_scan`) VALUES
(2, '04113b827e6780', '2025-03-09', '14:04:22'),
(3, '04315f7a816780', '2025-03-08', '14:08:13'),
(4, '041e11927e6780', '2025-03-09', '14:08:59'),
(16, '041e11927e6780', '2025-03-10', '14:46:48'),
(23, '041e0d927e6780', '2025-03-11', '07:56:38'),
(24, '041e0d927e6780', '2025-03-11', '15:57:52'),
(29, '04315f7a816780', '2025-03-11', '10:43:46'),
(32, '041e11927e6780', '2025-03-11', '14:57:11'),
(34, '04113b827e6780', '2025-03-11', '15:31:03'),
(35, '04315f7a816780', '2025-03-11', '15:31:06'),
(38, '041e11927e6780', '2025-03-12', '12:43:56'),
(39, '041e0d927e6780', '2025-03-12', '12:43:56'),
(43, '04113b827e6780', '2025-03-12', '12:51:07'),
(44, '04315f7a816780', '2025-03-12', '12:51:08'),
(45, '04113b827e6780', '2025-03-12', '08:58:00'),
(46, '04315f7a816780', '2025-03-12', '07:00:00');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('professeur','entreprise','administrateur') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `login`, `password_hash`, `email`, `role`, `created_at`) VALUES
(5, 'prof1', 'bc4b3efcaeefab8fbe6d4b20f3b9c865fd3d9aafad2bf2828e0f7149816aac30', 'karine.bergeron@etu.univ-grenoble-alpes.fr', 'professeur', '2025-03-10 15:22:12'),
(3, 'admin', 'df78b0ea5ffe97ecc62dcf13b0776e6bd73f511d119966e8dd8025b84fccc7e7', 'admin@exemple.com', 'administrateur', '2025-02-05 10:14:30'),
(6, 'JAY', 'bc4b3efcaeefab8fbe6d4b20f3b9c865fd3d9aafad2bf2828e0f7149816aac30', 'vincent.jay@koesio.com', 'entreprise', '2025-03-11 08:48:13');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `presences`
--
ALTER TABLE `presences`
  ADD CONSTRAINT `presences_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `etudiants` (`uid`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
