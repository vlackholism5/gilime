-- v1.3-05: shuttle_doc_job_log 인덱스 1개 — ops_dashboard NOT EXISTS(latest job 판별) 최적화
-- 선택 기준: NOT EXISTS 내부 j2는 (source_doc_id, job_type, job_status, id > j.id) 조건.
--   A) (source_doc_id, job_type, job_status, id): source_doc_id 선두 → doc별로 인덱스 범위 스캔, id > j.id 존재 여부만 확인. 최소 행 스캔.
--   B) (job_type, job_status, source_doc_id, id): 동일 조건 가능하나, j가 job_type/job_status로 걸러진 뒤이므로 A가 더 직접적.
-- 채택: A. 실행 전 아래 SHOW INDEX로 존재 여부 확인 권장. MySQL은 CREATE INDEX IF NOT EXISTS 미지원.
-- ---------- 사전 확인 (주석 해제 후 실행) ----------
-- SHOW INDEX FROM shuttle_doc_job_log WHERE Key_name = 'idx_joblog_doc_type_status_id';
-- ----------

CREATE INDEX idx_joblog_doc_type_status_id
  ON shuttle_doc_job_log(source_doc_id, job_type, job_status, id);

-- ---------- 롤백 (필요 시만 실행) ----------
-- DROP INDEX idx_joblog_doc_type_status_id ON shuttle_doc_job_log;
-- ----------
