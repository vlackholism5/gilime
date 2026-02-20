# CTO 조직 팀 시뮬레이션 회의록 — Admin QA·파이프라인·Build

**일자:** 2026-02-19  
**범위:** 관리자 페이지 QA 검수 필요 사항, 자동화/파이프라인 구조, 중복·불필요 작업 검토, 포지션별 리뷰, Build 목록 확정.  
**규칙:** 코드 변경 없이 회의록만 작성. 모든 제안 = (Why)(Risk)(Minimal Fix)(Verification). FACT vs OPEN QUESTION 구분.

---

## 1) FACTS (확정 사항)

- **Admin 파이프라인 구조:** PDF 업로드(upload_pdf.php, 단일/ZIP) → `shuttle_source_doc` 생성 → 문서 화면(doc.php)에서 **파싱/매칭 실행**(run_job.php, `PARSE_MATCH`) → **노선 검수**(route_review.php)에서 후보 승인/거절 → **승격**(promote.php)으로 `shuttle_route_stop` 반영. 실제 승격은 **노선 검수 화면에서만** 수행하며, 운영 대시보드(ops_dashboard.php)는 "오늘 우선순위 점검용" 표시 전용이다.
- **run_job vs promote 경계:** `run_job` = PARSE_MATCH만 수행(shuttle_stop_candidate 갱신, shuttle_route_stop 미변경). `promote` = approved 후보만 shuttle_route_stop으로 반영(created_job_id·is_active=1). latest PARSE_MATCH job만 승격 대상이며, pending > 0이면 승격 차단.
- **알림 운영:** alert_ops.php에서 초안 생성·발행(Publish), event_type(strike/event/update), route_label, published_at. 발행 시 app_alert_events 및 app_alert_deliveries( pending 등) 연동. 유저 구독 토글은 routes.php 및 subscription API. 알림 감사(alert_event_audit.php)·별칭 감사(alias_audit.php)는 읽기 전용, 승인/승격은 노선 검수에서만 수행한다고 명시됨.
- **Observability:** run_job.php에서 get_trace_id·safe_log 사용. route_finder UI 경유 E1 호출은 index.php를 타지 않아 api_enter/db_query_*/api_exit 로그 없음(CTO_REPORT_MVP_PLUS_EXEC_1.md에 "UI 경유 시 관측 로그 없음" 명시). 운영 제어(ops_control.php)의 "실패 상위 20건"은 app_alert_deliveries WHERE status='failed' 조회이며, 실패 0건이면 "데이터가 없습니다"로 표시되는 것이 현재 구현이다.
- **문서 파싱 상태 표시:** doc.php는 shuttle_doc_job_log의 PARSE_MATCH 행에서 last_parse_status, last_parse_error_code( result_note에서 normalize_error_code ), last_parse_duration_ms( result_note에서 duration_ms 파싱 ), last_parse_route_label를 파생한다. run_job 실패 시 insertFailedJobLog로 result_note에 error_code=XXX 기록하도록 되어 있다.

---

## 2) OPEN QUESTIONS (미해결·추후 확인)

| Question | Why it matters | Owner | How to verify |
|----------|----------------|-------|----------------|
| 문서 #8처럼 parse_status=failed인데 last_parse_error_code/last_parse_duration_ms가 "-"로 나오는 원인 | 운영 시 실패 원인 파악 불가. | Backend | 해당 source_doc_id에 대해 shuttle_doc_job_log의 PARSE_MATCH failed 행 존재 여부 및 result_note 형식 확인. insertFailedJobLog 호출 경로가 모든 실패 분기에서 호출되는지 코드 검토. |
| 운영 대시보드에서 "대기 건수"와 "리스크 대기 건수"가 모든 행에서 동일한 이유 | 의도된 집계인지, 리스크 정의(like_prefix/NULL)와 데이터가 맞는지 검증 필요. | Data/ETL, QA | shuttle_stop_candidate pending 건의 match_method 분포 조회. agg.pending_risky_total 정의와 일치하는지 확인. |
| 알림 목록 Pagination "v1.6-06 (확인 필요)" 문구의 스펙/완료 여부 | QA 갭 및 릴리스 체크리스트 정합성. | QA, Release | ADMIN_QA_GAP_LIST 또는 릴리스 노트에서 pagination 요구사항 확인. |
| [Metrics] Review needed 유형 알림의 본문이 유저 이슈 화면에 JSON 그대로 노출되는 것이 의도인지 | 운영/디버그용 메시지와 유저용 메시지 구분 필요 여부. | PM/운영, Backend | 이슈 목록/상세에서 update 타입 본문 표시 정책 문서화 또는 마스킹 적용 여부 결정. |

---

## 3) ROLE REVIEWS (Strengths / Risks / Minimal Fix / Verification)

