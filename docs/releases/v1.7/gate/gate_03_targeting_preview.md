# v1.7-03 Gate (Targeting Preview)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| **G1** | 파일 생성/갱신 OK | docs/releases/v1.7/specs/spec_03_targeting_preview.md, smoke/gate, alert_ops Targeting Preview, sql/releases/v1.7/validation/validation_03_targeting_preview.sql. |
| **G2** | 프리뷰 count/list 쿼리 동작 OK | event_id 지정 시 target_user_cnt 및 상위 20명 리스트 표시. |
| **G3** | 구독 1명 이상이면 프리뷰 리스트에 노출 OK | routes.php에서 R1 등 구독한 user가 프리뷰 테이블에 포함됨. |
| **G4** | alert_type mismatch 시 제외 OK | alert_type에 event_type이 포함되지 않은 구독은 카운트/리스트에서 제외. |
| **G5** | user/alerts.php에는 초안 노출 없음 유지 OK | v1.7-02와 동일. published_at IS NOT NULL 만 노출. |

## Non-goals (v1.7-03)

- 실제 발송(insert deliveries) 금지.
- 채널 확장(email/SMS) 금지.
- 승인 플로우·스케줄러/배치 금지.
