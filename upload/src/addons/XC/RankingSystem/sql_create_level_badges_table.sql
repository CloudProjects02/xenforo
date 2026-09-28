-- SQL script to create the xc_level_badges table for XC/RankingSystem addon
-- Run this script in your database to create the missing table

CREATE TABLE `xc_level_badges` (
  `level_badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `level_id` int(11) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `file_ex` varchar(100) NOT NULL DEFAULT 'svg',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_date` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`level_badge_id`),
  KEY `level_id` (`level_id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Add foreign key constraint if you want referential integrity
-- ALTER TABLE `xc_level_badges` ADD CONSTRAINT `fk_level_badges_level_id` 
-- FOREIGN KEY (`level_id`) REFERENCES `xc_ranking_levels` (`level_id`) ON DELETE CASCADE;
