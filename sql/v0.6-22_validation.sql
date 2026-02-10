-- v0.6-22: PARSE_MATCH 매칭 품질 지표 저장 검증 쿼리
-- 목적: shuttle_parse_metrics 테이블 + 데이터 무결성 확인
-- 사용법: Workbench에서 순서대로 실행 (각 쿼리 블록을 선택 후 실행)

-- (1) 테이블 존재 확인
-- 기대: id, source_doc_id, parse_job_id, route_label, cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt, created_at
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_parse_metrics'
ORDER BY ORDINAL_POSITION;

-- (2) latest PARSE_MATCH job_id 확인
-- 기대: latest job_id(가장 위) 값 확인
SELECT id, source_doc_id, job_status, created_at
FROM shuttle_doc_job_log
WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
ORDER BY id DESC
LIMIT 5;

-- (3) metrics row count (route 수만큼 생성되는지)
-- 기대: route_cnt = metrics_cnt (1:1 매칭)
SET @latest_job_id = (SELECT id FROM shuttle_doc_job_log WHERE job_type='PARSE_MATCH' AND job_status='success' ORDER BY id DESC LIMIT 1);
SELECT 
  (SELECT COUNT(DISTINCT route_label) FROM shuttle_stop_candidate WHERE created_job_id = @latest_job_id) AS route_cnt,
  (SELECT COUNT(*) FROM shuttle_parse_metrics WHERE parse_job_id = @latest_job_id) AS metrics_cnt;

-- (4) metrics와 실제 candidate 집계 일치 확인 (샘플 1 route)
-- 기대: 두 행의 모든 컬럼이 일치
SET @latest_job_id = (SELECT id FROM shuttle_doc_job_log WHERE job_type='PARSE_MATCH' AND job_status='success' ORDER BY id DESC LIMIT 1);
SET @sample_route = (
  SELECT CONVERT(route_label USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM shuttle_stop_candidate
  WHERE created_job_id = @latest_job_id
  LIMIT 1
);
SELECT 
  'metrics' AS source,
  cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt
FROM shuttle_parse_metrics
WHERE parse_job_id = @latest_job_id
  AND CONVERT(route_label USING utf8mb4) COLLATE utf8mb4_unicode_ci
      = CONVERT(@sample_route USING utf8mb4) COLLATE utf8mb4_unicode_ci
UNION ALL
SELECT 
  'candidate' AS source,
  COUNT(*) AS cand_total,
  SUM(CASE WHEN match_method IS NOT NULL THEN 1 ELSE 0 END) AS auto_matched_cnt,
  SUM(CASE WHEN match_method = 'like_prefix' THEN 1 ELSE 0 END) AS low_confidence_cnt,
  SUM(CASE WHEN matched_stop_id IS NULL OR matched_stop_id = '' THEN 1 ELSE 0 END) AS none_matched_cnt,
  SUM(CASE WHEN match_method IN ('alias_exact','alias_normalized','alias_live_rematch') THEN 1 ELSE 0 END) AS alias_used_cnt,
  SUM(CASE WHEN match_method IN ('exact','alias_live_rematch','alias_exact') THEN 1 ELSE 0 END) AS high_cnt,
  SUM(CASE WHEN match_method IN ('normalized','alias_normalized') THEN 1 ELSE 0 END) AS med_cnt,
  SUM(CASE WHEN match_method = 'like_prefix' THEN 1 ELSE 0 END) AS low_cnt,
  SUM(CASE WHEN match_method IS NULL THEN 1 ELSE 0 END) AS none_cnt
FROM shuttle_stop_candidate
WHERE created_job_id = @latest_job_id
  AND CONVERT(route_label USING utf8mb4) COLLATE utf8mb4_unicode_ci
      = CONVERT(@sample_route USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- (5) match_method 분포 vs high/med/low/none (전체 route 합산)
-- 기대: high_total + med_total + low_total + none_total = cand_total_sum
SET @latest_job_id = (SELECT id FROM shuttle_doc_job_log WHERE job_type='PARSE_MATCH' AND job_status='success' ORDER BY id DESC LIMIT 1);
SELECT 
  SUM(high_cnt) AS high_total,
  SUM(med_cnt) AS med_total,
  SUM(low_cnt) AS low_total,
  SUM(none_cnt) AS none_total,
  SUM(cand_total) AS cand_total_sum
FROM shuttle_parse_metrics
WHERE parse_job_id = @latest_job_id;

-- (6) only_low 후보 수 vs low_cnt
-- 기대: metrics_low_total = candidate_low_total
SET @latest_job_id = (SELECT id FROM shuttle_doc_job_log WHERE job_type='PARSE_MATCH' AND job_status='success' ORDER BY id DESC LIMIT 1);
SELECT 
  (SELECT SUM(low_cnt) FROM shuttle_parse_metrics WHERE parse_job_id = @latest_job_id) AS metrics_low_total,
  (SELECT COUNT(*) FROM shuttle_stop_candidate WHERE created_job_id = @latest_job_id AND match_method = 'like_prefix') AS candidate_low_total;

-- (7) none_matched_cnt vs matched_stop_id NULL
-- 기대: metrics_none_total = candidate_none_total
SET @latest_job_id = (SELECT id FROM shuttle_doc_job_log WHERE job_type='PARSE_MATCH' AND job_status='success' ORDER BY id DESC LIMIT 1);
SELECT 
  (SELECT SUM(none_matched_cnt) FROM shuttle_parse_metrics WHERE parse_job_id = @latest_job_id) AS metrics_none_total,
  (SELECT COUNT(*) FROM shuttle_stop_candidate WHERE created_job_id = @latest_job_id AND (matched_stop_id IS NULL OR matched_stop_id = '')) AS candidate_none_total;

-- (8) parse_job_id 중복 실행 시 UPSERT로 1세트만 유지되는지
-- 기대: 0건 (중복 없음)
SELECT parse_job_id, route_label, COUNT(*) AS dup_cnt
FROM shuttle_parse_metrics
GROUP BY parse_job_id, route_label
HAVING dup_cnt > 1;
