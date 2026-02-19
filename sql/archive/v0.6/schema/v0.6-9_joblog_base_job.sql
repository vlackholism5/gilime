-- v0.6-9: PROMOTE가 어떤 PARSE_MATCH(parse_job_id) 기반으로 승격했는지 job_log에서 추적
-- 기존 데이터 마이그레이션 없음 (NULL 허용)

ALTER TABLE shuttle_doc_job_log
  ADD COLUMN base_job_id BIGINT UNSIGNED NULL COMMENT 'PROMOTE 시 기반이 된 PARSE_MATCH job_id',
  ADD COLUMN route_label VARCHAR(50) NULL COMMENT 'PROMOTE 시 대상 route_label';

CREATE INDEX ix_job_doc_type_status ON shuttle_doc_job_log (source_doc_id, job_type, job_status, id);
CREATE INDEX ix_job_base_job ON shuttle_doc_job_log (base_job_id);

-- ---------- 검증 쿼리 6개 ----------
-- 1) 컬럼 존재 확인
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_doc_job_log' AND COLUMN_NAME IN ('base_job_id','route_label');

-- 2) 인덱스 존재 확인
-- SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_doc_job_log' AND INDEX_NAME IN ('ix_job_doc_type_status','ix_job_base_job');

-- 3) PROMOTE job별 source_doc_id, route_label, base_job_id 조회
-- SELECT id, source_doc_id, route_label, base_job_id, job_status, result_note
-- FROM shuttle_doc_job_log WHERE job_type = 'PROMOTE' ORDER BY id DESC LIMIT 10;

-- 4) base_job_id로 해당 PARSE_MATCH 후보/승인 수
-- SELECT j.id AS promote_id, j.base_job_id,
--   (SELECT COUNT(*) FROM shuttle_stop_candidate c WHERE c.source_doc_id=j.source_doc_id AND c.route_label=j.route_label AND c.created_job_id=j.base_job_id) AS base_cand_total,
--   (SELECT COUNT(*) FROM shuttle_stop_candidate c WHERE c.source_doc_id=j.source_doc_id AND c.route_label=j.route_label AND c.created_job_id=j.base_job_id AND c.status='approved') AS base_cand_approved
-- FROM shuttle_doc_job_log j WHERE j.job_type='PROMOTE' AND j.base_job_id IS NOT NULL ORDER BY j.id DESC LIMIT 5;

-- 5) PROMOTE → route_stop → base(parse) 추적 연쇄
-- SELECT r.id AS route_stop_id, r.source_doc_id, r.route_label, r.created_job_id AS promote_job_id,
--   j.base_job_id AS parse_job_id
-- FROM shuttle_route_stop r
-- JOIN shuttle_doc_job_log j ON j.id = r.created_job_id AND j.job_type = 'PROMOTE'
-- WHERE r.is_active = 1 ORDER BY r.source_doc_id, r.route_label, r.stop_order LIMIT 10;

-- 6) doc별 최신 PROMOTE의 base_job_id
-- SELECT source_doc_id, route_label, id AS promote_job_id, base_job_id, created_at
-- FROM shuttle_doc_job_log
-- WHERE job_type = 'PROMOTE' AND job_status = 'success' AND base_job_id IS NOT NULL
-- ORDER BY source_doc_id, route_label, id DESC;
