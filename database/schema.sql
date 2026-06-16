-- IT OpsDesk database schema
-- Import this file in phpMyAdmin, then run seed.sql or seed.php

CREATE DATABASE IF NOT EXISTS `tasks_schema`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `tasks_schema`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity`;
DROP TABLE IF EXISTS `assets`;
DROP TABLE IF EXISTS `incidents`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `organizations`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `organizations` (
  `id`          VARCHAR(50)  NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `short_name`  VARCHAR(20)  NOT NULL,
  `color`       VARCHAR(20)  NOT NULL DEFAULT '#6366f1',
  `contact`     VARCHAR(255) NOT NULL DEFAULT '',
  `site_count`  INT          NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks` (
  `id`          VARCHAR(50)  NOT NULL,
  `org_id`      VARCHAR(50)  NOT NULL,
  `title`       VARCHAR(500) NOT NULL,
  `description` TEXT,
  `category`    ENUM('hardware','software','network','security','email','access','account','other') NOT NULL DEFAULT 'other',
  `priority`    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status`      ENUM('open','in_progress','on_hold','resolved','closed') NOT NULL DEFAULT 'open',
  `assignee`    VARCHAR(255) NOT NULL DEFAULT '',
  `requester`   VARCHAR(255) NOT NULL DEFAULT '',
  `due_date`    DATE,
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `tags`        JSON,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_org` (`org_id`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_due` (`due_date`),
  CONSTRAINT `fk_tasks_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `incidents` (
  `id`               VARCHAR(50)  NOT NULL,
  `org_id`           VARCHAR(50)  NOT NULL,
  `title`            VARCHAR(500) NOT NULL,
  `summary`          TEXT,
  `severity`         ENUM('info','minor','major','outage') NOT NULL DEFAULT 'minor',
  `status`           ENUM('investigating','mitigating','resolved') NOT NULL DEFAULT 'investigating',
  `impacted_service` VARCHAR(255) NOT NULL DEFAULT '',
  `reported_by`      VARCHAR(255) NOT NULL DEFAULT '',
  `started_at`       DATETIME NOT NULL,
  `resolved_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_incidents_org` (`org_id`),
  KEY `idx_incidents_status` (`status`),
  CONSTRAINT `fk_incidents_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assets` (
  `id`               VARCHAR(50)  NOT NULL,
  `org_id`           VARCHAR(50)  NOT NULL,
  `name`             VARCHAR(255) NOT NULL,
  `type`             ENUM('laptop','server','network','printer','mobile','other') NOT NULL DEFAULT 'other',
  `serial_number`    VARCHAR(100) NOT NULL DEFAULT '',
  `location`         VARCHAR(255) NOT NULL DEFAULT '',
  `assigned_to`      VARCHAR(255) NOT NULL DEFAULT '',
  `status`           ENUM('operational','degraded','maintenance','retired') NOT NULL DEFAULT 'operational',
  `purchase_date`    DATE,
  `last_maintenance` DATE,
  `next_maintenance` VARCHAR(20) NOT NULL DEFAULT 'N/A',
  PRIMARY KEY (`id`),
  KEY `idx_assets_org` (`org_id`),
  CONSTRAINT `fk_assets_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity` (
  `id`           VARCHAR(50)  NOT NULL,
  `type`         ENUM('task','incident','asset','maintenance') NOT NULL,
  `action`       VARCHAR(100) NOT NULL,
  `entity_title` VARCHAR(500) NOT NULL,
  `org_id`       VARCHAR(50)  NOT NULL,
  `actor`        VARCHAR(255) NOT NULL DEFAULT '',
  `timestamp`    DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_org` (`org_id`),
  KEY `idx_activity_ts` (`timestamp`),
  CONSTRAINT `fk_activity_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
