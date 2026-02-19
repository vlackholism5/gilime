# RESET 00 — Snapshot

## Timestamp

- **문서 작성 시점:** 2026-02-19 (RESET baseline 기준일: 2026-02-14)

## Current branch

- `main`

## Last 5 commits

다음 명령으로 확인:

```bash
git log -5 --oneline
```

기록값 (스냅샷 시점):

```
baf9592 feat(v1.7-18): standardize bootstrap-first ui system for admin and user pages
5b7fb81 feat(v1.7-17): add minimal pdf ingest (upload -> run_job) flow
fd049f0 perf(v1.7-16): unify parse_status policy and batch selection output
730cb07 feat(v1.7-15): normalize legacy parse errors for ops TopN
349c4a2 feat(v1.7-12): add ops control view + bundled gate docs
```

## High-level architecture summary (1 page)

### SoT (Source of Truth)

- **상태/버전:** docs/STATUS_FOR_GPT.md — 현재 버전, SoT 규칙, 제약.
- **UI 시스템:** docs/ui/SOT_GILAIME_UI_SYSTEM.md — 토큰, 컴포넌트, 적용 상태.
- **문서 인덱스:** docs/INDEX.md — 핵심 문서, releases 구조, SQL 인덱스 링크.

### DB

- **app_\*** — users, user_sessions, subscriptions, alert_events, alert_deliveries, alert_approvals. 사용자·세션·구독·알림 이벤트·배달·승인 감사.
- **shuttle_\*** — source_doc, doc_job_log, stop_candidate, route_stop, stop_alias, stop_normalize_rule, parse_metrics, temp_route, temp_route_stop. 파싱/검수/승격/메트릭/임시 파싱.
- **seoul_bus_\*** — stop_master, route_master, route_stop_master. 서울 정류장·노선 공개 데이터.

### API

- **진입점:** public/api/index.php — `GET path=` 쿼리로 디스패치. trace_id, debug/ping, debug/echo-trace, subscription/toggle.
- **정류장 자동완성:** public/api/route/suggest_stops.php — GET `q=` 검색어. 라우팅은 .htaccess RewriteRule (api/route/suggest_stops → 해당 PHP).

### UI

- **Admin:** public/admin/ — index, login, logout, doc, upload_pdf, run_job, route_review, review_queue, alias_audit, ops_dashboard, alert_ops, alert_event_audit, ops_summary, ops_control, promote, parse_match, export_candidates, import_candidate_review, run_gpt_review.
- **User:** public/user/ — home, routes, alerts, my_routes, route_finder, issues, issue, journey.
- **공통:** public/assets/css/gilaime_ui.css.

### Ops

- **스크립트:** scripts/ — run_job(파싱/매칭/승격), run_parse_match_batch, run_delivery_outbound_stub, run_alert_ingest_real_metrics 등.
- **문서:** docs/releases/v1.7/ — specs, smoke, gate.

---

## Known pain points (top 5)

1. **MVP2 인증:** 쿠키 기반 anonymous session 사용. 프로덕션 로그인/회원가입 미교체 (확인 필요).
2. **알림 수집:** run_alert_ingest_stub 스텁. 외부 API/이벤트 미연동 (확인 필요).
3. **alias_text<=2 legacy 3건:** v0.6-21 이전 데이터 잔존. 동작 영향 없음, 정리 후속.
4. **route_label 미적용:** 해당 DDL 미적용 시 alert_ops route/subscribed 필터 비활성.
5. **문서/스키마 혼재:** flat(v0.6~v1.6)과 releases/v1.7 혼재 — 단일 SoT로 정리 필요.
