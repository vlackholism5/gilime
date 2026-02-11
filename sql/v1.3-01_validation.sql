-- v1.3-01 검증: 인덱스 존재 확인 + EXPLAIN 3개 (실행 결과 붙여넣을 빈칸)
-- 실행 순서: 1) v1.3-01_indexes.sql 적용 후, 2) 본 파일에서 아래 쿼리 순서대로 실행.

-- ========== 1. SHOW INDEX (인덱스 존재 확인) ==========
SHOW INDEX FROM shuttle_stop_candidate WHERE Key_name IN ('idx_cand_doc_job_status', 'idx_cand_doc_job_status_method');
SHOW INDEX FROM shuttle_stop_alias WHERE Key_name = 'idx_alias_active_updated';

-- ========== 2. EXPLAIN (1) review_queue — Top Candidates ==========
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
-- 실행 결과 붙여넣기 (id, select_type, table, type, key, rows, Extra 등):
-- (여기 채움)

-- ========== 3. EXPLAIN (2) ops_dashboard — Docs needing review ==========
EXPLAIN
SELECT j.source_doc_id, j.id AS latest_parse_job_id, j.updated_at,
  (SELECT COUNT(*) FROM shuttle_stop_candidate c
   WHERE c.source_doc_id = j.source_doc_id AND c.created_job_id = j.id AND c.status = 'pending') AS pending_total,
  (SELECT COUNT(*) FROM shuttle_stop_candidate c
   WHERE c.source_doc_id = j.source_doc_id AND c.created_job_id = j.id AND c.status = 'pending'
     AND (c.match_method = 'like_prefix' OR c.match_method IS NULL)) AS pending_risky_total
FROM shuttle_doc_job_log j
INNER JOIN (
  SELECT source_doc_id, MAX(id) AS mid
  FROM shuttle_doc_job_log
  WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
  GROUP BY source_doc_id
) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
ORDER BY pending_risky_total DESC, pending_total DESC;
-- 실행 결과 붙여넣기:
-- (여기 채움)

-- ========== 4. EXPLAIN (3) alias_audit — Alias Issues ==========
EXPLAIN
SELECT a.id, a.alias_text, a.canonical_text, a.updated_at
FROM shuttle_stop_alias a
WHERE a.is_active = 1 AND (LENGTH(TRIM(a.alias_text)) <= 2)
ORDER BY a.updated_at DESC
LIMIT 100;
-- 실행 결과 붙여넣기:
-- (여기 채움)
