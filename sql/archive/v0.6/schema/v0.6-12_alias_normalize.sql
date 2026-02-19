-- v0.6-12: 정류장명 정규화 + 동의어(alias) 사전
-- 자동매칭 품질 개선: raw → normalized → alias → canonical → stop_master 재시도

-- 동의어 사전 (alias_text → canonical_text)
CREATE TABLE IF NOT EXISTS shuttle_stop_alias (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alias_text    VARCHAR(100) NOT NULL COMMENT '원문/동의어(유니크)',
  canonical_text VARCHAR(100) NOT NULL COMMENT '정식 명칭(stop_master 매칭용)',
  rule_version  VARCHAR(20)  NULL DEFAULT 'v0.6-12',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shuttle_stop_alias_text (alias_text(80)),
  KEY ix_shuttle_stop_alias_canonical (canonical_text(50)),
  KEY ix_shuttle_stop_alias_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='정류장 동의어/예외 사전(alias→canonical)';

-- (선택) 정규화 규칙 테이블 — 추후 rule_type 확장용
CREATE TABLE IF NOT EXISTS shuttle_stop_normalize_rule (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_type  VARCHAR(30)  NOT NULL DEFAULT 'collapse_space' COMMENT 'trim,collapse_space,remove_suffix 등',
  rule_value VARCHAR(100) NULL DEFAULT NULL,
  priority   SMALLINT     NOT NULL DEFAULT 0 COMMENT '높을수록 먼저 적용',
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_normalize_rule_priority (priority DESC, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='정류장명 정규화 규칙(선택)';

-- ---------- 검증 쿼리 8개 ----------
-- 1) shuttle_stop_alias 테이블/컬럼 존재
-- SELECT COLUMN_NAME FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_stop_alias' ORDER BY ORDINAL_POSITION;

-- 2) shuttle_stop_alias 유니크/인덱스
-- SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_stop_alias';

-- 3) alias 등록 건수 및 샘플
-- SELECT COUNT(*) AS cnt FROM shuttle_stop_alias WHERE is_active = 1;
-- SELECT id, alias_text, canonical_text, rule_version FROM shuttle_stop_alias WHERE is_active = 1 LIMIT 5;

-- 4) PARSE_MATCH 후 match_method별 건수 (alias_exact, alias_normalized 포함)
-- SELECT match_method, COUNT(*) AS cnt FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = :latest_job_id
-- GROUP BY match_method;

-- 5) normalized 표시와 raw 비교 (route_review와 동일 규칙: trim+collapse space)
-- SELECT id, raw_stop_name, TRIM(REGEXP_REPLACE(raw_stop_name, '\\s+', ' ')) AS normalized
-- FROM shuttle_stop_candidate WHERE source_doc_id = :doc AND created_job_id = :latest_job_id LIMIT 5;

-- 6) alias 적용 시 canonical이 stop_master에 존재하는지
-- SELECT a.alias_text, a.canonical_text,
--   (SELECT COUNT(*) FROM seoul_bus_stop_master m WHERE m.stop_name = a.canonical_text) AS master_match
-- FROM shuttle_stop_alias a WHERE a.is_active = 1 LIMIT 10;

-- 7) SoT: latest PARSE_MATCH 후보 수 및 alias로 매칭된 건 수
-- SELECT created_job_id,
--   COUNT(*) AS total,
--   SUM(CASE WHEN match_method IN ('alias_exact','alias_normalized') THEN 1 ELSE 0 END) AS alias_matched
-- FROM shuttle_stop_candidate
-- WHERE source_doc_id = :doc AND created_job_id = (SELECT id FROM shuttle_doc_job_log
--   WHERE source_doc_id = :doc AND job_type = 'PARSE_MATCH' AND job_status = 'success' ORDER BY id DESC LIMIT 1)
-- GROUP BY created_job_id;

-- 8) shuttle_stop_normalize_rule 테이블 존재(선택)
-- SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_stop_normalize_rule';
