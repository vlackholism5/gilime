# RESET 01 — Inventory

## 1) Pages

### User pages (path + purpose)

| Path | Purpose |
|------|--------|
| public/user/home.php | 사용자 홈 — 출발/도착·이슈 Top3·길찾기 진입 |
| public/user/routes.php | 노선 목록 — 구독/노선 탐색 |
| public/user/alerts.php | 알림 — published 이벤트 목록·배달 기록 |
| public/user/my_routes.php | 내 노선 — 구독 노선 관리 |
| public/user/route_finder.php | 경로 찾기 — 정류장 자동완성·경로 안내 |
| public/user/issues.php | 이슈 목록 |
| public/user/issue.php | 이슈 상세 |
| public/user/journey.php | 여정/경로 상세 |

### Admin pages (path + purpose)

| Path | Purpose |
|------|--------|
| public/admin/index.php | 문서 허브 — source_doc 목록·검수/운영 링크 |
| public/admin/login.php | 관리자 로그인 |
| public/admin/logout.php | 관리자 로그아웃 |
| public/admin/doc.php | 문서 상세 — 메타·실행 버튼·PARSE_MATCH 메트릭·Next Actions |
| public/admin/upload_pdf.php | PDF 업로드 — source_doc 생성·run_job 바로가기 |
| public/admin/run_job.php | Job 실행 — PARSE_MATCH/PROMOTE 등 트리거 |
| public/admin/route_review.php | 노선 검수 — 후보 승인/거절/별칭·Promote |
| public/admin/review_queue.php | 검수 대기 — 문서/노선별 pending 요약·링크 |
| public/admin/alias_audit.php | Alias 감사 — 이슈·최근 alias 목록 |
| public/admin/ops_dashboard.php | 운영 대시보드 — 검수 필요·최근 job·Promote 후보 |
| public/admin/alert_ops.php | 알림 운영 — 이벤트 목록·Draft/Publish·타겟 프리뷰 |
| public/admin/alert_event_audit.php | 알림 감사 — 이벤트별 필터·드릴다운 |
| public/admin/ops_summary.php | 운영 요약 — approvals/events/deliveries/outbound 안내 |
| public/admin/ops_control.php | 운영 제어 — retry/backoff·failed Top20·outbound stub·metrics CLI 링크 |
| public/admin/promote.php | 승격 실행 (route_review에서 호출) |
| public/admin/parse_match.php | 파싱/매칭 실행 (doc/run_job에서 호출) |
| public/admin/export_candidates.php | 후보 내보내기 |
| public/admin/import_candidate_review.php | 후보 검수 데이터 가져오기 |
| public/admin/run_gpt_review.php | GPT 검수 실행 |

---

## 2) API

| Method | Route | Handler | Request / Response (요약) |
|--------|-------|---------|---------------------------|
| GET | /api/route/suggest_stops | public/api/route/suggest_stops.php | Query: `q` (검색어). Response: `{ "items": [ { "stop_id", "stop_name", ... } ] }` |
| GET | /api?path=debug/ping | public/api/index.php | GILIME_DEBUG=1 시. Response: `{ "ok", "ts", "db_ok" }` |
| GET | /api?path=debug/echo-trace | public/api/index.php | Response: `{ "ok", "trace_id" }` |
| POST | /api?path=subscription/toggle | public/api/index.php | Body: `{ "action", "doc_id", "route_label" }`. Response: `{ "ok", "action" }` |

---

## 3) DB

### app_*

| Table | Purpose |
|-------|---------|
| app_users | 사용자(이메일·display_name·role 등) |
| app_user_sessions | 세션(토큰·만료) |
| app_subscriptions | 구독( user_id, target_type, target_id, alert_type, is_active ) |
| app_alert_events | 알림 이벤트(제목·본문·ref·published_at·content_hash 등) |
| app_alert_deliveries | 배달 로그(이벤트·사용자·채널·상태·retry_count 등) |
| app_alert_approvals | 발행 승인 감사(이벤트·액터·액션) |

### shuttle_*

| Table | Purpose | 비고 |
|-------|---------|------|
| shuttle_source_doc | 원본 문서(PDF 등) 메타·ocr_status·parse_status | DDL 위치 분산(확인 필요) |
| shuttle_doc_job_log | Job 로그(PARSE_MATCH/PROMOTE 등)·base_job_id·route_label | |
| shuttle_stop_candidate | 정류장 후보(검수 상태·match_method·created_job_id) | |
| shuttle_route_stop | 승격된 정류장 순서(is_active·stop_order) | |
| shuttle_stop_alias | 정류장 별칭(canonical·alias_text) | |
| shuttle_stop_normalize_rule | 정규화 규칙 | |
| shuttle_parse_metrics | PARSE_MATCH 품질(parse_job_id·route_label 단위) | |
| shuttle_temp_route | 임시 노선(파싱 중간) | 확인 필요 |
| shuttle_temp_route_stop | 임시 노선 정류장 | 확인 필요 |

### seoul_bus_*

| Table | Purpose |
|-------|---------|
| seoul_bus_stop_master | 서울 정류장 마스터 |
| seoul_bus_route_master | 서울 노선 마스터 |
| seoul_bus_route_stop_master | 서울 노선별 정류장 순서 |

### Unused / unclear

- **shuttle_temp_\*** — 임시 파싱용·용도 확인 필요.
- **shuttle_source_doc** — DDL 단일 파일 부재(레거시 스키마 분산). 확인 필요.

---

## 4) Works vs Broken

### What works now (confirmed)

- 관리자: 로그인, index(문서 허브), doc, upload_pdf, run_job, route_review, review_queue, alias_audit, ops_dashboard, alert_ops, alert_event_audit, ops_summary, ops_control.
- 사용자: home, routes, alerts.
- API: suggest_stops, subscription/toggle (로그인 사용자).
- v1.7 게이트 항목(STATUS_FOR_GPT·docs/releases/v1.7/RELEASE_GATE.md 기준): 관리자 작성/발행 흐름, 타겟 프리뷰/전달 이력, 전달 중복 0, 사용자 알림·경로 안내, 검수/승격 반영.

### What is broken / missing (suspected)

- 프로덕션 인증 미교체(쿠키·anonymous 세션).
- 실알림 수집/채널 미연동(스텁만 존재).
- OCR 자동화 미구현(설계 문서만 유지).

### 확인 필요

- route_label 미적용 DB에서 alert_ops route/subscribed 필터 동작 여부.
- run_alert_ingest_stub 외 실데이터 파이프라인 경계.

---

## 5) Next actions (≤5)

1. **DB 스키마 단일 SoT 정리** — app_*·shuttle_* DDL을 docs/references 또는 sql/releases 기준으로 문서화.
2. **인증/세션** — 프로덕션 로그인·회원가입 범위 결정 및 이슈화.
3. **알림 수집** — 스텁 vs 실데이터 경계 명시 및 연동 계획 문서화.
4. **RESET 02** — SoT → DB → API → UI 순 검증 체크리스트 작성.
5. **2026-02-20 MVP DoD** — 업로드→파싱/매칭→검수→승격→경로 안내→감사 1회 E2E 수동 실행 및 증거 저장.
