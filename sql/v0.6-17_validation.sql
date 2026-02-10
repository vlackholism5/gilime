-- v0.6-17: 추천 canonical 최적화 검증 쿼리 6개 (주석 해제 후 :doc 등 치환)
-- 기존 기능 회귀 없음·only_unmatched에서만 추천 사용 판단용

-- 1) latest PARSE_MATCH job_id (doc 단위)
-- SELECT id, source_doc_id, job_type, job_status, created_at
-- FROM shuttle_doc_job_log
-- WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success'
-- ORDER BY id DESC LIMIT 1;

-- 2) latest 스냅샷 unmatched 후보 수 (matched_stop_id NULL/빈값)
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
-- SELECT COUNT(*) AS cnt FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = (
--   SELECT id FROM shuttle_doc_job_log
--   WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success' ORDER BY id DESC LIMIT 1
-- ) AND c.match_method = 'alias_live_rematch';

-- 5) unmatched 후보 raw_stop_name Top 30 (중복 확인·캐시 효과 판단용)
-- SELECT c.raw_stop_name, COUNT(*) AS dup_cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = :latest_job_id
--   AND (c.matched_stop_id IS NULL OR c.matched_stop_id = '')
-- GROUP BY c.raw_stop_name ORDER BY dup_cnt DESC LIMIT 30;

-- 6) approved인데 matched_stop_id 빈값인 건수 (0이어야 promote 가능)
-- SELECT COUNT(*) AS approved_empty_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND status = 'approved' AND (matched_stop_id IS NULL OR matched_stop_id = '');
