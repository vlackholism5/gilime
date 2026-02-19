# v1.7-05 Smoke (Deliveries pre-write)

1. route 구독 유저 1~2명 확보. draft 생성 → targeting preview에서 2명 확인.
2. Publish → flash에 published (queued N) 또는 queued_cnt 확인.
3. Workbench: EID 기준 deliveries pending count >= 2 확인.
4. user/alerts 접속 → pending이 shown으로 전환, pending 감소 확인.
5. 새로고침 → shown count 불변. (B) 중복 0 rows 확인.