| 포지션 | Strengths | Risks | Minimal Fix | Verification |
|--------|-----------|-------|-------------|--------------|
| **CTO(의장)** | run_job vs promote 경계·승격은 노선 검수에서만 수행이 코드와 UI 문구로 일치. Phase-2 LOCK(지도/폴리라인) 유지. | 문서 #8처럼 파싱 실패 시 진단 정보 부족 시 운영 판단 지연. | Build에서 doc 실패 로깅·표시 보강 우선순위 확정. | GAP LIST P0 반영 후 검증. |
| **PM/운영** | 유저 플로우(홈→이슈→길찾기→마이노선) 및 Admin 문서 허브→문서→노선 검수→승격 플로우 문서화됨. empty state("이슈가 없습니다", "데이터가 없습니다") 존재. | 실패 상위 20건 "데이터가 없습니다"가 "실패한 배달 없음"인지 문구만으로 불명확. [Metrics] 본문 노출 정책 미정. | 운영 제어 empty state 문구 보강("실패한 배달이 없습니다"). 알림 유형별 본문 노출 정책 1문장 문서화. | 스크린/문서 확인. |
| **Backend(PHP/Admin)** | run_job PARSE_MATCH·insertFailedJobLog·promote 게이트·route_review 연동 구현됨. observability trace_id run_job에 적용. | parse failed인데 job_log에 실패 행 없거나 result_note 형식 불일치 시 doc.php에서 "-"만 표시. 알림 폼 입력값(XSS/길이) 검증·h() 일관 적용 확인 필요. | 실패 시 모든 분기에서 insertFailedJobLog 호출 및 result_note에 error_code 포함 보장. 알림 title/본문 출력 시 h() 적용 여부 점검. | failed 문서에서 error_code 표시. script 입력 시 이스케이프 확인. |
| **Data/ETL** | PARSE_MATCH → candidate → promote → route_stop 파이프라인과 content_hash·delivery 중복 0 요구 일치. ingest 스크립트·CLI 안내(ops_control) 명시. | 대기 건수/리스크 대기 건수 동일한 이유 미문서화. | 집계 정의(리스크=like_prefix/NULL)를 ops_dashboard 또는 ADMIN_PIPELINE 1페이지에 1문장 추가. | SQL로 pending match_method 분포 확인. |
| **QA** | 유저 4화면(홈·이슈·길찾기·마이노선)·Admin(문서 허브·문서 상세·운영 대시보드·알림 운영·검수 대기열·별칭 감사·운영 제어·PDF 업로드) 적용 상태 SOT_GILAIME_UI_SYSTEM에 표로 정리됨. | 관리자 갭 리스트(ADMIN_QA_GAP_LIST) 미생성. Pagination v1.6-06 확인 필요. doc failed 시 재현 절차·테스트 케이스 부족. | ADMIN_QA_GAP_LIST.md 신규 작성(갭·Minimal Fix·검증·Owner). doc failed 시 error_code 표시 검증 케이스 추가. | 갭 리스트 존재·체크리스트 실행. |
| **Release/배포** | RELEASE_GATE·SMOKE_RUNBOOK과 Admin run_job/promote/알림 발행 플로우 일치. | 배포 후 파싱 실패 시 로그 부족하면 원인 추적 어려움. | Build 목록 반영 후 스모크에 "doc failed 시 error_code 노출" 1건 포함. | 스모크 체크리스트 업데이트. |
| **Observability/운영제어** | run_job trace_id·safe_log. ops_control 상태별 집계·실패 상위 20건·CLI 안내 제공. | route_finder UI 경유 E1 호출에는 trace_id/api_enter~api_exit 없음(설계상). | CTO_REPORT에 "UI 경유 시 관측 로그 없음" 이미 반영. 추가로 Admin 알림 발행·promote 시 trace_id 로깅 여부만 점검. | 문서·로그 샘플 확인. |

---

## 4) GAP LIST 요약 (Priority / Gap / Minimal Fix / Verification / Owner)

| Priority | Gap | Minimal Fix | Verification | Owner |
|----------|-----|-------------|--------------|-------|
| **P0** | 문서 파싱 실패 시 last_parse_error_code/last_parse_duration_ms가 "-"로만 표시됨 | 실패 분기 전부에서 insertFailedJobLog 호출 및 result_note에 error_code=XXX·duration_ms(가능 시) 포함. doc.php 표시 로직이 해당 필드 파싱하는지 확인. | parse_status=failed인 문서에서 error_code 값 표시 확인. | Backend |
| **P0** | 알림 운영·알림 목록에서 사용자 입력(title, 본문, route_label 등) XSS/레이아웃 위험 | 출력 경로 전부 htmlspecialchars(h()) 적용. 입력 길이/형식 검증(필요 시 API와 동일 1~60자 등). | title/본문에 `<script>` 입력 후 저장·목록/상세에서 이스케이프 확인. | Backend, QA |
| **P1** | 운영 대시보드 "대기 건수"와 "리스크 대기 건수"가 전부 동일하게 보이는 이유 미문서화 | 집계 정의(리스크 = pending 중 match_method like_prefix 또는 NULL)를 ops_dashboard 또는 ADMIN 파이프라인 1페이지에 1문장 추가. | 문서 존재·SQL로 pending match_method 분포 확인. | Data/ETL |
| **P1** | 운영 제어 "실패 상위 20건" empty state가 "데이터가 없습니다"만 표시 | "실패한 배달이 없습니다" 등 의도 명확 문구로 변경. | 화면 확인. | PM/QA |
| **P1** | 유저 이슈 화면에 [Metrics] Review needed 등 update 타입 알림 본문이 JSON 그대로 노출 | 운영용 메시지와 유저용 구분: 본문 마스킹 또는 "상세는 관리자에서 확인" 등 정책 1문장 문서화 후 필요 시 표시 로직 조정. | 이슈 목록/상세에서 update 타입 본문 확인. | PM/Backend |
| **P2** | 관리자 QA 갭 리스트·검증 방법 통합 문서 없음 | ADMIN_QA_GAP_LIST.md 신규 작성(갭·Minimal Fix·검증·Owner). | 문서 존재·회의 GAP과 링크. | QA |
| **P2** | 알림 목록 Pagination v1.6-06 "확인 필요" 문구 | 요구사항 확인 후 완료 시 문구 제거 또는 버전 고정. | ADMIN_QA_GAP_LIST 또는 스펙 반영. | QA, Release |

