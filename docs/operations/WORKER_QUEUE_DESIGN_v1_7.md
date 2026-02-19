# 비동기 워커/큐 확장 설계 (v1.7)

## 목적
- 현재 동기 PARSE_MATCH(run_job.php)를 유지하면서,
- 2차에서 비동기 워커/큐로 확장하여 대량 처리·재시도·격리(DLQ)를 지원하기 위한 설계 기준.
- **문서만으로 차기 구현 착수 가능**하도록 계약·규칙 명시.

## 1. 잡 상태머신

```
pending → queued → running → success
                     ↓
         retry_wait (재시도 가능)
                     ↓
         failed (재시도 초과) → DLQ
```

| 상태 | 설명 |
|------|------|
| pending | 문서 업로드 완료, 아직 큐에 없음 |
| queued | 큐에 발행됨, 워커 대기 |
| running | 워커가 처리 중 |
| success | 처리 완료 |
| retry_wait | 실패했으나 재시도 대기 중 |
| failed | 재시도 초과, DLQ 이벤트 발생 |

## 2. Idempotency Key · 중복실행 방지

- **idempotency key 형식:** `source_doc_id:{id}:PARSE_MATCH:{requested_at_ms}`
  - 예: `source_doc_id:123:PARSE_MATCH:1739347200000`
- 동일 key로 재요청 시: 기존 running/success job 재사용 또는 skip
- 큐 소비 시: `message_id` + `source_doc_id` 기준으로 중복 처리 방지
- `shuttle_doc_job_log`에 `job_status=running`인 job이 이미 있으면, 새 워커 실행은 해당 job을 재사용하거나 skip

## 3. 중복실행 방지 규칙

- **락:** `source_doc_id` 단위로 분산 락 (Redis 등) 요청 시 선점
- **DB 제약:** `shuttle_doc_job_log`에 `(source_doc_id, job_type, job_status)` 인덱스, `running` 상태에서 중복 INSERT 방지
- **워커:** 메시지 수신 후 `ack` 전에 `running` 상태 갱신, 처리 완료 후 `success`/`failed`

## 4. 운영 지표 (SLA, 실패 TopN, backlog 기준)

| 지표 | 설명 | 기준(예시) |
|------|------|-------------|
| SLA | 처리 완료 시간 (queued → success) | 95p < 60초 |
| 실패 TopN | error_code별 실패 건수 (PARSE_* 코드) | 상위 5개 집계 |
| backlog | queued 대기 건수 | 경고: > 100, critical: > 500 |
| DLQ 적재량 | 재시도 초과 건수 | 경고: > 10 |
| 재시도 횟수 | job당 retry | 최대 3회 |

## 5. 추천 아키텍처 (2차)

```
[run_job.php] → [Queue] → [Worker Pool]
                  ↓
              [DLQ] (재시도 초과)
```

- **Queue:** Redis 기반 (예: Redis Stream, Bull/BullMQ)
- **Worker:** PHP CLI 또는 Python 스크립트, `scripts/php/run_parse_match_batch.php` 로직 재사용
- **배포:** Systemd/Windows Service, 또는 Container

## 6. 2차 착수 체크리스트

- [ ] Queue 선택 (Redis 기반 vs DB 기반)
- [ ] 워커 배포 전략 확정
- [ ] DLQ 운영 정책 및 알림 채널
- [ ] 비용/성능 기준선 정의
