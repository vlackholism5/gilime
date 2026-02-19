-- v1.4-02: app_subscriptions만 생성 (다른 app_* 테이블은 이미 있을 때 사용)
-- 실행: Workbench에서 이 파일만 실행

SET NAMES utf8mb4;

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
