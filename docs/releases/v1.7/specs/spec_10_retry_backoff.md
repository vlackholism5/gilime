# Retry/Backoff (v1.7-10)

outbound stub processor가 실패를 재시도 가능한 실패로 남기고, backoff에 따라 재시도하도록 개선.

## 대상

- app_alert_deliveries WHERE status IN ('pending','failed') AND channel='web'
- pending: 항상 재시도 대상. failed: backoff 창 경과한 경우만.

## Backoff (분)

- attempt 1: 1분, 2: 5분, 3: 15분, 4 이상: 60분.
- 창 경과: created_at <= NOW() - INTERVAL N MINUTE (retry_count별 N).

## 처리

- 성공: status=sent, delivered_at=NOW(), last_error=NULL. retry_count 유지.
- 실패: status=failed, last_error=stub_fail, retry_count+1, delivered_at=NULL, sent_at=NULL.

## 스크립트 출력

processed=N sent=X failed=Y skipped_backoff=Z

## 스키마

app_alert_deliveries.retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0 (v1.7-10_schema.sql).

## Non-goals

- 실제 외부 채널 연동. 큐 워커/크론 등록.
