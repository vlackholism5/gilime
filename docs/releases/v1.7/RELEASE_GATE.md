# v1.7 Release Gate

## Gate 항목
- [x] 관리자 작성/발행 흐름 동작
- [x] 타겟 프리뷰/전달 이력 조회 가능
- [x] 전달 중복 0건
- [x] 사용자 알림 조회 동작
- [x] 사용자 경로 안내 조회 동작
- [x] 검수/승격 이후 사용자 반영 확인

## E2E 증거 목록
- 코드 변경 diff
- 화면 캡처(관리자/사용자)
- SQL 검증 결과
- 로그 증거

## SQL 검증 (복붙용)
```sql
-- 최근 이벤트 10건
SELECT id, event_type, published_at, created_at
FROM app_alert_events
ORDER BY id DESC
LIMIT 10;

-- 이벤트별 전달 건수
SELECT alert_event_id, channel, status, COUNT(*) AS cnt
FROM app_alert_deliveries
GROUP BY alert_event_id, channel, status
ORDER BY alert_event_id DESC, channel, status;

-- 중복 체크 (0건이어야 정상)
SELECT alert_event_id, user_id, channel, COUNT(*) AS dup_cnt
FROM app_alert_deliveries
GROUP BY alert_event_id, user_id, channel
HAVING COUNT(*) > 1;
```

## 판정 규칙
- 모든 항목 체크 + 중복 0건이면 PASS.
- 미충족 항목이 있으면 HOLD 후 보완.
