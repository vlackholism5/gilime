# SQL Index

실무형 정리 2단계 완료: 루트 레거시 `sql/v*.sql`를 버전별로 `sql/releases/<ver>/` 및 `sql/archive/v0.6/`로 이전함. 신규 작업은 `sql/releases/` 구조 사용.

## 레거시 위치 (v0.6 ~ v1.6)

- **v0.6:** `sql/archive/v0.6/schema/`, `sql/archive/v0.6/validation/` (실행 순서/증거는 기존 문서·README 참조)
- **v1.3 ~ v1.6:** `sql/releases/v1.3/` ~ `sql/releases/v1.6/` (각 버전 내 `schema/`, `validation/`)

## 신규 권장 구조

- `sql/releases/v1.7/schema/` : 스키마(ALTER/CREATE)
- `sql/releases/v1.7/validation/` : read-only 검증 쿼리
- `sql/releases/v1.7/verify/` : 운영/자동검증용 SQL
- `sql/operations/` : 버전 독립 운영 쿼리
- `sql/archive/` : 장기 보관

## 파일명 규칙 (신규)

- schema: `schema_XX_<topic>.sql`
- validation: `validation_XX_<topic>.sql`
- verify: `verify_XX_<topic>.sql`

예)
- `schema_10_retry_backoff.sql`
- `validation_10_retry_backoff.sql`
- `verify_10_retry_backoff.sql`

## 실행 순서 원칙

1. schema
2. validation
3. verify(optional)

## v1.7 최근 실행 예시

1. `sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql`
2. `sql/releases/v1.7/validation/validation_02_draft_publish.sql`
3. `sql/releases/v1.7/validation/validation_03_targeting_preview.sql`
4. `sql/releases/v1.7/validation/validation_04_publish_guard.sql`
5. `sql/releases/v1.7/schema/schema_05_deliveries_index.sql`
6. `sql/releases/v1.7/validation/validation_05_delivery_queue.sql`
7. `sql/releases/v1.7/schema/schema_06_approver_role_audit.sql`
8. `sql/releases/v1.7/validation/validation_06_approver_role.sql`
9. `sql/releases/v1.7/schema/schema_07_outbound_stub.sql`
10. `sql/releases/v1.7/validation/validation_07_outbound_stub.sql`
11. `sql/releases/v1.7/validation/validation_08_subscription_matching.sql`
12. `sql/releases/v1.7/validation/validation_09_ops_summary.sql`
13. `sql/releases/v1.7/schema/schema_10_retry_backoff.sql`
14. `sql/releases/v1.7/validation/validation_10_retry_backoff.sql`
15. `sql/releases/v1.7/validation/validation_11_real_metrics_ingest.sql`
16. `sql/releases/v1.7/validation/validation_12_ops_control.sql`

## 마이그레이션 정책

- 1단계(완료): 구조/목차 추가, v1.7 전체 이동 + 참조 링크 동기화
- 2단계(완료): v0.6 → `sql/archive/v0.6/`, v1.3~v1.6 → `sql/releases/<ver>/schema|validation`
  - 실행 순서는 각 버전 폴더 내 README 또는 기존 gate/smoke 문서 참조
