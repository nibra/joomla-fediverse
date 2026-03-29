-- ---------------------------------------------------------------------------
-- pkg_fediverse / com_fediverse
-- Schema update: ensure post-baseline tables exist on upgraded installs
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__fediverse_delivery_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `outbox_id` BIGINT UNSIGNED NOT NULL,
  `local_actor_id` BIGINT UNSIGNED NOT NULL,
  `target_inbox_url` VARCHAR(512) NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `state` ENUM('queued','inflight','delivered','failed','dead') NOT NULL DEFAULT 'queued',
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_fed_dq_due` (`state`, `next_attempt_at`),
  KEY `idx_fed_dq_outbox` (`outbox_id`),
  KEY `idx_fed_dq_actor` (`local_actor_id`),
  UNIQUE KEY `uniq_fed_dq_outbox_target` (`outbox_id`, `target_inbox_url`),

  CONSTRAINT `fk_fed_dq_outbox`
    FOREIGN KEY (`outbox_id`)
    REFERENCES `#__fediverse_outbox` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_fed_dq_local_actor`
    FOREIGN KEY (`local_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_user_settings` (
  `user_id` INT UNSIGNED NOT NULL,
  `federation_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `bio` TEXT NULL,
  `website` VARCHAR(512) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_content_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `context` VARCHAR(190) NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `federate` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_content_context_item` (`context`(100), `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_domain_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` VARCHAR(253) NOT NULL,
  `policy` ENUM('allow','block') NOT NULL DEFAULT 'block',
  `reason` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_domain_policy` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_webhooks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `target_url` VARCHAR(768) NOT NULL,
  `secret` VARCHAR(255) NOT NULL,
  `events` TEXT NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `last_delivery_at` DATETIME NULL,
  `last_delivery_status` SMALLINT NULL,
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_fed_webhooks_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_oauth_clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(191) NOT NULL,
  `client_secret` VARCHAR(255) NOT NULL,
  `redirect_uris` TEXT NOT NULL,
  `scopes` VARCHAR(255) NOT NULL DEFAULT '',
  `name` VARCHAR(190) NULL,
  `website` VARCHAR(255) NULL,
  `is_confidential` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_oauth_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_oauth_authcodes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `code_hash` CHAR(64) NOT NULL,
  `redirect_uri` VARCHAR(512) NOT NULL,
  `scope` VARCHAR(255) NOT NULL DEFAULT '',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_oauth_code_hash` (`code_hash`),
  KEY `idx_fed_oauth_authcodes_client` (`client_id`),
  KEY `idx_fed_oauth_authcodes_user` (`user_id`),

  CONSTRAINT `fk_fed_oauth_authcodes_client`
    FOREIGN KEY (`client_id`)
    REFERENCES `#__fediverse_oauth_clients` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__fediverse_oauth_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `access_token_hash` CHAR(64) NOT NULL,
  `refresh_token_hash` CHAR(64) NULL,
  `scope` VARCHAR(255) NOT NULL DEFAULT '',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_oauth_access_hash` (`access_token_hash`),
  UNIQUE KEY `uniq_fed_oauth_refresh_hash` (`refresh_token_hash`),
  KEY `idx_fed_oauth_tokens_client` (`client_id`),
  KEY `idx_fed_oauth_tokens_user` (`user_id`),

  CONSTRAINT `fk_fed_oauth_tokens_client`
    FOREIGN KEY (`client_id`)
    REFERENCES `#__fediverse_oauth_clients` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
