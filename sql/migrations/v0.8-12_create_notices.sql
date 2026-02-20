-- v0.8-12: Notice/Event for user-facing bulletin (MVP)
-- SoT: docs/SOT/NOTICE_EVENT.md

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notices (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category      VARCHAR(16) NOT NULL DEFAULT 'notice' COMMENT 'notice,event',
  label         VARCHAR(16) NOT NULL DEFAULT '공지' COMMENT '공지,안내,이벤트',
  status        VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT 'draft,published,archived',
  is_pinned     TINYINT(1) NOT NULL DEFAULT 0,
  title         VARCHAR(200) NOT NULL DEFAULT '',
  body_md       MEDIUMTEXT NULL,
  starts_at     DATETIME NULL DEFAULT NULL,
  ends_at       DATETIME NULL DEFAULT NULL,
  published_at  DATETIME NULL DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_notices_category (category),
  INDEX idx_notices_status (status),
  INDEX idx_notices_visibility (status, starts_at, ends_at),
  INDEX idx_notices_sort (is_pinned, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='User notices and events';
