-- v0.6-21: LOW 승인 게이트 + alias 등록 검증 강화 검증 (Workbench 실행용, 주석 해제 후 :doc/:rl/:latest_job_id 치환)

-- (1) latest PARSE_MATCH job id 확인
-- SELECT id, source_doc_id, job_type, job_status, created_at
-- FROM shuttle_doc_job_log
-- WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success'
-- ORDER BY id DESC LIMIT 1;

-- (2) LOW(like_prefix) pending 후보 수
-- SELECT COUNT(*) AS low_pending_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND match_method = 'like_prefix' AND status = 'pending';

-- (3) LOW 후보 중 approved 된 수 (승인 게이트 통과 후에만 증가)
-- SELECT COUNT(*) AS low_approved_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND match_method = 'like_prefix' AND status = 'approved';
-- 참고: 이 쿼리는 운영자가 confirm_low 체크 후 승인한 건만 카운트됨

-- (4) alias 등록 시 canonical 존재 여부 샘플 (최근 20건 alias가 stop_master에 존재하는지)
-- SELECT a.alias_text, a.canonical_text,
--   (SELECT COUNT(*) FROM seoul_bus_stop_master m WHERE m.stop_name = a.canonical_text) AS master_match
-- FROM shuttle_stop_alias a
-- WHERE a.is_active = 1
-- ORDER BY a.id DESC LIMIT 20;
-- 기대: master_match가 모두 1 이상 (v0.6-21 검증 통과한 것만 저장됨)

-- (5) alias_text 길이 분포 (<=2가 존재하면 경고)
-- SELECT 
--   CASE 
--     WHEN CHAR_LENGTH(alias_text) <= 2 THEN '<=2 (blocked)'
--     WHEN CHAR_LENGTH(alias_text) <= 5 THEN '3-5'
--     WHEN CHAR_LENGTH(alias_text) <= 10 THEN '6-10'
--     ELSE '>10'
--   END AS length_range,
--   COUNT(*) AS cnt
-- FROM shuttle_stop_alias
-- WHERE is_active = 1
-- GROUP BY length_range
-- ORDER BY length_range;
-- 기대: '<=2 (blocked)' 건수 = 0 (v0.6-21 검증으로 차단됨)

-- (6) match_method별 분포 (회귀 확인)
-- SELECT match_method, COUNT(*) AS cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
-- GROUP BY match_method
-- ORDER BY cnt DESC;

-- (7) approved인데 matched_stop_id 빈값 0 (기존 gate 회귀 확인)
-- SELECT COUNT(*) AS approved_empty_stop_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND status = 'approved' AND (matched_stop_id IS NULL OR matched_stop_id = '');
-- 기대: 0

-- (8) alias_live_rematch 건수 (회귀 확인)
-- SELECT COUNT(*) AS alias_live_rematch_cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND match_method = 'alias_live_rematch';
-- 기대: alias 등록 + live rematch 후 건수 증가
