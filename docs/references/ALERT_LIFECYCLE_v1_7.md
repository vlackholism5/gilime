# ALERT Lifecycle v1.7

## 상태 모델
- `draft`: 작성 중, 사용자 노출 없음.
- `approved`: 운영 검토 완료.
- `published`: 사용자 노출 가능한 상태(`published_at` 기준).
- `delivered`: 채널별 전달 기록이 남은 상태(`shown`/`sent`).

## 권한 모델
- 관리자(Admin): 작성/승인/발행/재전달(정책 범위 내) 가능.
- 사용자(User): 조회만 가능, 상태 변경 불가.

## 전이 규칙
1. `draft -> approved`: 운영 검토 완료 시.
2. `approved -> published`: 발행 수행 시.
3. `published -> delivered`: 타겟 계산 후 채널별 전달 기록 생성.

## 중복 방지/멱등성
- `content_hash` 중복 이벤트는 삽입 차단 또는 무시 정책을 유지한다.
- `app_alert_deliveries`는 `(alert_event_id, user_id, channel)` 유니크를 유지한다.
- 재실행 시 `INSERT ... ON DUPLICATE KEY UPDATE`로 중복 행 생성 없이 상태만 갱신한다.

## 운영 규칙
- 사용자 페이지는 `published_at IS NOT NULL`만 노출한다.
- 운영 감사는 전달 상태(`shown`, `sent`, `failed`)를 분리 집계한다.
- 실패 재처리 시 기존 행 업데이트를 우선한다.

## 확인 필요
- `published_at`를 draft 구분용으로 사용할지, 별도 status 컬럼을 둘지 최종 확정 필요.
