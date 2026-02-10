-- v0.6-22: PARSE_MATCH 매칭 품질 지표 저장
-- 목적: job_id + route_label 단위로 매칭 품질 수치를 DB에 저장해, 운영 중 품질 변화를 추적

CREATE TABLE IF NOT EXISTS shuttle_parse_metrics (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_doc_id      BIGINT UNSIGNED NOT NULL COMMENT '원본 문서 ID',
  parse_job_id       BIGINT UNSIGNED NOT NULL COMMENT 'PARSE_MATCH job_log id',
  route_label        VARCHAR(64)     NOT NULL COMMENT '노선 라벨(R1, R2 등)',
  cand_total         INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT '전체 후보 수',
  auto_matched_cnt   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'match_method IS NOT NULL',
  low_confidence_cnt INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'match_method = like_prefix',
  none_matched_cnt   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'matched_stop_id NULL/빈값',
  alias_used_cnt     INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'alias_exact/alias_normalized/alias_live_rematch',
  high_cnt           INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'exact/alias_live_rematch/alias_exact',
  med_cnt            INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'normalized/alias_normalized',
  low_cnt            INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'like_prefix',
  none_cnt           INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'match_method NULL',
  created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_parse_metrics_job_route (parse_job_id, route_label),
  KEY ix_parse_metrics_doc_route (source_doc_id, route_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PARSE_MATCH 매칭 품질 지표(route별)';

-- ---------- 검증 쿼리 5개 ----------
-- 1) 테이블 존재 및 컬럼
-- SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_parse_metrics'
-- ORDER BY ORDINAL_POSITION;

-- 2) 인덱스 존재
-- SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_parse_metrics'
-- ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- 3) latest PARSE_MATCH + metrics 연결 확인
-- SELECT j.id AS parse_job_id, j.source_doc_id, j.created_at,
--   (SELECT COUNT(*) FROM shuttle_parse_metrics m WHERE m.parse_job_id = j.id) AS metrics_cnt
-- FROM shuttle_doc_job_log j
-- WHERE j.source_doc_id = :doc AND j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
-- ORDER BY j.id DESC LIMIT 5;

-- 4) metrics route별 샘플
-- SELECT route_label, cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, high_cnt, med_cnt, low_cnt, none_cnt
-- FROM shuttle_parse_metrics
-- WHERE parse_job_id = :latest_job_id
-- ORDER BY route_label;

-- 5) UPSERT idempotent 확인 (같은 job_id+route_label 중복 실행 시 1건만 유지)
-- SELECT parse_job_id, route_label, COUNT(*) AS dup_cnt
-- FROM shuttle_parse_metrics
-- GROUP BY parse_job_id, route_label
-- HAVING dup_cnt > 1;
-- 기대: 0건 (중복 없어야 함)
