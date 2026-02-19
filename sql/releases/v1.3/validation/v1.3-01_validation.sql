-- v1.3-01 검증: 인덱스 존재 확인 + EXPLAIN 3개
-- 실행 순서: 1) v1.3-01_indexes.sql 적용 후, 2) 본 파일에서 아래 쿼리 순서대로 실행.

-- ========== 1. SHOW INDEX (인덱스 존재 확인) ==========
SHOW INDEX FROM shuttle_stop_candidate WHERE Key_name IN ('idx_cand_doc_job_status', 'idx_cand_doc_job_status_method');
SHOW INDEX FROM shuttle_stop_alias WHERE Key_name = 'idx_alias_active_updated';

-- ---------- 1) 실행 결과 요약 ----------
-- (A) 생성 전: SHOW INDEX ... idx_cand_doc_job_status → 0 row(s). idx_cand_doc_job_status_method → 0 row(s). idx_alias_active_updated → 0 row(s).
-- (B) CREATE INDEX 실행: idx_cand_doc_job_status 0.594s, idx_cand_doc_job_status_method 0.188s, idx_alias_active_updated 0.156s. 각 0 row(s) affected.
-- (C) SHOW INDEX 생성 확인:
--     shuttle_stop_candidate: idx_cand_doc_job_status (1 source_doc_id, 2 created_job_id, 3 status), idx_cand_doc_job_status_method (1 source_doc_id, 2 created_job_id, 3 status, 4 match_method).
--     shuttle_stop_alias: idx_alias_active_updated (1 is_active, 2 updated_at).
-- ----------

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
-- 실행 결과:
-- id | select_type | table | type | key | rows | Extra
-- 1  | PRIMARY     | <derived2> | ALL | NULL | 2 | Using where; Using temporary; Using filesort
-- 1  | PRIMARY     | c     | ref  | idx_cand_doc_job_status | 2 | Using index condition
-- 2  | DERIVED     | shuttle_doc_job_log | range | ix_job_doc_type_status | 2 | Using where; Using index for group-by

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
-- 실행 결과:
-- id | select_type | table | type | key | rows | Extra
-- 1  | PRIMARY     | <derived4> | ALL | NULL | 2 | Using temporary; Using filesort
-- 1  | PRIMARY     | j     | eq_ref | PRIMARY | 1 | Using where
-- 4  | DERIVED     | shuttle_doc_job_log | range | ix_job_doc_type_status | 2 | Using where; Using index for group-by
-- 3  | DEPENDENT SUBQUERY | c | ref_or_null | idx_cand_doc_job_status_method | 4 | Using where; Using index
-- 2  | DEPENDENT SUBQUERY | c | ref | idx_cand_doc_job_status | 2 | Using where; Using index

-- ========== 4. EXPLAIN (3) alias_audit — Alias Issues ==========
EXPLAIN
SELECT a.id, a.alias_text, a.canonical_text, a.updated_at
FROM shuttle_stop_alias a
WHERE a.is_active = 1 AND (LENGTH(TRIM(a.alias_text)) <= 2)
ORDER BY a.updated_at DESC
LIMIT 100;
-- 실행 결과:
-- id | select_type | table | type | key | rows | Extra
-- 1  | SIMPLE      | a     | ref  | idx_alias_active_updated | 5 | Using where; Backward index scan
