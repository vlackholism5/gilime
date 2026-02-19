# RETRY Policy v1.7

## 목표
- 재전송/재집계 수행 시 중복 폭증 없이 상태를 복구한다.

## 기본 규칙
- 재전송 허용 대상: `pending`, `failed`.
- 재전송 금지 대상: 이미 최종 처리(`shown`, `sent`)된 레코드의 신규 삽입.
- 동일 `(alert_event_id, user_id, channel)` 키는 단일 행만 유지한다.

## 상태 전환
- `failed -> sent`: 재전송 성공 시 갱신 가능.
- `pending -> sent`: 1차 전송 성공 시 갱신.
- `sent -> sent`: 재실행 시 no-op 또는 동일 값 업데이트.

## 구현 원칙
- 삽입: `INSERT ... ON DUPLICATE KEY UPDATE`.
- 감사: 전환 전/후 카운트를 남긴다.
- 예외: 외부 채널 실패 시 `last_error`, `retry_count` 갱신.

## 검증 항목
1. 재실행 전/후 유니크 위반 0건.
2. `failed` 대상 재전송 후 `sent` 전환 확인.
3. 재실행 횟수 증가에도 전체 행 수가 비정상 증가하지 않음.
