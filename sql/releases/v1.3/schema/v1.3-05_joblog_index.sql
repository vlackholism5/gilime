-- v1.3-05: shuttle_doc_job_log 인덱스 1개 — ops_dashboard NOT EXISTS(latest job 판별) 최적화
-- 선택 기준: NOT EXISTS 내부 j2는 (source_doc_id, job_type, job_status, id > j.id) 조건.
--   A) (source_doc_id, job_type, job_status, id): source_doc_id 선두 → doc별로 인덱스 범위 스캔. 채택: A.
-- 가드: MySQL은 CREATE INDEX IF NOT EXISTS 미지원. 먼저 SHOW INDEX로 존재 확인 후, 없을 때만 아래 CREATE 실행.
--       이미 존재하면 CREATE 실행하지 말 것. 실행 시 Warning 1831(duplicate index) 발생 가능.
-- ---------- 사전 확인 (주석 해제 후 실행, 결과 0 rows일 때만 CREATE 실행) ----------
-- SHOW INDEX FROM shuttle_doc_job_log WHERE Key_name = 'idx_joblog_doc_type_status_id';
-- ----------

CREATE INDEX idx_joblog_doc_type_status_id
  ON shuttle_doc_job_log(source_doc_id, job_type, job_status, id);

-- ---------- 롤백 (필요 시만 실행) ----------
-- DROP INDEX idx_joblog_doc_type_status_id ON shuttle_doc_job_log;
-- ----------
