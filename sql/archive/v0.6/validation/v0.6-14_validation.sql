-- v0.6-14: 매칭 품질·안전장치 검증 쿼리 5개 (주석 해제 후 :doc 등 치환)

-- 1) latest PARSE_MATCH job_id 기준 match_method별 건수
-- SELECT match_method, COUNT(*) AS cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = (
--   SELECT id FROM shuttle_doc_job_log
--   WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success' ORDER BY id DESC LIMIT 1
-- )
-- GROUP BY match_method ORDER BY cnt DESC;

-- 2) like_prefix 매칭된 후보 중 raw_stop_name 길이 분포 (2글자 이하는 없어야 함)
-- SELECT match_method, CHAR_LENGTH(raw_stop_name) AS len, COUNT(*) AS cnt
-- FROM shuttle_stop_candidate
-- WHERE match_method = 'like_prefix'
-- GROUP BY match_method, CHAR_LENGTH(raw_stop_name) ORDER BY len, cnt;

-- 3) match_score NULL vs NOT NULL 건수
-- SELECT
--   SUM(CASE WHEN match_score IS NULL THEN 1 ELSE 0 END) AS null_score_cnt,
--   SUM(CASE WHEN match_score IS NOT NULL THEN 1 ELSE 0 END) AS has_score_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = :latest_job_id;

-- 4) matched_stop_id NULL 후보 Top 20 (운영 대상)
-- SELECT id, seq_in_route, raw_stop_name, match_method, match_score, status
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = :latest_job_id
--   AND (matched_stop_id IS NULL OR matched_stop_id = '')
-- ORDER BY seq_in_route LIMIT 20;

-- 5) alias_live_rematch 발생 건수 확인
-- SELECT match_method, COUNT(*) AS cnt FROM shuttle_stop_candidate
-- WHERE match_method = 'alias_live_rematch' GROUP BY match_method;
