-- v0.6-20: seoul_bus_stop_master 실데이터 import 검증 (Workbench 실행용, 주석 해제 후 실행)

-- (1) stop_master row count
-- SELECT COUNT(*) AS total_rows FROM seoul_bus_stop_master;
-- 기대: 10,000건 이상 (서울시 전체 정류장)

-- (2) stop_name 인덱스 존재
-- SELECT INDEX_NAME, COLUMN_NAME, INDEX_TYPE
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seoul_bus_stop_master'
--   AND INDEX_NAME = 'ix_seoul_stop_name';
-- 기대: ix_seoul_stop_name (stop_name) 1건

-- (3) stop_id PK/unique 존재
-- SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seoul_bus_stop_master'
--   AND COLUMN_NAME = 'stop_id'
-- ORDER BY INDEX_NAME;
-- 기대: PRIMARY (stop_id) NON_UNIQUE=0

-- (4) stop_name exact 샘플 10개 (강남역/서울역/홍대입구역 등)
-- SELECT stop_id, stop_name, district_code
-- FROM seoul_bus_stop_master
-- WHERE stop_name IN ('강남역', '서울역', '홍대입구역', '사당역', '신촌역', '역삼역', '선릉역', '삼성역', '종로3가역', '시청역')
-- LIMIT 10;
-- 기대: 10건 (또는 실제 존재하는 건수)

-- (5) like_prefix 샘플 10개 (강남, 서울, 홍대)
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE '강남%' LIMIT 5;
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE '서울%' LIMIT 5;
-- SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE '홍대%' LIMIT 5;
-- 기대: 각각 여러 건 (강남역, 강남구청, 서울역, 서울대입구, 홍대입구역 등)

-- (6) EXPLAIN: stop_name exact (인덱스 사용 확인)
-- EXPLAIN SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = '강남역';
-- 기대:
--   type: ref (또는 const)
--   key: ix_seoul_stop_name
--   rows: 1~10 (풀스캔 아님)

-- (7) EXPLAIN: stop_name LIKE prefix (인덱스 사용 확인)
-- EXPLAIN SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE '강남%';
-- 기대:
--   type: range
--   key: ix_seoul_stop_name
--   rows: 적은 수 (풀스캔 아님)

-- (8) PARSE_MATCH 실행 후 match_method 분포 (실데이터 기준, :doc/:latest_job_id 치환 필요)
-- SELECT match_method, COUNT(*) AS cnt
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = :latest_job_id
-- GROUP BY match_method
-- ORDER BY cnt DESC;
-- 기대: exact/normalized/like_prefix/alias_* 등 분포 확인. LOW(like_prefix) 비중이 더미 대비 줄어들었는지 확인.

-- (9) only_low=1 필터 결과 샘플 10건 (route_review 확인용, :doc/:rl/:latest_job_id 치환 필요)
-- SELECT id, raw_stop_name, matched_stop_id, matched_stop_name, match_method, match_score
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND route_label = :rl AND created_job_id = :latest_job_id
--   AND match_method = 'like_prefix'
-- ORDER BY id ASC LIMIT 10;
-- 기대: like_prefix 행이 0이 아닌지 확인 (실데이터 기준)
