# v1.7-02 Gate (Draft/Publish)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| **G1** | 스키마 적용 | `sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql` 실행. `SHOW COLUMNS` / information_schema 로 `published_at` NULL 허용 확인. |
| **G2** | 초안 생성 (EID) | alert_ops에서 published_at 비우고 Create → "created". draft_only=1 목록에 해당 행 표시. **EID:** 8 |
| **G3** | 사용자 페이지에 초안 미노출 | EID 해당 이벤트는 `/user/alerts.php` 에 노출되지 않음 (published_at IS NULL). |
| **G4** | Publish 후 사용자 노출 | EID 행에 [Publish] 클릭 → flash=published. published_only=1 목록 + user alerts에 노출. delivery 동작 변경 없음. |
| **G5** | 검증 SQL | `sql/releases/v1.7/validation/validation_02_draft_publish.sql` 실행. content_hash 중복 0 rows. draft_cnt / published_cnt 일치. |

## Non-goals (v1.7-02)

- Unpublish(발행→초안 되돌리기) 없음.
- Approval workflow(approved 상태) 없음. Draft vs Published 만 (published_at NULL / NOT NULL).
- Outbound 채널(email/SMS/push) 없음.
