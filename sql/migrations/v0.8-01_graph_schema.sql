-- v0.8-01: Minimal graph + subway G1 schema (controllable baseline)
-- InnoDB, utf8mb4. PK: BIGINT. SoT: docs/SOT/06_DATA_PATHS_AND_FILES.md, STEP 3.

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- graph_versions: versioned graph snapshots
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS graph_versions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  version_label     VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'e.g. v1, 20260220',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_graph_versions_label (version_label(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Graph version snapshots';

-- -----------------------------------------------------------------------------
-- graph_nodes: nodes per graph version
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS graph_nodes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  graph_version_id  BIGINT UNSIGNED NOT NULL,
  node_type        VARCHAR(32) NOT NULL DEFAULT '',
  external_ref     VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'external system id',
  name             VARCHAR(255) NOT NULL DEFAULT '',
  lat              DECIMAL(10,7) NULL DEFAULT NULL,
  lon              DECIMAL(10,7) NULL DEFAULT NULL,
  meta_json        JSON NULL DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_graph_nodes_version_type (graph_version_id, node_type),
  INDEX idx_graph_nodes_version_ref  (graph_version_id, external_ref(64)),
  CONSTRAINT fk_graph_nodes_version FOREIGN KEY (graph_version_id) REFERENCES graph_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Graph nodes per version';

-- -----------------------------------------------------------------------------
-- graph_edges: edges per graph version
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS graph_edges (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  graph_version_id  BIGINT UNSIGNED NOT NULL,
  edge_type        VARCHAR(32) NOT NULL DEFAULT '',
  from_node_id     BIGINT UNSIGNED NOT NULL,
  to_node_id       BIGINT UNSIGNED NOT NULL,
  distance_m       DECIMAL(12,2) NULL DEFAULT NULL,
  time_sec         INT UNSIGNED NULL DEFAULT NULL,
  cost             DECIMAL(12,4) NULL DEFAULT NULL,
  service_id       VARCHAR(64) NULL DEFAULT NULL,
  meta_json        JSON NULL DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_graph_edges_version_type   (graph_version_id, edge_type),
  INDEX idx_graph_edges_version_from   (graph_version_id, from_node_id),
  CONSTRAINT fk_graph_edges_version FOREIGN KEY (graph_version_id) REFERENCES graph_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_graph_edges_from FOREIGN KEY (from_node_id) REFERENCES graph_nodes(id) ON DELETE CASCADE,
  CONSTRAINT fk_graph_edges_to   FOREIGN KEY (to_node_id)   REFERENCES graph_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Graph edges per version';

-- -----------------------------------------------------------------------------
-- subway_stations_master: subway station master (G1)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subway_stations_master (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_cd        VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'station code',
  station_name      VARCHAR(120) NOT NULL DEFAULT '',
  line_code         VARCHAR(16) NOT NULL DEFAULT '',
  fr_code           VARCHAR(16) NULL DEFAULT NULL COMMENT 'e.g. station number on line',
  lat               DECIMAL(10,7) NULL DEFAULT NULL,
  lon               DECIMAL(10,7) NULL DEFAULT NULL,
  osm_full_id       VARCHAR(32) NULL DEFAULT NULL,
  match_confidence  DECIMAL(5,4) NULL DEFAULT NULL COMMENT '0~1',
  meta_json         JSON NULL DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_subway_stations_cd (station_cd),
  INDEX idx_subway_stations_name (station_name(60)),
  INDEX idx_subway_stations_line (line_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subway stations master G1';

-- -----------------------------------------------------------------------------
-- subway_edges_g1: subway segment edges (G1)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subway_edges_g1 (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  line_code         VARCHAR(16) NOT NULL DEFAULT '',
  from_station_cd   VARCHAR(32) NOT NULL DEFAULT '',
  to_station_cd     VARCHAR(32) NOT NULL DEFAULT '',
  distance_m        DECIMAL(12,2) NULL DEFAULT NULL,
  time_sec          INT UNSIGNED NULL DEFAULT NULL,
  meta_json         JSON NULL DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_subway_edges_g1_line (line_code),
  INDEX idx_subway_edges_g1_from (line_code, from_station_cd),
  INDEX idx_subway_edges_g1_to   (line_code, to_station_cd)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subway edges G1';

-- =============================================================================
-- Validation queries (run after migration; do not execute inside Cursor)
-- =============================================================================

-- SHOW TABLES LIKE 'graph_%';
-- SHOW TABLES LIKE 'subway_%';

-- DESCRIBE graph_versions;
-- DESCRIBE graph_nodes;
-- DESCRIBE graph_edges;
-- DESCRIBE subway_stations_master;
-- DESCRIBE subway_edges_g1;

-- SELECT COUNT(*) AS cnt FROM graph_versions;
-- SELECT COUNT(*) AS cnt FROM graph_nodes;
-- SELECT COUNT(*) AS cnt FROM graph_edges;
-- SELECT COUNT(*) AS cnt FROM subway_stations_master;
-- SELECT COUNT(*) AS cnt FROM subway_edges_g1;
-- (Expected 0 initially.)
