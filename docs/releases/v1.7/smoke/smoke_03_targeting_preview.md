# v1.7-03 Smoke Checklist (Targeting Preview)

1. alert_ops에서 초안 생성 (published_at 비움). created 후 event_id로 highlight 확인.
2. event_id로 highlight 및 Targeting Preview 박스 노출 확인.
3. Targeting Preview에서 target_user_cnt, target_user_list 20명 테이블 표시 확인.
4. routes.php에서 R1 구독한 user_id가 프리뷰 리스트에 포함되는지 확인.
5. event_type을 strike→update로 바꾼 새 이벤트 생성 후 프리뷰 결과가 달라지는지 확인.
6. user/alerts.php에는 초안이 안 보이는지 확인 (v1.7-02 유지).
