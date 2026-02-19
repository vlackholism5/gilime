# Draft/Publish (v1.7-02)

`app_alert_events.published_at`을 NULL 허용으로 변경해 draft/published lifecycle을 분리.

## 상태

- draft: `published_at IS NULL`
- published: `published_at IS NOT NULL`

## 동작

1. alert_ops에서 draft 생성
2. Publish 액션으로 draft → published 전환
3. user/alerts는 published만 노출

## Non-goals

- Unpublish 없음
- Approval workflow 없음
- Outbound 채널 없음
