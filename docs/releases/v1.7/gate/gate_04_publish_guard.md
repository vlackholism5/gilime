# v1.7-04 Gate (Approval + Publish guard)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| **G1** | 문서/파일 OK | docs/releases/v1.7/specs/spec_04_approval_flow.md, smoke/gate, alert_ops 상태 뱃지·Publish guard, sql/releases/v1.7/validation/validation_04_publish_guard.sql. |
| **G2** | 상태 뱃지 | 목록에 draft / published 라벨 표시. |
| **G3** | Publish guard | target_user_cnt=0 이면 Publish 차단(blocked_no_targets). target_user_cnt>0 이면 Publish 허용(published). |
| **G4** | flash 문구 | published, blocked_no_targets, failed 표시. |
| **G5** | user 노출 | 발행된 이벤트만 user/alerts에 노출 유지. |

## Non-goals (v1.7-04)

- deliveries 선생성·outbound·타겟팅 정교화·role/권한 금지.
