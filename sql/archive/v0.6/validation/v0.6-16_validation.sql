-- v0.6-16: unmatched 필터·canonical 추천 검증 쿼리 7개 (주석 해제 후 :doc 등 치환)

-- 1) latest PARSE_MATCH job_id 확인 (doc=1)
-- SELECT id, source_doc_id, job_type, job_status, created_at
-- FROM shuttle_doc_job_log
-- WHERE source_doc_id = 1 AND job_type = 'PARSE_MATCH' AND job_status = 'success'
-- ORDER BY id DESC LIMIT 1;

-- 2) latest 스냅샷에서 matched_stop_id NULL 후보 수
-- SELECT COUNT(*) AS unmatched_cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = (
--   SELECT id FROM shuttle_doc_job_log
--   WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success' ORDER BY id DESC LIMIT 1
-- ) AND (c.matched_stop_id IS NULL OR c.matched_stop_id = '');

-- 3) match_method별 건수 (latest 기준)
-- SELECT match_method, COUNT(*) AS cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = (
--   SELECT id FROM shuttle_doc_job_log
--   WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success' ORDER BY id DESC LIMIT 1
-- )
-- GROUP BY match_method ORDER BY cnt DESC;

-- 4) alias_live_rematch 건수 (latest 기준)
-- SELECT COUNT(*) AS alias_live_rematch_cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = (
--   SELECT id FROM shuttle_doc_job_log
--   WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success' ORDER BY id DESC LIMIT 1
-- ) AND c.match_method = 'alias_live_rematch';

-- 5) alias 테이블 등록 건수 Top 20
-- SELECT id, alias_text, canonical_text, is_active, updated_at
-- FROM shuttle_stop_alias WHERE is_active = 1 ORDER BY id DESC LIMIT 20;

-- 6) only_unmatched 대상 후보 Top 20 raw_stop_name 샘플
-- SELECT c.id, c.seq_in_route, c.raw_stop_name, c.match_method
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = :latest_job_id
--   AND (c.matched_stop_id IS NULL OR c.matched_stop_id = '')
-- ORDER BY c.seq_in_route LIMIT 20;

-- 7) promote gate 조건 위반 후보(approved인데 matched_stop_id 빈값) 0인지 확인
-- SELECT COUNT(*) AS approved_empty_stop_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND status = 'approved' AND (matched_stop_id IS NULL OR matched_stop_id = '');
-- (0이어야 promote 가능)
