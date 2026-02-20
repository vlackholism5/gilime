-- v0.8-13: issue routing policy targets (BLOCK/PENALTY/BOOST)
-- SoT: docs/SOT/ROUTING_ISSUE_WEIGHTING_MVP.md

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS issue_targets (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  issue_id       BIGINT UNSIGNED NOT NULL,
  target_type    VARCHAR(16) NOT NULL DEFAULT 'route' COMMENT 'route,line,station',
  target_id      VARCHAR(100) NOT NULL DEFAULT '',
  policy_type    VARCHAR(16) NOT NULL DEFAULT 'penalty' COMMENT 'block,penalty,boost',
  severity       VARCHAR(16) NOT NULL DEFAULT 'medium' COMMENT 'low,medium,high,critical',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_issue_targets_policy (issue_id, target_type, target_id, policy_type),
  INDEX idx_issue_targets_issue (issue_id),
  INDEX idx_issue_targets_target (target_type, target_id),
  CONSTRAINT fk_issue_targets_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Issue targets for route policy application';
