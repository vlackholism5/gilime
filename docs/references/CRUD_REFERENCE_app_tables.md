# CRUD Reference (MVP)

## 범위
- 주요 `app_*` + 운영 핵심 테이블의 MVP CRUD 권한 매트릭스.

| Resource | User | Admin |
|---|---|---|
| `app_alert_events` | R | CRUD |
| `app_alert_deliveries` | R(본인 노출 간접) | R/재처리 |
| `app_subscriptions` | CRUD(본인) | R |
| `shuttle_source_doc` | - | CRUD |
| `shuttle_stop_candidate` | - | CRUD(검수) |
| `shuttle_route_stop` | R(간접 조회) | CRUD(승격 결과) |
| `shuttle_stop_alias` | - | CRUD |
| `shuttle_doc_job_log` | - | R |
| `shuttle_parse_metrics` | - | R |

## 비고
- 사용자의 `app_alert_deliveries`는 직접 수정하지 않고 렌더링 시 기록된다.
- 승격 결과(`shuttle_route_stop`)는 관리자 승격 액션을 통해서만 갱신한다.
