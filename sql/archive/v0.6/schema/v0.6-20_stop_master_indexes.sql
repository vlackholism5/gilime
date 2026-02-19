-- v0.6-20: seoul_bus_stop_master 인덱스 확인
-- 목적: 실데이터 import 후에도 성능 보장
-- 변경: 최소화 (stop_name 인덱스 1개만 확인, 이미 v0.6-10에서 생성됨)

-- 1) 현재 인덱스 확인
-- SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, INDEX_TYPE
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seoul_bus_stop_master'
-- ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- 2) stop_name 인덱스 존재 확인 (v0.6-10에서 이미 생성됨)
-- ix_seoul_stop_name (stop_name(50))
-- 
-- 추가 인덱스는 v0.6-21로 미룸:
-- - normalized 검색 대비용 보조 인덱스
-- - district_code 인덱스 (지역별 검색용)
-- - 전문 검색(FULLTEXT) 검색

-- 3) EXPLAIN으로 인덱스 사용 확인 (검증 쿼리에서 실행)
-- EXPLAIN SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = '강남역';
-- EXPLAIN SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE '강남%';
-- 
-- 기대 결과:
-- - type: ref (exact match) 또는 range (LIKE prefix)
-- - key: ix_seoul_stop_name
-- - rows: 적은 수 (풀스캔 아님)
