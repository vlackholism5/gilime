-- v0.6-18: 매칭 신뢰도 표시 + summary 집계 검증 (Workbench에서 주석 해제 후 :doc/:rl/:latest_job_id 치환 실행)
-- GPT 대화에서 검증할 때: 사용자가 이 파일의 쿼리를 실행한 뒤, 실행 결과(행 수/표/에러)를 GPT 대화창에 붙여넣으면 GPT가 다음 지시를 줌. (GPT는 SQL 직접 실행 불가)

-- (1) latest PARSE_MATCH job id 확인
-- SELECT id, source_doc_id, job_type, job_status, created_at
-- FROM shuttle_doc_job_log
-- WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success'
-- ORDER BY id DESC LIMIT 1;

-- (2) match_method별 건수 (특히 like_prefix)
-- SELECT match_method, COUNT(*) AS cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.route_label = :rl AND c.created_job_id = :latest_job_id
-- GROUP BY match_method ORDER BY cnt DESC;

-- (3) 신뢰도 분류별 건수 (HIGH/MED/LOW/NONE) — SQL CASE
-- SELECT
--   CASE
--     WHEN match_method IN ('exact','alias_live_rematch','alias_exact') THEN 'HIGH'
--     WHEN match_method IN ('normalized','alias_normalized') THEN 'MED'
--     WHEN match_method = 'like_prefix' THEN 'LOW'
--     ELSE 'NONE'
--   END AS confidence,
--   COUNT(*) AS cnt
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.route_label = :rl AND c.created_job_id = :latest_job_id
-- GROUP BY confidence ORDER BY confidence;

-- (4) low_confidence(like_prefix) 후보 샘플 20건
-- SELECT raw_stop_name,
--        TRIM(raw_stop_name) AS normalized,
--        matched_stop_name
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND match_method = 'like_prefix'
-- ORDER BY id ASC LIMIT 20;

-- (5) alias 계열 매칭 건수
-- SELECT COUNT(*) AS alias_used_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND match_method IN ('alias_exact','alias_normalized','alias_live_rematch');

-- (6) none_matched_cnt 확인 (matched_stop_id NULL/빈값)
-- SELECT COUNT(*) AS none_matched_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND (matched_stop_id IS NULL OR matched_stop_id = '');

-- (7) promote gate 회귀 확인: approved인데 matched_stop_id 빈값 0이어야 함
-- SELECT COUNT(*) AS approved_empty_stop_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND status = 'approved' AND (matched_stop_id IS NULL OR matched_stop_id = '');
