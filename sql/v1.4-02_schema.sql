-- v1.4-02 MVP2: app_* tables (DDL only; run on PC)
-- Charset: utf8mb4. PK: BIGINT. created_at/updated_at on all.

SET NAMES utf8mb4;

-- User (anonymous/guest supported; email nullable for MVP2 temporary auth)
CREATE TABLE IF NOT EXISTS app_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) DEFAULT NULL,
  password_hash VARCHAR(255) DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_app_users_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session (MVP2: cookie session_id stored here)
CREATE TABLE IF NOT EXISTS app_user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  session_token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_app_user_sessions_token (session_token),
  INDEX idx_app_user_sessions_user_id (user_id),
  INDEX idx_app_user_sessions_expires (expires_at),
  CONSTRAINT fk_app_user_sessions_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscriptions (route/doc target; one row per user+target)
CREATE TABLE IF NOT EXISTS app_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  target_type VARCHAR(32) NOT NULL,
  target_id VARCHAR(64) NOT NULL,
  alert_type VARCHAR(32) NOT NULL DEFAULT 'strike,event,update',
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_app_subscriptions_user_target (user_id, target_type, target_id),
  INDEX idx_app_subscriptions_user_id (user_id),
  INDEX idx_app_subscriptions_is_active (is_active),
  CONSTRAINT fk_app_subscriptions_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert events (strike/event/update); content_hash for idempotent ingest
CREATE TABLE IF NOT EXISTS app_alert_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(32) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT,
  ref_type VARCHAR(32) DEFAULT NULL,
  ref_id BIGINT UNSIGNED DEFAULT NULL,
  content_hash VARCHAR(64) DEFAULT NULL,
  published_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_app_alert_events_content_hash (content_hash),
  INDEX idx_app_alert_events_created_at (created_at),
  INDEX idx_app_alert_events_event_type (event_type),
  INDEX idx_app_alert_events_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert delivery log (who received which event)
CREATE TABLE IF NOT EXISTS app_alert_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  alert_event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(32) NOT NULL DEFAULT 'inapp',
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_app_alert_deliveries_event_id (alert_event_id),
  INDEX idx_app_alert_deliveries_user_id (user_id),
  INDEX idx_app_alert_deliveries_created_at (created_at),
  CONSTRAINT fk_app_alert_deliveries_event FOREIGN KEY (alert_event_id) REFERENCES app_alert_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_alert_deliveries_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
