-- v1.7-18: 서울시 버스 노선/노선-정류장 마스터 검증 (Workbench 실행용)

-- (1) 테이블 존재 확인
-- SELECT TABLE_NAME
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('seoul_bus_route_master', 'seoul_bus_route_stop_master')
-- ORDER BY TABLE_NAME;

-- (2) row count
-- SELECT COUNT(*) AS route_cnt FROM seoul_bus_route_master;
-- SELECT COUNT(*) AS route_stop_cnt FROM seoul_bus_route_stop_master;

-- (3) 인덱스 확인
-- SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('seoul_bus_route_master', 'seoul_bus_route_stop_master')
-- ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- (4) route_id/seq 중복 검증 (0건 기대)
-- SELECT route_id, seq_in_route, COUNT(*) AS dup_cnt
-- FROM seoul_bus_route_stop_master
-- GROUP BY route_id, seq_in_route
-- HAVING COUNT(*) > 1
-- LIMIT 20;

-- (5) route_stop의 route_id가 route_master에 없는 경우 (0건 또는 매우 소수 기대)
-- SELECT rs.route_id, COUNT(*) AS cnt
-- FROM seoul_bus_route_stop_master rs
-- LEFT JOIN seoul_bus_route_master rm ON rm.route_id = rs.route_id
-- WHERE rm.route_id IS NULL
-- GROUP BY rs.route_id
-- ORDER BY cnt DESC
-- LIMIT 20;

-- (6) 샘플 조회
-- SELECT route_id, route_name, route_type, start_stop_name, end_stop_name
-- FROM seoul_bus_route_master
-- ORDER BY route_id DESC
-- LIMIT 10;
--
-- SELECT route_id, seq_in_route, stop_id, stop_name, ars_id
-- FROM seoul_bus_route_stop_master
-- ORDER BY id DESC
-- LIMIT 20;

-- (7) EXPLAIN (인덱스 사용 확인)
-- EXPLAIN SELECT route_id, route_name FROM seoul_bus_route_master WHERE route_name LIKE '강남%';
-- EXPLAIN SELECT route_id, seq_in_route, stop_name FROM seoul_bus_route_stop_master WHERE route_id = 100100118 ORDER BY seq_in_route ASC LIMIT 30;
