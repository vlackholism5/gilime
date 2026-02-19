# v1.7-06 Gate (Approver + approval audit)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| G1 | schema | sql/releases/v1.7/schema/schema_06_approver_role_audit.sql, validation 결과. |
| G2 | approver만 Publish | role!=approver 시 blocked_not_approver. |
| G3 | approval 로그 | publish_blocked, publish_success 기록. |
| G4 | UI Role | alert_ops 상단 Role: approver/user. |
| G5 | 회귀 없음 | 초안/프리뷰/큐 유지. |
