# v1.2 운영 3페이지 성능 노트 (EXPLAIN 증거화)

## A. 대상 페이지 3개

| 페이지 | 파일 | 용도 |
|--------|------|------|
| Review Queue | public/admin/review_queue.php | 문서/노선별 pending Summary + Top N 후보, focus 링크 |
| Ops Dashboard | public/admin/ops_dashboard.php | Docs needing review, Recent PARSE_MATCH, Promote 후보 |
| Alias Audit | public/admin/alias_audit.php | Alias Issues(리스크) + Recent Alias Writes |

---

## B. 핵심 쿼리 3개 (EXPLAIN 증거)

### (1) review_queue — Top Candidates

**목적:** pending 후보 중 리스크 우선으로 상위 N건 조회. doc_id/route_label 필터 선택.

**쿼리 (EXPLAIN 실행 시 아래 그대로 사용):**

```sql
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
```

**EXPLAIN 결과 요약:**

| id | select_type | table | type | key | rows | Extra |
|----|-------------|-------|------|-----|------|-------|
| 1 | PRIMARY | &lt;derived2&gt; | ALL | NULL | 2 | Using temporary; Using filesort |
| 1 | PRIMARY | c | ALL | NULL | 39 | Using where; Using join buffer (hash join) |
| 2 | DERIVED | shuttle_doc_job_log | range | ix_job_doc_type_status | 2 | Using where; Using index for group-by |

**인덱스 후보 (v1.3에서 적용):**

```sql
-- shuttle_doc_job_log: (job_type, job_status) → source_doc_id, id
-- CREATE INDEX idx_job_type_status_doc_id ON shuttle_doc_job_log(job_type, job_status, source_doc_id);  -- v1.3에서 적용

-- shuttle_stop_candidate: (source_doc_id, created_job_id, status)
-- CREATE INDEX idx_cand_doc_job_status ON shuttle_stop_candidate(source_doc_id, created_job_id, status);  -- v1.3에서 적용
```

---

### (2) ops_dashboard — Docs needing review

**목적:** doc별 latest PARSE_MATCH job 기준 pending_total, pending_risky_total. pending_risky_total DESC 정렬.

**쿼리 (EXPLAIN 실행 시 아래 그대로 사용):**

```sql
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
```

**EXPLAIN 결과 요약:**

| id | select_type | table | type | key | rows | Extra |
|----|-------------|-------|------|-----|------|-------|
| 1 | PRIMARY | &lt;derived4&gt; | ALL | NULL | 2 | Using temporary; Using filesort |
| 1 | PRIMARY | j | eq_ref | PRIMARY | 1 | Using where |
| 4 | DERIVED | shuttle_doc_job_log | range | ix_job_doc_type_status | 2 | Using where; Using index for group-by |
| 3 | DEPENDENT SUBQUERY | c | ALL | NULL | 39 | Using where |
| 2 | DEPENDENT SUBQUERY | c | ALL | NULL | 39 | Using where |

**인덱스 후보 (v1.3에서 적용):**

```sql
-- shuttle_doc_job_log: (job_type, job_status), GROUP BY source_doc_id
-- CREATE INDEX idx_job_type_status_doc_id ON shuttle_doc_job_log(job_type, job_status, source_doc_id);  -- v1.3에서 적용

-- shuttle_stop_candidate: (source_doc_id, created_job_id, status), scalar subquery용
-- CREATE INDEX idx_cand_doc_job_status ON shuttle_stop_candidate(source_doc_id, created_job_id, status);  -- v1.3에서 적용
```

---

### (3) alias_audit — Alias Issues

**목적:** alias_text 길이 2 이하(is_active=1) 이슈 후보 최대 100건. updated_at DESC.

**쿼리 (EXPLAIN 실행 시 아래 그대로 사용):**

```sql
EXPLAIN
SELECT a.id, a.alias_text, a.canonical_text, a.updated_at
FROM shuttle_stop_alias a
WHERE a.is_active = 1 AND (LENGTH(TRIM(a.alias_text)) <= 2)
ORDER BY a.updated_at DESC
LIMIT 100;
```

**EXPLAIN 결과 요약:**

| id | select_type | table | type | key | rows | Extra |
|----|-------------|-------|------|-----|------|-------|
| 1 | SIMPLE | a | ref | ix_shuttle_stop_alias_active | 5 | Using where; Using filesort |

**인덱스 후보 (v1.3에서 적용):**

```sql
-- shuttle_stop_alias: (is_active, updated_at) — 필터+정렬
-- CREATE INDEX idx_alias_active_updated ON shuttle_stop_alias(is_active, updated_at DESC);  -- v1.3에서 적용
```

---

## C. 운영 원칙 (3줄)

1. **신규 페이지는 read-only.** Review Queue / Alias Audit / Ops Dashboard는 조회·링크 전용.
2. **approve / reject / alias 등록 / promote** 는 **route_review에서만** 수행.
3. 성능 이슈 발생 시 **LIMIT·필터 우선** 적용. 인덱스 추가는 **v1.3**에서 진행.

---

## D. v1.3 우선순위(권장)

- **인덱스 먼저 (DDL):**  
  - `shuttle_stop_candidate (source_doc_id, created_job_id, status)`  
  - `shuttle_stop_alias (is_active, updated_at)`  
  - job_log는 이미 `ix_job_doc_type_status` 존재. 필요 시 확장 검토.
- **ops_dashboard 쿼리 리팩터:**  
  "doc별 latest job + scalar subquery 2개" → "candidate를 한 번 GROUP BY 해서 JOIN" 형태로 변경.  
  DEPENDENT SUBQUERY 제거 시 체감 속도 개선 효과가 클 가능성 높음.
- **(선택) job_snapshot_summary 등 요약 테이블/스냅샷 도입**  
  새 테이블/페이지 확장의 실익이 커지는 구간.

---

## E. v1.3-01 적용 후 기대 변화

- **review_queue:** `shuttle_stop_candidate`(c)가 ALL → **ref** (key: idx_cand_doc_job_status). rows·join buffer 감소 기대.
- **ops_dashboard:** DEPENDENT SUBQUERY의 **c 테이블**이 ALL → **ref**로 바뀌는 것이 목표. idx_cand_doc_job_status 또는 idx_cand_doc_job_status_method 사용 시 doc/job당 풀스캔 제거.
- **alias_audit:** 기존 ix_shuttle_stop_alias_active 사용 시 ref 유지. idx_alias_active_updated 추가로 **ORDER BY updated_at** 시 Using filesort 제거 가능(covering 여부는 데이터에 따라 다름).
- 검증: sql/v1.3-01_validation.sql 에서 SHOW INDEX + EXPLAIN 3개 실행 후 결과로 확인.
- 실패 시 롤백 DDL은 v1.3-01_indexes.sql 하단 주석 참고.

---

## F. v1.3-02 적용 후 기대 변화 (ops_dashboard 리팩터)

- **DEPENDENT SUBQUERY 제거:** scalar subquery 2개 → candidate 집계 derived table 1개 + LEFT JOIN. doc당 반복 실행 제거.
- **temp/filesort 감소 목표:** derived table 1회 스캔·집계 후 join으로 정렬 부담 완화. EXPLAIN에서 Using temporary; Using filesort 유무 확인.
- 검증: sql/v1.3-02_explain.sql 실행 후 결과 공유.
