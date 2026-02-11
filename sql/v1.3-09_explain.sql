-- v1.3-09: review_queue Top Candidates — 정렬별 EXPLAIN (기본 vs 단순)
-- 실행: Workbench에서 1) 2) 각각 실행 후 "(여기 채움)" 블록에 결과 붙여넣기.

-- ========== 1) sort=default (ORDER BY match_method NULL, like_prefix, score, id) ==========
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
  AND (c.match_method = 'like_prefix' OR c.match_method IS NULL)
ORDER BY (c.match_method IS NULL) DESC, (c.match_method = 'like_prefix') DESC, c.match_score ASC, c.id ASC
LIMIT 50;

-- 실행 결과 (sort=default):
-- id | select_type | table | type | key | rows | Extra
-- 1  | PRIMARY     | <derived2> | ALL | NULL | 2 | Using where; Using temporary; Using filesort
-- 1  | PRIMARY     | c     | ref  | idx_cand_doc_job_status | 2 | Using index condition; Using where
-- 2  | DERIVED     | shuttle_doc_job_log | range | ix_job_doc_type_status | 2 | Using where; Using index for group-by

-- ========== 2) sort=simple (ORDER BY c.id ASC) ==========
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
  AND (c.match_method = 'like_prefix' OR c.match_method IS NULL)
ORDER BY c.id ASC
LIMIT 50;

-- 실행 결과 (sort=simple):
-- id | select_type | table | type | key | rows | Extra
-- 1  | PRIMARY     | <derived2> | ALL | NULL | 2 | Using where; Using temporary; Using filesort
-- 1  | PRIMARY     | c     | ref  | idx_cand_doc_job_status | 2 | Using index condition; Using where
-- 2  | DERIVED     | shuttle_doc_job_log | range | ix_job_doc_type_status | 2 | Using where; Using index for group-by
