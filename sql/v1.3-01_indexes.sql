-- v1.3-01: 운영 3페이지 성능 개선 1차 — 인덱스 3개 추가 (DDL만)
-- 실행: Workbench에서 본 파일 실행. 중복 생성 방지를 위해 아래 SHOW INDEX로 사전 확인 권장.
-- MySQL은 CREATE INDEX에 IF NOT EXISTS를 지원하지 않으므로, 인덱스 존재 시 해당 CREATE는 건너뛸 것.

-- ---------- 사전 확인 (주석 해제 후 실행해 보기) ----------
-- SHOW INDEX FROM shuttle_stop_candidate WHERE Key_name = 'idx_cand_doc_job_status';
-- SHOW INDEX FROM shuttle_stop_candidate WHERE Key_name = 'idx_cand_doc_job_status_method';
-- SHOW INDEX FROM shuttle_stop_alias WHERE Key_name = 'idx_alias_active_updated';
-- ----------

-- (1) shuttle_stop_candidate: review_queue / ops_dashboard JOIN·WHERE 조건
CREATE INDEX idx_cand_doc_job_status
  ON shuttle_stop_candidate(source_doc_id, created_job_id, status);

-- (2) shuttle_stop_candidate: ops_dashboard scalar subquery 최적화용 (status + match_method 커버)
CREATE INDEX idx_cand_doc_job_status_method
  ON shuttle_stop_candidate(source_doc_id, created_job_id, status, match_method);

-- (3) shuttle_stop_alias: alias_audit 필터(is_active) + 정렬(updated_at DESC)
CREATE INDEX idx_alias_active_updated
  ON shuttle_stop_alias(is_active, updated_at);

-- ---------- 롤백 (필요 시만 실행) ----------
-- DROP INDEX idx_cand_doc_job_status ON shuttle_stop_candidate;
-- DROP INDEX idx_cand_doc_job_status_method ON shuttle_stop_candidate;
-- DROP INDEX idx_alias_active_updated ON shuttle_stop_alias;
