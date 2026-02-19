-- v1.7-18: 서울시 버스 공공데이터(노선/노선-정류장) 적재 테이블
-- 목적: inbound CSV를 DB에 UPSERT 가능한 최소 마스터 구성

CREATE TABLE IF NOT EXISTS seoul_bus_route_master (
  route_id        BIGINT UNSIGNED NOT NULL COMMENT '서울시 노선ID',
  route_name      VARCHAR(120) NOT NULL DEFAULT '' COMMENT '노선명',
  route_type      VARCHAR(30) NULL DEFAULT NULL COMMENT '노선유형',
  start_stop_name VARCHAR(120) NULL DEFAULT NULL COMMENT '기점 정류장명',
  end_stop_name   VARCHAR(120) NULL DEFAULT NULL COMMENT '종점 정류장명',
  term_min        INT UNSIGNED NULL DEFAULT NULL COMMENT '배차간격(분)',
  first_bus_time  VARCHAR(20) NULL DEFAULT NULL COMMENT '첫차시간',
  last_bus_time   VARCHAR(20) NULL DEFAULT NULL COMMENT '막차시간',
  corp_name       VARCHAR(160) NULL DEFAULT NULL COMMENT '운수사',
  raw_json        JSON NULL DEFAULT NULL COMMENT '원본행 JSON',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (route_id),
  KEY ix_route_name (route_name(60))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='서울시 버스 노선 마스터';

CREATE TABLE IF NOT EXISTS seoul_bus_route_stop_master (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  route_id        BIGINT UNSIGNED NOT NULL COMMENT '서울시 노선ID',
  seq_in_route    INT UNSIGNED NOT NULL COMMENT '노선 내 순번',
  stop_id         BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '정류장ID',
  stop_name       VARCHAR(120) NOT NULL DEFAULT '' COMMENT '정류장명',
  ars_id          VARCHAR(30) NULL DEFAULT NULL COMMENT 'ARS ID',
  direction_text  VARCHAR(40) NULL DEFAULT NULL COMMENT '방향/상행하행',
  raw_json        JSON NULL DEFAULT NULL COMMENT '원본행 JSON',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_route_seq (route_id, seq_in_route),
  KEY ix_route_stop (route_id, stop_id),
  KEY ix_route_stop_name (stop_name(60))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='서울시 버스 노선-정류장 마스터';
