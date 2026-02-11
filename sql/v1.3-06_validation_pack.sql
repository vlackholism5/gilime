-- v1.3-06: 검증 통합팩 — SHOW INDEX 4건 + EXPLAIN 3건 (운영 3페이지 핵심 쿼리)
-- 실행: Workbench에서 1)~4) 순서대로 실행 후, 각 "(여기 채움)" 블록에 결과 붙여넣기.

-- ========== 1. SHOW INDEX (인덱스 존재 확인) ==========
SHOW INDEX FROM shuttle_stop_candidate WHERE Key_name IN ('idx_cand_doc_job_status', 'idx_cand_doc_job_status_method');
SHOW INDEX FROM shuttle_stop_alias WHERE Key_name = 'idx_alias_active_updated';
SHOW INDEX FROM shuttle_doc_job_log WHERE Key_name = 'idx_joblog_doc_type_status_id';

-- (여기 채움) SHOW INDEX 요약: candidate 2개, alias 1개, job_log 1개 행 수·Key_name·Column_name 순서
-- ----------

-- ========== 2. EXPLAIN — review_queue Top Candidates (v1.3-01 쿼리) ==========
EXPLAIN
SELECT c.id AS cand_id, c.created_job_id, c.source_doc_id AS doc_id, c.route_label, c.raw_stop_name, c.matched_stop_name, c.match_method, c.match_score
FROM shuttle_stop_candidate c
INNER JOIN (
  SELECT source_doc_id, MAX(id) AS mid
  FROM shuttle_doc_job_log
  WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
  GROUP BY source_doc_id
) t ON c.source_doc_id = t.source_doc_id AND c.created_job_id = t.mid
WHERE c.status = 'pending'
ORDER BY (c.match_method IS NULL) DESC, (c.match_method = 'like_prefix') DESC, c.match_score ASC, c.id ASC
LIMIT 50;

-- (여기 채움)
-- id | select_type | table | type | key | rows | Extra
-- 1  | PRIMARY     | <derived2> | ... | ... | ... | ...
-- 1  | PRIMARY     | c     | ...  | ... | ... | ...
-- 2  | DERIVED     | shuttle_doc_job_log | ... | ... | ... | ...
-- ----------

-- ========== 3. EXPLAIN — alias_audit Alias Issues (v1.3-01 쿼리) ==========
EXPLAIN
SELECT a.id, a.alias_text, a.canonical_text, a.updated_at
FROM shuttle_stop_alias a
WHERE a.is_active = 1 AND (LENGTH(TRIM(a.alias_text)) <= 2)
ORDER BY a.updated_at DESC
LIMIT 100;

-- (여기 채움)
-- id | select_type | table | type | key | rows | Extra
-- 1  | SIMPLE      | a     | ...  | ... | ... | ...
-- ----------

-- ========== 4. EXPLAIN — ops_dashboard Docs needing review (v1.3-05 최신 쿼리) ==========
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
-- id | select_type | table      | type | key   | rows | Extra
-- 1  | PRIMARY     | j          | ...  | ...   | ...  | ...
-- 1  | PRIMARY     | j2         | ...  | ...   | ...  | ...
-- 1  | PRIMARY     | <derived2> | ...  | ...   | ...  | ...
-- 2  | DERIVED     | shuttle_stop_candidate | ... | ... | ... | ...
-- ----------
