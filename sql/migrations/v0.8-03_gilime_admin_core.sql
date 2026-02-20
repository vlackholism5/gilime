-- v0.8-03: Gilime Admin core — issues, shuttle_routes, shuttle_stops (MVP)
-- SoT: docs/SOT/Gilime_Admin_ERD_MVP_v1.md

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- issues
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS issues (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title             VARCHAR(255) NOT NULL DEFAULT '',
  severity          VARCHAR(32) NOT NULL DEFAULT 'medium' COMMENT 'low, medium, high, critical',
  status            VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'draft, active, ended',
  start_at          DATETIME NULL DEFAULT NULL,
  end_at            DATETIME NULL DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_issues_status (status),
  INDEX idx_issues_start_end (start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Transport issues (draft/active/ended)';

-- -----------------------------------------------------------------------------
-- issue_lines
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS issue_lines (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  issue_id          BIGINT UNSIGNED NOT NULL,
  line_code         VARCHAR(16) NOT NULL DEFAULT '',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_issue_lines_issue_line (issue_id, line_code),
  INDEX idx_issue_lines_issue (issue_id),
  CONSTRAINT fk_issue_lines_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Affected line codes per issue';

-- -----------------------------------------------------------------------------
-- issue_modes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS issue_modes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  issue_id          BIGINT UNSIGNED NOT NULL,
  mode              VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'subway, bus, shuttle',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_issue_modes_issue_mode (issue_id, mode),
  INDEX idx_issue_modes_issue (issue_id),
  CONSTRAINT fk_issue_modes_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Affected modes per issue';

-- -----------------------------------------------------------------------------
-- shuttle_routes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shuttle_routes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  issue_id          BIGINT UNSIGNED NOT NULL,
  route_name        VARCHAR(128) NOT NULL DEFAULT '',
  headway_min       INT UNSIGNED NULL DEFAULT NULL COMMENT 'minutes between departures',
  service_hours     VARCHAR(255) NULL DEFAULT NULL COMMENT 'e.g. 06:00-23:00',
  status            VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'draft, active, ended',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_shuttle_routes_issue_name (issue_id, route_name),
  INDEX idx_shuttle_routes_issue (issue_id),
  INDEX idx_shuttle_routes_status (status),
  CONSTRAINT fk_shuttle_routes_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Shuttle routes per issue';

-- -----------------------------------------------------------------------------
-- shuttle_stops
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shuttle_stops (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  shuttle_route_id  BIGINT UNSIGNED NOT NULL,
  stop_order        INT UNSIGNED NOT NULL COMMENT '1-based sequence',
  stop_id           VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'external stop id',
  stop_name         VARCHAR(255) NOT NULL DEFAULT '',
  lat               DECIMAL(10,7) NULL DEFAULT NULL,
  lng               DECIMAL(10,7) NULL DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_shuttle_stops_route_order (shuttle_route_id, stop_order),
  UNIQUE KEY uk_shuttle_stops_route_stop  (shuttle_route_id, stop_id),
  INDEX idx_shuttle_stops_route (shuttle_route_id),
  CONSTRAINT fk_shuttle_stops_route FOREIGN KEY (shuttle_route_id) REFERENCES shuttle_routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stop sequence per shuttle route';
