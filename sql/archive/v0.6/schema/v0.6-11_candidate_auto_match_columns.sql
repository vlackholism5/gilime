-- v0.6-11: shuttle_stop_candidate 자동매칭 컬럼 (이미 있으면 스킵)
-- run_job.php PARSE_MATCH 시 seoul_bus_stop_master 기반 matched_* 추천 저장용

-- MySQL 8.0.12+ 에서만 동작. 이전 버전은 컬럼 존재 시 오류 무시하고 진행.
ALTER TABLE shuttle_stop_candidate
  ADD COLUMN IF NOT EXISTS matched_stop_id   VARCHAR(50)  NULL DEFAULT NULL COMMENT '자동/수동 매칭 정류장ID' AFTER raw_stop_name,
  ADD COLUMN IF NOT EXISTS matched_stop_name VARCHAR(100) NULL DEFAULT NULL COMMENT '매칭 정류장명' AFTER matched_stop_id,
  ADD COLUMN IF NOT EXISTS match_score       DECIMAL(3,2) NULL DEFAULT NULL COMMENT '1.0=exact, 0.7=fallback' AFTER matched_stop_name,
  ADD COLUMN IF NOT EXISTS match_method      VARCHAR(30)  NULL DEFAULT NULL COMMENT 'exact|normalized|like_prefix|manual_approve' AFTER match_score;

-- ---------- 검증 쿼리 6개 ----------
-- 1) 후보 테이블에 자동매칭 컬럼 존재 여부
-- SELECT COLUMN_NAME FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_stop_candidate'
--   AND COLUMN_NAME IN ('matched_stop_id','matched_stop_name','match_score','match_method');

-- 2) PARSE_MATCH 1회 실행 후, latest job 기준 후보 수
-- SELECT created_job_id, COUNT(*) AS cnt FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND is_active = 1
-- GROUP BY created_job_id ORDER BY created_job_id DESC LIMIT 1;

-- 3) 자동매칭 채워진 후보 (match_method NOT NULL)
-- SELECT id, raw_stop_name, matched_stop_id, matched_stop_name, match_score, match_method
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = :latest_job_id AND matched_stop_id IS NOT NULL;

-- 4) 자동매칭 실패한 후보 (수동 입력 대상)
-- SELECT id, raw_stop_name, matched_stop_id FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = :latest_job_id
--   AND (matched_stop_id IS NULL OR matched_stop_id = '');

-- 5) seoul_bus_stop_master와 정확일치 가능한 raw_stop_name 샘플 (강남역, 역삼역 등)
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name IN ('강남역','역삼역','선릉역');

-- 6) SoT 확인: latest PARSE_MATCH job_id와 후보 created_job_id 일치
-- SELECT j.id AS latest_parse_job_id,
--   (SELECT COUNT(*) FROM shuttle_stop_candidate c
--    WHERE c.source_doc_id = j.source_doc_id AND c.created_job_id = j.id) AS cand_cnt
-- FROM shuttle_doc_job_log j
-- WHERE j.source_doc_id = :doc AND j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
-- ORDER BY j.id DESC LIMIT 1;
