-- v1.7-06 Approver role + approval audit. Run on PC (Workbench).

-- Pre-check (optional): SHOW COLUMNS FROM app_users LIKE 'role';

-- A) app_users.role (default 'user')
ALTER TABLE app_users
  ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'user' AFTER display_name;

-- B) app_alert_approvals
CREATE TABLE IF NOT EXISTS app_alert_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  alert_event_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_approvals_event_created (alert_event_id, created_at),
  INDEX idx_approvals_actor_created (actor_user_id, created_at),
  CONSTRAINT fk_approvals_event FOREIGN KEY (alert_event_id) REFERENCES app_alert_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_approvals_actor FOREIGN KEY (actor_user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback (확인 필요):
-- ALTER TABLE app_users DROP COLUMN role;
-- DROP TABLE IF EXISTS app_alert_approvals;
