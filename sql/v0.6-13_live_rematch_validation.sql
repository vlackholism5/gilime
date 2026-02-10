-- v0.6-13: alias 등록 즉시 재매칭 — 검증 쿼리 6개 (DDL 없음, 주석 해제 후 실행)

-- 1) alias 저장 확인
-- SELECT id, alias_text, canonical_text, is_active, updated_at
-- FROM shuttle_stop_alias WHERE is_active = 1 ORDER BY id DESC LIMIT 10;

-- 2) candidate matched_* 즉시 업데이트 확인 (match_method = alias_live_rematch)
-- SELECT id, source_doc_id, route_label, raw_stop_name, matched_stop_id, matched_stop_name, match_score, match_method, updated_at
-- FROM shuttle_stop_candidate WHERE match_method = 'alias_live_rematch' ORDER BY updated_at DESC LIMIT 10;

-- 3) match_method='alias_live_rematch' 건수 확인
-- SELECT match_method, COUNT(*) AS cnt FROM shuttle_stop_candidate
-- WHERE match_method = 'alias_live_rematch' GROUP BY match_method;

-- 4) canonical_text가 stop_master에 존재하는지 (alias 기준)
-- SELECT a.id, a.alias_text, a.canonical_text,
--   (SELECT COUNT(*) FROM seoul_bus_stop_master m WHERE m.stop_name = a.canonical_text OR m.stop_name = TRIM(REGEXP_REPLACE(a.canonical_text, '\\s+', ' '))) AS master_match
-- FROM shuttle_stop_alias a WHERE a.is_active = 1 ORDER BY a.id DESC LIMIT 10;

-- 5) latest PARSE_MATCH 기준 candidate 중 matched_stop_id NULL 건수 (감소 추이 확인용)
-- SELECT j.id AS latest_job_id,
--   (SELECT COUNT(*) FROM shuttle_stop_candidate c WHERE c.source_doc_id = j.source_doc_id AND c.created_job_id = j.id AND (c.matched_stop_id IS NULL OR c.matched_stop_id = '')) AS null_matched_cnt
-- FROM shuttle_doc_job_log j
-- WHERE j.source_doc_id = :doc AND j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
-- ORDER BY j.id DESC LIMIT 1;

-- 6) promote 전 approved 중 matched_stop_id 빈값 0 유지 확인
-- SELECT source_doc_id, route_label, created_job_id,
--   SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_cnt,
--   SUM(CASE WHEN status = 'approved' AND (matched_stop_id IS NULL OR matched_stop_id = '') THEN 1 ELSE 0 END) AS approved_empty_stop_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
-- GROUP BY source_doc_id, route_label, created_job_id;
-- (approved_empty_stop_cnt = 0 이어야 promote 가능)
