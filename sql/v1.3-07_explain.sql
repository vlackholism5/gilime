-- v1.3-07: ops_dashboard Docs needing review — 정렬별 EXPLAIN (기본 최신 vs 위험도)
-- 실행: Workbench에서 1) 2) 각각 실행 후 "(여기 채움)" 블록에 결과 붙여넣기.

-- ========== 1) sort=updated (ORDER BY j.updated_at DESC) ==========
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
ORDER BY j.updated_at DESC;

-- (여기 채움)
-- id | select_type | table | type | key | rows | Extra
-- ...

-- ========== 2) sort=risky (ORDER BY pending_risky_total DESC, pending_total DESC) ==========
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

-- (여기 채움)
-- id | select_type | table | type | key | rows | Extra
-- ...
