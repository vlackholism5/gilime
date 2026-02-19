-- v0.6-10: 서울시 버스 정류장 마스터 (inbound CSV → DB)
-- 입력: data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv (euc-kr)

CREATE TABLE IF NOT EXISTS seoul_bus_stop_master (
  stop_id        BIGINT UNSIGNED NOT NULL COMMENT '정류장ID',
  stop_name      VARCHAR(100) NOT NULL DEFAULT '' COMMENT '정류장명칭',
  district_code  VARCHAR(20) NULL DEFAULT NULL COMMENT '시군구코드(자치구정류장코드분류)',
  lat            DECIMAL(12, 8) NULL DEFAULT NULL COMMENT '위도',
  lng            DECIMAL(12, 8) NULL DEFAULT NULL COMMENT '경도',
  raw_json       JSON NULL DEFAULT NULL COMMENT '원본행 JSON(옵션)',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (stop_id),
  KEY ix_seoul_stop_name (stop_name(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='서울시 버스 정류장 마스터 (공공데이터)';

-- ---------- 검증 쿼리 5개 ----------
-- 1) 테이블 존재 및 컬럼
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seoul_bus_stop_master' ORDER BY ORDINAL_POSITION;

-- 2) PK·인덱스
-- SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seoul_bus_stop_master' ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- 3) 건수 및 샘플
-- SELECT COUNT(*) AS cnt FROM seoul_bus_stop_master;
-- SELECT stop_id, stop_name, district_code, lat, lng FROM seoul_bus_stop_master ORDER BY stop_id LIMIT 5;

-- 4) stop_name 검색 (ix_seoul_stop_name)
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE '%강남역%' LIMIT 10;

-- 5) 중복 실행 후 건수 동일 여부 (import 2회 후 동일 건수면 idempotent)
-- SELECT COUNT(*) FROM seoul_bus_stop_master;
