# v1.7-06 Smoke (Approver + audit)

1. Workbench: user_id=1 role=approver, user_id=2 role=user.
2. user_id=2로 Publish 시도 -> blocked_not_approver, approvals 1건.
3. user_id=1로 Publish -> published, approvals 1건.
4. approvals 최근 20건 action 2종 확인.
5. alert_ops 상단 Role 표시 확인.
