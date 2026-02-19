# v1.7-07 Gate (Outbound stub)

## Gate

- Schema: delivered_at, last_error 컬럼 존재.
- Stub: pending → sent 전환, delivered_at 설정. 실패 시 failed + last_error.
- Validation SQL: 상태별 카운트, 최근 20건.

## Non-goals

- 실제 이메일/SMS/푸시 없음.
