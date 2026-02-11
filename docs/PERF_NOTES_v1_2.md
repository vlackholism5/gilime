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

---

## G. v1.3-03 목표/검증 (ops_dashboard agg 인덱스 활용)

- **목표:** candidate 집계 derived(agg)가 idx_cand_doc_job_status 또는 idx_cand_doc_job_status_method 를 사용하도록 FORCE INDEX 적용. GROUP BY (source_doc_id, created_job_id) + WHERE status='pending' 이 인덱스 선두와 일치.
- **검증 포인트:** EXPLAIN에서 derived3(shuttle_stop_candidate)의 **key**가 기존 idx_cand_status → **idx_cand_doc_job_status** 또는 **idx_cand_doc_job_status_method** 로 바뀌는지 확인.
- 검증: sql/v1.3-03_explain.sql 실행 후 결과 공유.

---

## H. v1.3-04 목표/검증 (ops_dashboard derived2 temp/filesort 최소화)

- **목표:** latest job per doc을 derived table(t) 대신 NOT EXISTS로만 필터. derived2 제거로 해당 단계의 Using temporary; Using filesort 제거 또는 감소.
- **검증 포인트:** EXPLAIN에서 derived2 Extra에 **Using temporary / Using filesort** 가 줄었는지 확인. 쿼리 결과 동일성(컬럼·정렬: pending_risky_total DESC, pending_total DESC) 유지.
- 검증: sql/v1.3-04_explain.sql 실행 후 결과 공유.

---

## I. v1.3-05 목표/검증 (job_log 인덱스 — NOT EXISTS 최적화)

- **목적:** NOT EXISTS 내부(j2) 비용 감소. shuttle_doc_job_log에 (source_doc_id, job_type, job_status, id) 인덱스 추가 → j2가 source_doc_id·job_type·job_status 기준으로 id > j.id 범위만 스캔하도록 유도.
- **검증 포인트:** EXPLAIN에서 **j2**의 key가 **idx_joblog_doc_type_status_id** 로 잡히는지, rows 감소 여부 확인.
- 롤백: sql/v1.3-05_joblog_index.sql 하단 DROP INDEX 주석 참고. 검증: sql/v1.3-05_validation.sql (SHOW INDEX + EXPLAIN).

**Workbench SHOW INDEX (shuttle_doc_job_log, Key_name = idx_joblog_doc_type_status_id):**

| 구분 | 행 수 | Key_name | Column_name 순서(1~4) |
|------|-------|----------|----------------------|
| 초기(CREATE INDEX 적용 전) | 0 rows | — | — |
| CREATE INDEX 적용 후 | 4 | idx_joblog_doc_type_status_id | 1 source_doc_id, 2 job_type, 3 job_status, 4 id |

**EXPLAIN — j2 행만 (NOT EXISTS 내부):**

| 구분 | key | rows | Extra |
|------|-----|------|-------|
| CREATE INDEX 적용 후 | ix_job_doc_type_status | 5 | Using where; Not exists; Using index |

**판정:** ( ) A) key 변경됨 — j2가 idx_joblog_doc_type_status_id 사용  
( ) B) key 미변경 — j2가 기존 ix_job_doc_type_status 유지  
→ **확인 필요:** 현재 EXPLAIN상 j2는 ix_job_doc_type_status 사용. A/B 재확인 후 체크.

- CREATE INDEX 실행 시 Warning 1831(duplicate index) 발생 → 이미 존재하던 인덱스였음을 명시. SHOW INDEX 결과로 4컬럼(source_doc_id, job_type, job_status, id) 존재 확정.
- **v1.3-06 후보(문서만, 코드 변경 보류):** j2가 idx_joblog_doc_type_status_id를 사용하지 않을 경우, NOT EXISTS 서브쿼리 j2에 USE INDEX(idx_joblog_doc_type_status_id) 힌트 적용 후보. 확인 필요.

---

## J. v1.3-06 검증 통합팩

- **원칙:** 운영 3페이지(review_queue, alias_audit, ops_dashboard) 성능 검증은 **sql/v1.3-06_validation_pack.sql 1개**로 통합. SHOW INDEX 4건 + EXPLAIN 3건 실행 후 각 "(여기 채움)" 블록에 결과 붙여넣기.
- j2가 idx_joblog_doc_type_status_id를 쓰지 않고 ix_job_doc_type_status를 쓰는 현상은 **확인 필요/가설**로 분리. 옵티마이저 선택 차이 또는 통계에 따른 것으로 두고, 다음 버전(v1.3-07)에서 **힌트 실험(STRAIGHT_JOIN/USE INDEX)** 계획만 명시.