---

## 5) Build 목록 (실행용, 순서대로)

| ID | 한 줄 설명 | 파일/위치 | 검증 | 담당 |
|----|------------|-----------|------|------|
| B1 | ADMIN_QA_GAP_LIST.md 신규 작성 — 갭·Minimal Fix·검증·Owner (본 회의록 4) 반영 | docs/OPS/ADMIN_QA_GAP_LIST.md | 문서 존재·표 항목과 회의록 4) 대응 | QA |
| B2 | doc.php 파싱 실패 시 error_code 표시 보강 — job_log failed 행·result_note 파싱 검토 및 insertFailedJobLog 모든 실패 경로 호출 확인 | public/admin/doc.php, public/admin/run_job.php | parse_status=failed 문서에서 last_parse_error_code 값 표시 | Backend |
| B3 | 알림 운영·알림 목록 출력 경로 h() 적용 및 입력 길이 제한(필요 시) | public/admin/alert_ops.php | title/본문에 script 입력 후 이스케이프 확인 | Backend |
| B4 | 운영 제어 "실패 상위 20건" empty state 문구를 "실패한 배달이 없습니다"로 변경 | public/admin/ops_control.php | failed 0건일 때 해당 문구 노출 | PM/QA |
| B5 | 운영 대시보드 또는 Admin 파이프라인 1페이지에 "리스크 대기 = pending 중 match_method like_prefix 또는 NULL" 1문장 추가 | docs/OPS/ADMIN_PIPELINE_ONE_PAGER.md 또는 public/admin/ops_dashboard.php 주석/helper | 문서 또는 화면 안내 존재 | Data/ETL |
| B6 | CTO_ADMIN_BUILD_LIST.md에 본 Build 목록(B1~B6) 행 반영(파일/위치·검증·담당) | docs/OPS/CTO_ADMIN_BUILD_LIST.md | 표에 B1~B6 행 존재 | CTO/Release |

---

## 6) To-Dos 리스트 (개발 착수용)

- [ ] B1: ADMIN_QA_GAP_LIST.md 신규 작성(갭·Minimal Fix·검증·Owner)
- [ ] B2: doc.php 파싱 실패 시 error_code 표시 보강 및 run_job 실패 경로 insertFailedJobLog 확인
- [ ] B3: alert_ops.php 출력 h() 적용 및 입력 검증
- [ ] B4: ops_control.php 실패 상위 20건 empty state 문구 변경
- [ ] B5: 리스크 대기 건수 집계 정의 1문장 문서/화면 반영
- [ ] B6: CTO_ADMIN_BUILD_LIST.md에 B1~B6 반영

---

## 7) Decision Log (LOCK)

| ID | Decision | Rationale | Status |
|----|----------|-----------|--------|
| D1 | 실제 승격(PROMOTE)은 노선 검수 화면(route_review.php)에서만 수행. 운영 대시보드는 표시 전용. | 단일 승격 진입점으로 데이터 일관성·감사 보장. | LOCK |
| D2 | run_job = PARSE_MATCH만(candidate 갱신), promote = approved → route_stop 반영. | 파이프라인 단계 명확 분리. | LOCK |
| D3 | Admin 알림·별칭 감사는 읽기 전용; 승인/승격은 노선 검수에서만. | UI 문구와 코드 일치 유지. | LOCK |
| D4 | Build는 B1→B2→…→B6 순서로 진행. 각 Bn 구현·검증 후 Bn+1. | 의존성·검증 부담 순서 고려. | LOCK |

---

이 Build 목록(B1→B2→…→B6) 순서대로 Cursor Agent 모드 또는 Plan 모드에서 개발을 시작하면 됩니다. 각 Bn 항목을 한 번에 하나씩 구현·검증 후 다음 Bn+1로 진행하세요.
