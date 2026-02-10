<?php
/**
 * v0.6-8 ~ v0.6-18 통합 마이그레이션 + 더미 데이터
 * CLI에서 실행: php scripts/migrate_v06_8_to_18.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/inc/db.php';

$pdo = pdo();

echo "=== GILIME MVP v0.6-8 ~ v0.6-18 마이그레이션 시작 ===\n\n";

// ---------- v0.6-8: shuttle_route_stop promoted_job_id 제거 ----------
echo "[v0.6-8] shuttle_route_stop: promoted_job_id 제거 중...\n";
try {
    $pdo->exec("DROP PROCEDURE IF EXISTS drop_promoted_job_id_if_exists");
    $pdo->exec("
        CREATE PROCEDURE drop_promoted_job_id_if_exists()
        BEGIN
          IF (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_route_stop' AND COLUMN_NAME = 'promoted_job_id') > 0
          THEN
            ALTER TABLE shuttle_route_stop DROP COLUMN promoted_job_id;
          END IF;
        END
    ");
    $pdo->exec("CALL drop_promoted_job_id_if_exists()");
    $pdo->exec("DROP PROCEDURE IF EXISTS drop_promoted_job_id_if_exists");
    echo "  ✓ promoted_job_id 제거 완료\n\n";
} catch (Exception $e) {
    echo "  ⚠ 오류 (이미 제거되었거나 없을 수 있음): " . $e->getMessage() . "\n\n";
}

// ---------- v0.6-9: job_log에 base_job_id, route_label 추가 ----------
echo "[v0.6-9] shuttle_doc_job_log: base_job_id, route_label 추가 중...\n";
try {
    $pdo->exec("
        ALTER TABLE shuttle_doc_job_log
          ADD COLUMN IF NOT EXISTS base_job_id BIGINT UNSIGNED NULL COMMENT 'PROMOTE 시 기반이 된 PARSE_MATCH job_id',
          ADD COLUMN IF NOT EXISTS route_label VARCHAR(50) NULL COMMENT 'PROMOTE 시 대상 route_label'
    ");
    echo "  ✓ 컬럼 추가 완료\n";
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS ix_job_doc_type_status ON shuttle_doc_job_log (source_doc_id, job_type, job_status, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS ix_job_base_job ON shuttle_doc_job_log (base_job_id)");
    echo "  ✓ 인덱스 추가 완료\n\n";
} catch (Exception $e) {
    echo "  ⚠ 오류 (이미 존재할 수 있음): " . $e->getMessage() . "\n\n";
}

// ---------- v0.6-10: seoul_bus_stop_master 테이블 생성 + 더미 데이터 ----------
echo "[v0.6-10] seoul_bus_stop_master 테이블 생성 중...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seoul_bus_stop_master (
          stop_id        BIGINT UNSIGNED NOT NULL COMMENT '정류장ID',
          stop_name      VARCHAR(100) NOT NULL DEFAULT '' COMMENT '정류장명칭',
          district_code  VARCHAR(20) NULL DEFAULT NULL COMMENT '시군구코드',
          lat            DECIMAL(12, 8) NULL DEFAULT NULL COMMENT '위도',
          lng            DECIMAL(12, 8) NULL DEFAULT NULL COMMENT '경도',
          raw_json       JSON NULL DEFAULT NULL COMMENT '원본행 JSON',
          created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (stop_id),
          KEY ix_seoul_stop_name (stop_name(50))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='서울시 버스 정류장 마스터'
    ");
    echo "  ✓ 테이블 생성 완료\n";
    
    // 더미 데이터 추가
    $pdo->exec("
        INSERT IGNORE INTO seoul_bus_stop_master (stop_id, stop_name, district_code) VALUES
        (100000001, '강남역', '11680'),
        (100000002, '서울역', '11110'),
        (100000003, '홍대입구역', '11380'),
        (100000004, '사당역', '11650'),
        (100000005, '신촌역', '11380'),
        (100000006, '역삼역', '11680'),
        (100000007, '선릉역', '11680'),
        (100000008, '삼성역', '11680'),
        (100000009, '종로3가역', '11110'),
        (100000010, '시청역', '11110')
    ");
    $cnt = $pdo->query("SELECT COUNT(*) FROM seoul_bus_stop_master")->fetchColumn();
    echo "  ✓ 더미 데이터 추가 완료 (총 {$cnt}건)\n\n";
} catch (Exception $e) {
    echo "  ⚠ 오류: " . $e->getMessage() . "\n\n";
}

// ---------- v0.6-11: candidate에 자동매칭 컬럼 추가 ----------
echo "[v0.6-11] shuttle_stop_candidate: 자동매칭 컬럼 추가 중...\n";
try {
    $pdo->exec("
        ALTER TABLE shuttle_stop_candidate
          ADD COLUMN IF NOT EXISTS matched_stop_id   VARCHAR(50)  NULL DEFAULT NULL COMMENT '자동/수동 매칭 정류장ID' AFTER raw_stop_name,
          ADD COLUMN IF NOT EXISTS matched_stop_name VARCHAR(100) NULL DEFAULT NULL COMMENT '매칭 정류장명' AFTER matched_stop_id,
          ADD COLUMN IF NOT EXISTS match_score       DECIMAL(3,2) NULL DEFAULT NULL COMMENT '1.0=exact, 0.7=fallback' AFTER matched_stop_name,
          ADD COLUMN IF NOT EXISTS match_method      VARCHAR(30)  NULL DEFAULT NULL COMMENT 'exact|normalized|like_prefix|manual_approve' AFTER match_score
    ");
    echo "  ✓ 컬럼 추가 완료\n\n";
} catch (Exception $e) {
    echo "  ⚠ 오류 (이미 존재할 수 있음): " . $e->getMessage() . "\n\n";
}

// ---------- v0.6-12: alias 테이블 생성 + 더미 데이터 ----------
echo "[v0.6-12] shuttle_stop_alias, shuttle_stop_normalize_rule 테이블 생성 중...\n";
try {
    $pdo->exec("
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
          COMMENT='정류장 동의어/예외 사전(alias→canonical)'
    ");
    echo "  ✓ shuttle_stop_alias 테이블 생성 완료\n";
    
    $pdo->exec("
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
          COMMENT='정류장명 정규화 규칙(선택)'
    ");
    echo "  ✓ shuttle_stop_normalize_rule 테이블 생성 완료\n";
    
    // 더미 alias 추가
    $pdo->exec("
        INSERT IGNORE INTO shuttle_stop_alias (alias_text, canonical_text, rule_version) VALUES
        ('강남', '강남역', 'v0.6-12'),
        ('홍대', '홍대입구역', 'v0.6-12'),
        ('사당', '사당역', 'v0.6-12')
    ");
    $aliasCnt = $pdo->query("SELECT COUNT(*) FROM shuttle_stop_alias")->fetchColumn();
    echo "  ✓ 더미 alias 추가 완료 (총 {$aliasCnt}건)\n\n";
} catch (Exception $e) {
    echo "  ⚠ 오류: " . $e->getMessage() . "\n\n";
}

// ---------- v0.6-13 ~ v0.6-18: validation만 (DDL 없음) ----------
echo "[v0.6-13 ~ v0.6-18] validation 전용 버전 (DDL 변경 없음)\n\n";

echo "=== 마이그레이션 완료 ===\n\n";

// 최종 검증
echo "--- 최종 검증 ---\n";
$tables = ['seoul_bus_stop_master', 'shuttle_stop_alias', 'shuttle_stop_normalize_rule'];
foreach ($tables as $table) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    echo "{$table}: {$cnt}건\n";
}

$cols = $pdo->query("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_stop_candidate'
      AND COLUMN_NAME IN ('matched_stop_id','matched_stop_name','match_score','match_method')
")->fetchAll(PDO::FETCH_COLUMN);
echo "shuttle_stop_candidate 자동매칭 컬럼: " . implode(', ', $cols) . "\n";

$jobCols = $pdo->query("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_doc_job_log'
      AND COLUMN_NAME IN ('base_job_id','route_label')
")->fetchAll(PDO::FETCH_COLUMN);
echo "shuttle_doc_job_log 추적 컬럼: " . implode(', ', $jobCols) . "\n\n";

echo "브라우저에서 route_review.php를 새로고침하세요.\n";
