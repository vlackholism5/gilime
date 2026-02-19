# SoT 05 — System Architecture

## Purpose

Single source of truth for system structure and data flow. SoT → DB → API → UI → Ops. Includes admin promote gate (DRAFT→ACTIVE/published).

## Definitions

- **SoT (Source of Truth):** 문서·규칙·상태의 단일 기준. docs/SOT, docs/STATUS_FOR_GPT, docs/INDEX 등.
- **Promote gate:** 후보 검수 후 승격 가능 여부. stale 차단, latest PARSE_MATCH만 승격.
- **DRAFT→ACTIVE:** 알림 이벤트는 draft(published_at NULL) → published(published_at 설정). 문서/노선 측에서는 검수·승격으로 "활성" 전환.

## Diagram (text)

```
[SoT]
  docs/SOT/*, STATUS_FOR_GPT, INDEX, ui/SOT_GILAIME_UI_SYSTEM
       |
       v
[DB]
  app_* (users, sessions, subscriptions, alert_events, alert_deliveries, alert_approvals)
  shuttle_* (source_doc, doc_job_log, stop_candidate, route_stop, alias, parse_metrics, temp_*)
  seoul_bus_* (stop_master, route_master, route_stop_master)
       |
       v
[API]
  public/api/index.php (path=: debug/ping, subscription/toggle, ...)
  public/api/route/suggest_stops.php (GET q=)
       |
       v
[UI]
  Admin: public/admin/* (index, doc, upload_pdf, run_job, route_review, alert_ops, ops_*, ...)
  User:  public/user/* (home, routes, alerts, route_finder, issues, ...)
  Assets: public/assets/css/gilaime_ui.css
       |
       v
[Ops]
  scripts/ (run_job, run_parse_match_batch, run_delivery_outbound_stub, run_alert_ingest_*)
  docs/releases/v1.7 (specs, smoke, gate)
```

## Data flows (summary)

1. **문서:** 업로드 → shuttle_source_doc. run_job(PARSE_MATCH) → shuttle_stop_candidate, shuttle_doc_job_log.
2. **검수·승격:** route_review 승인/거절/별칭 → Promote 시 latest job만 허용 → shuttle_route_stop 갱신. (Admin promote gate: DRAFT→ACTIVE는 알림 측 draft→published; 노선 측은 pending→approved→promote.)
3. **알림:** alert_ops 작성 → app_alert_events(draft). Publish → published_at 설정. 타겟팅 → app_alert_deliveries. 사용자 alerts는 published_at IS NOT NULL만 노출.
4. **경로:** 구독·승격된 route_stop·정류장 순서. suggest_stops API → seoul_bus_stop_master 등.

## Admin promote gate (DRAFT→ACTIVE)

- **알림 이벤트:** draft(published_at NULL) → Publish 액션 → published(published_at 설정). target_user_cnt=0이면 Publish 차단(blocked_no_targets).
- **노선/문서:** 후보는 pending → approved/rejected. Promote는 latest PARSE_MATCH만 허용; stale 후보 승인/승격 차단.
- **역할:** role=approver만 Publish 허용. 그 외는 blocked_not_approver·approval 기록(app_alert_approvals).

## Assumptions

- PHP SSR. 단일 DB(MySQL). 배치/스크립트는 CLI. 외부 길찾기 API 연동은 MVP Core에서 "베이스+셔틀 삽입" 범위만; 상세 확인 필요.

## Open Questions (확인 필요)

- "ACTIVE" 용어를 노선/문서 측에 공식 도입할지, 또는 published/승격 완료로만 표현할지.
- API·UI 간 인증/세션 경계(쿠키·토큰) 문서화 위치.
