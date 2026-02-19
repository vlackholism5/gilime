-- v1.7-21: 임시 셔틀버스 노선·정류장 테이블 (길찾기 포함용)
-- 설계: docs/operations/SHUTTLE_TEMP_DESIGN_v1_7.md

CREATE TABLE IF NOT EXISTS shuttle_temp_route (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  route_label       VARCHAR(80) NOT NULL DEFAULT '' COMMENT '노선명 ex) 동작1(임시), 서초1(임시)',
  district_name     VARCHAR(40) NULL DEFAULT NULL COMMENT '자치구명',
  source_doc_id     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '원본 PDF (shuttle_source_doc)',
  first_bus_time    VARCHAR(20) NULL DEFAULT NULL COMMENT '첫차 ex) 06:00',
  last_bus_time     VARCHAR(20) NULL DEFAULT NULL COMMENT '막차 ex) 22:00',
  headway_min       VARCHAR(30) NULL DEFAULT NULL COMMENT '배차간격 ex) 20분, 15~20분',
  distance_km       DECIMAL(6,2) NULL DEFAULT NULL COMMENT '운행거리 km',
  bus_count         INT UNSIGNED NULL DEFAULT NULL COMMENT '배정대수',
  run_count         INT UNSIGNED NULL DEFAULT NULL COMMENT '운행횟수',
  raw_json          JSON NULL DEFAULT NULL COMMENT '원본 추출 JSON',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_route_label (route_label),
  KEY ix_district (district_name(20)),
  KEY ix_source_doc (source_doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='임시 셔틀버스 노선 마스터';

CREATE TABLE IF NOT EXISTS shuttle_temp_route_stop (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  temp_route_id     BIGINT UNSIGNED NOT NULL COMMENT 'FK shuttle_temp_route.id',
  seq_in_route      INT UNSIGNED NOT NULL COMMENT '노선 내 순번',
  raw_stop_name     VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'PDF 원본 정류장명',
  stop_id           BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'seoul_bus_stop_master 매칭 ID',
  stop_name         VARCHAR(120) NULL DEFAULT NULL COMMENT '매칭된 정류장명',
  match_method      VARCHAR(40) NULL DEFAULT NULL COMMENT 'exact, like_prefix, id_extract, manual',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_temp_route_seq (temp_route_id, seq_in_route),
  KEY ix_temp_route (temp_route_id),
  KEY ix_stop_id (stop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='임시 셔틀버스 노선-정류장 (길찾기 그래프용)';
