-- v1.3-05 검증: job_log 인덱스 확인 + ops_dashboard 쿼리 EXPLAIN
-- 실행 순서: 1) v1.3-05_joblog_index.sql 적용 후, 2) 본 파일 SHOW INDEX + EXPLAIN 실행.

-- ========== 1. SHOW INDEX (인덱스 존재 확인) ==========
SHOW INDEX FROM shuttle_doc_job_log WHERE Key_name = 'idx_joblog_doc_type_status_id';

-- ========== 2. EXPLAIN — ops_dashboard "Docs needing review" (v1.3-04 쿼리 동일) ==========
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

-- ---------- 1) CREATE INDEX 실행 결과 ----------
-- CREATE INDEX idx_joblog_doc_type_status_id ON shuttle_doc_job_log(...) → 0 row(s) affected, 1 warning: Duplicate index 'idx_joblog_doc_type_status_id' (deprecated). 0.172 sec.
-- ---------- 2) SHOW INDEX 결과 ----------
-- Table=shuttle_doc_job_log, Key_name=idx_joblog_doc_type_status_id. 행 4: 1 source_doc_id, 2 job_type, 3 job_status, 4 id.
-- ---------- 3) EXPLAIN — j2 행 (NOT EXISTS 내부) ----------
-- id=1, table=j2, type=ref, key=ix_job_doc_type_status, rows=5, Extra=Using where; Not exists; Using index
