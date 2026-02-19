# v1.7-08 Gate (Subscription alert_type matching)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| G1 | FIND_IN_SET 적용 | alert_ops 4곳(Publish guard cnt/list, Preview cnt/list) LIKE 제거, FIND_IN_SET 사용. |
| G2 | 타겟 결과 합리성 | event_type이 alert_type CSV 토큰에 정확히 포함될 때만 타겟. 오탐/누락 없음. |
| G3 | 문서/검증 | docs/releases/v1.7/specs/spec_08_subscription_matching.md, docs/releases/v1.7/smoke/smoke_08_subscription_matching.md, sql/releases/v1.7/validation/validation_08_subscription_matching.sql. |

## Non-goals

- 복잡한 조건식/우선순위 엔진 없음.
