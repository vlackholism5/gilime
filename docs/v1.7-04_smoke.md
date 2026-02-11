# v1.7-04 Smoke (Approval + Publish guard)

1. draft 생성. draft_only에서 확인.
2. targeting preview에서 target_user_cnt 확인.
3. Publish 클릭 후 published_only 이동, flash=published. user/alerts 노출 확인.
4. target_user_cnt=0 이벤트로 Publish 시 blocked_no_targets, published_at 미변경 확인.
