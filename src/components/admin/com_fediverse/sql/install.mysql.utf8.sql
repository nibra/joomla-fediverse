-- ---------------------------------------------------------------------------
-- pkg_fediverse / com_fediverse
-- Install schema (MySQL)
-- NOTE: Joomla will replace the #__ prefix with the configured table prefix.
-- ---------------------------------------------------------------------------

-- =========================
-- Actors
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_actors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('local','remote') NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `handle` VARCHAR(190) NOT NULL,
  `preferred_username` VARCHAR(190) NOT NULL,
  `uri` VARCHAR(512) NOT NULL,
  `inbox_url` VARCHAR(512) NOT NULL,
  `outbox_url` VARCHAR(512) NOT NULL,
  `shared_inbox_url` VARCHAR(512) NULL,
  `public_key_pem` TEXT NULL,
  `profile_json` MEDIUMTEXT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `actor_type` ENUM('Person','Service') NOT NULL DEFAULT 'Person',
  `object_type` ENUM('Note','Article','Image','Video') NOT NULL DEFAULT 'Note',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uniq_fed_actors_handle` (`handle`),
  UNIQUE KEY `uniq_fed_actors_uri` (`uri`),

  -- Ensures uniqueness for local actors while allowing many NULLs for remotes.
  UNIQUE KEY `uniq_fed_actors_local_user` (`user_id`, `type`),

  KEY `idx_fed_actors_type` (`type`),
  KEY `idx_fed_actors_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - The UNIQUE(user_id, type) trick ensures:
--   - remote rows can have user_id NULL (allowed multiple times)
--   - local rows must have user_id unique among local (because type='local')
-- - If you want to strictly forbid user_id for remote, add a CHECK constraint
--   (MySQL 8.0.16+ enforces CHECK):
--   CHECK ( (type='remote' AND user_id IS NULL) OR (type='local' AND user_id IS NOT NULL) )

-- =========================
-- Keys (per actor)
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `key_id_uri` VARCHAR(768) NOT NULL,
  `private_key_enc` MEDIUMTEXT NOT NULL,
  `public_key_pem` TEXT NOT NULL,
  `status` ENUM('active','rotated','revoked') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rotated_at` DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_keys_keyid` (`key_id_uri`),
  KEY `idx_fed_keys_actor` (`actor_id`),
  KEY `idx_fed_keys_status` (`status`),

  CONSTRAINT `fk_fed_keys_actor`
    FOREIGN KEY (`actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Followers (follow graph)
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_followers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `local_actor_id` BIGINT UNSIGNED NOT NULL,
  `remote_actor_id` BIGINT UNSIGNED NOT NULL,
  `state` ENUM('pending','accepted','blocked') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_followers_edge` (`local_actor_id`, `remote_actor_id`),
  KEY `idx_fed_followers_local` (`local_actor_id`),
  KEY `idx_fed_followers_remote` (`remote_actor_id`),
  KEY `idx_fed_followers_state` (`state`),

  CONSTRAINT `fk_fed_followers_local_actor`
    FOREIGN KEY (`local_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_fed_followers_remote_actor`
    FOREIGN KEY (`remote_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Outbox
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_outbox` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `local_actor_id` BIGINT UNSIGNED NOT NULL,
  `activity_id_uri` VARCHAR(512) NOT NULL,
  `type` VARCHAR(64) NOT NULL,
  `raw_json` MEDIUMTEXT NOT NULL,
  `state` ENUM('queued','delivering','delivered','failed') NOT NULL DEFAULT 'queued',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_outbox_activityid` (`activity_id_uri`),
  KEY `idx_fed_outbox_actor_created` (`local_actor_id`, `created_at`),
  KEY `idx_fed_outbox_state` (`state`),
  KEY `idx_fed_outbox_type` (`type`),

  CONSTRAINT `fk_fed_outbox_local_actor`
    FOREIGN KEY (`local_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Inbox
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_inbox` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `local_actor_id` BIGINT UNSIGNED NULL,  -- NULL means shared inbox
  `activity_id_uri` VARCHAR(512) NULL,
  `type` VARCHAR(64) NOT NULL,
  `raw_json` MEDIUMTEXT NOT NULL,
  `signature_valid` TINYINT(1) NOT NULL DEFAULT 0,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  `status` ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
  `error` TEXT NULL,

  PRIMARY KEY (`id`),

  -- MySQL allows multiple NULLs in UNIQUE indexes.
  UNIQUE KEY `uniq_fed_inbox_activityid` (`activity_id_uri`),

  KEY `idx_fed_inbox_actor_received` (`local_actor_id`, `received_at`),
  KEY `idx_fed_inbox_status` (`status`),
  KEY `idx_fed_inbox_sig` (`signature_valid`),
  KEY `idx_fed_inbox_type` (`type`),

  CONSTRAINT `fk_fed_inbox_local_actor`
    FOREIGN KEY (`local_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Inbound activities
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_inbound_activities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inbox_id` BIGINT UNSIGNED NOT NULL,
  `local_actor_id` BIGINT UNSIGNED NULL,
  `remote_actor_id` BIGINT UNSIGNED NULL,
  `activity_id_uri` VARCHAR(512) NULL,
  `activity_type` VARCHAR(64) NOT NULL,
  `object_uri` VARCHAR(512) NULL,
  `object_id` VARCHAR(190) NULL,
  `raw_json` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  UNIQUE KEY `uniq_fed_inbound_activityid` (`activity_id_uri`),
  KEY `idx_fed_inbound_inbox` (`inbox_id`),
  KEY `idx_fed_inbound_actor` (`local_actor_id`),
  KEY `idx_fed_inbound_remote_actor` (`remote_actor_id`),
  KEY `idx_fed_inbound_object` (`object_id`),
  KEY `idx_fed_inbound_type` (`activity_type`),

  CONSTRAINT `fk_fed_inbound_inbox`
    FOREIGN KEY (`inbox_id`)
    REFERENCES `#__fediverse_inbox` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_fed_inbound_local_actor`
    FOREIGN KEY (`local_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT `fk_fed_inbound_remote_actor`
    FOREIGN KEY (`remote_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Media
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_media` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `local_actor_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `relative_path` VARCHAR(512) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `mime_type` VARCHAR(190) NOT NULL,
  `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_fed_media_actor` (`local_actor_id`),
  KEY `idx_fed_media_user` (`user_id`),

  CONSTRAINT `fk_fed_media_actor`
    FOREIGN KEY (`local_actor_id`)
    REFERENCES `#__fediverse_actors` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Delivery queue
-- =========================
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

    -- Prevent obvious duplicates (same activity -> same inbox). Note:
    -- If you want to allow re-queueing later, drop this and dedupe in code.
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

-- =========================
-- User settings
-- =========================
CREATE TABLE IF NOT EXISTS `#__fediverse_user_settings` (
  `user_id` INT UNSIGNED NOT NULL,
  `federation_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `bio` TEXT NULL,
  `website` VARCHAR(512) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Content settings
-- =========================
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

-- =========================
-- Domain policies
-- =========================
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

-- =========================
-- OAuth auth codes
-- =========================
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

-- =========================
-- OAuth tokens
-- =========================
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
