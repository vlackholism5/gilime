-- v0.6-15: stop_master 퀵 검색·검증 쿼리 6개 (주석 해제 후 실행)

-- 1) stop_master 테이블 row count
-- SELECT COUNT(*) AS cnt FROM seoul_bus_stop_master;

-- 2) stop_name 인덱스 존재 확인
-- SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seoul_bus_stop_master' AND COLUMN_NAME = 'stop_name';

-- 3) exact 샘플 검색 (강남역/역삼역/선릉역)
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master
-- WHERE stop_name IN ('강남역', '역삼역', '선릉역');

-- 4) normalized 샘플 검색 (공백 포함 케이스: 공백 축약 후 일치)
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master
-- WHERE stop_name = TRIM(REGEXP_REPLACE('  강남역  ', '\\s+', ' '));

-- 5) like_prefix 샘플 검색 (강남)
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master
-- WHERE stop_name LIKE CONCAT('강남', '%') LIMIT 10;

-- 6) matched_stop_id NULL 후보 Top 30 (운영 대상)
-- SELECT c.id, c.seq_in_route, c.raw_stop_name, c.match_method, c.match_score, c.status
-- FROM shuttle_stop_candidate c
-- WHERE c.source_doc_id = :doc AND c.created_job_id = :latest_job_id
--   AND (c.matched_stop_id IS NULL OR c.matched_stop_id = '')
-- ORDER BY c.seq_in_route LIMIT 30;
