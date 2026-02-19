-- v0.6-8: shuttle_route_stop legacy 정리
-- 목적: promoted_job_id 및 관련 인덱스 제거, 스냅샷 기준은 created_job_id로만 통일
-- 유지: uq_route_snapshot_order(source_doc_id, route_label, created_job_id, stop_order)

-- 1) promoted_job_id를 참조하는 인덱스 확인 후 수동 DROP
--    (아래 쿼리 실행 후 나온 INDEX_NAME 각각에 대해 2) 실행)
-- SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_route_stop' AND COLUMN_NAME = 'promoted_job_id';

-- 2) 예: 인덱스명이 ix_route_stop_promoted_job 이면
-- DROP INDEX ix_route_stop_promoted_job ON shuttle_route_stop;
-- (위 1)에서 조회된 이름으로 교체)

-- 3) promoted_job_id 컬럼 제거 (존재할 때만 실행, 없으면 스킵)
DELIMITER //
DROP PROCEDURE IF EXISTS drop_promoted_job_id_if_exists//
CREATE PROCEDURE drop_promoted_job_id_if_exists()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_route_stop' AND COLUMN_NAME = 'promoted_job_id') > 0
  THEN
    ALTER TABLE shuttle_route_stop DROP COLUMN promoted_job_id;
  END IF;
END//
DELIMITER ;
CALL drop_promoted_job_id_if_exists();
DROP PROCEDURE IF EXISTS drop_promoted_job_id_if_exists;

-- ---------- 적용 후 검증 (ACCEPTANCE A: promoted_job_id 미존재) ----------
-- 아래 두 쿼리 결과가 각각 0건이어야 함.
-- SELECT COUNT(*) FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_route_stop' AND COLUMN_NAME = 'promoted_job_id';
-- SELECT COUNT(*) FROM information_schema.STATISTICS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_route_stop' AND COLUMN_NAME = 'promoted_job_id';
