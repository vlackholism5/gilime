-- v1.3-04: ops_dashboard "Docs needing review" — latest job을 NOT EXISTS로만 필터(derived t 제거) 후 EXPLAIN
-- 실행: Workbench에서 아래 쿼리 실행 후 결과를 주석 빈칸에 붙여넣기.
-- 검증: derived2 제거로 Extra의 Using temporary; Using filesort가 줄었는지 확인. 결과 컬럼/정렬 동일 유지.

EXPLAIN
SELECT j.source_doc_id, j.id AS latest_parse_job_id, j.updated_at,
  COALESCE(agg.pending_total, 0) AS pending_total,
  COALESCE(agg.pending_risky_total, 0) AS pending_risky_total
FROM shuttle_doc_job_log j
LEFT JOIN (
  SELECT source_doc_id, created_job_id,
    COUNT(*) AS pending_total,
    SUM(CASE WHEN match_method = 'like_prefix' OR match_method IS NULL THEN 1 ELSE 0 END) AS pending_risky_total
  FROM shuttle_stop_candidate FORCE INDEX (idx_cand_doc_job_status_method)
  WHERE status = 'pending'
  GROUP BY source_doc_id, created_job_id
) agg ON j.source_doc_id = agg.source_doc_id AND j.id = agg.created_job_id
WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
  AND NOT EXISTS (
    SELECT 1 FROM shuttle_doc_job_log j2
    WHERE j2.source_doc_id = j.source_doc_id
      AND j2.job_type = 'PARSE_MATCH' AND j2.job_status = 'success'
      AND j2.id > j.id
  )
ORDER BY pending_risky_total DESC, pending_total DESC;

-- 실행 결과:
-- id | select_type | table      | type | key                          | rows | Extra
-- 1  | PRIMARY     | j          | ref  | idx_job_type_status           | 13   | Using temporary; Using filesort
-- 1  | PRIMARY     | j2         | ref  | ix_job_doc_type_status        | 5    | Using where; Not exists; Using index
-- 1  | PRIMARY     | <derived2> | ref  | <auto_key0>                  | 2    | Using where
-- 2  | DERIVED     | shuttle_stop_candidate | index | idx_cand_doc_job_status_method | 39 | Using where; Using index
