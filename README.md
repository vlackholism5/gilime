# Gilaime MVP - public/ 구조

## v0.6-11 자동매칭 규칙

PARSE_MATCH(job) 실행 시 후보(candidates) 생성하면서 **서울시 정류장마스터(seoul_bus_stop_master)** 기반으로  
`matched_stop_id`, `matched_stop_name`, `match_score`, `match_method`를 **추천**만 채움. status는 계속 `pending`.

- **우선순위:** 정확일치(exact, 1.0) → 공백 정규화(normalized, 0.7) → prefix LIKE(like_prefix, 0.7)
- **인덱스:** `seoul_bus_stop_master.stop_name` 사용, 풀스캔 없음
- **UI:** route_review Candidates에서 자동매칭된 값이 입력란에 미리 채워진 상태로 표시. 실패 시 수동 입력 후 Approve

## v0.6-12 정규화 + 동의어(alias) 사전

- **정규화:** raw_stop_name → normalized_stop_name (trim + 공백 축약). route_review Candidates 테이블에 `normalized_name` 표시.
- **매칭 순서:** exact(1.0) → normalized(0.7) → **alias 적용** → canonical으로 exact/normalized 재시도(0.85, alias_exact/alias_normalized) → like_prefix(0.7).
- **동의어 사전:** `shuttle_stop_alias` (alias_text → canonical_text). route_review에서 pending 행에 **alias 등록** 버튼: raw(정규화)를 alias_text, 입력한 정식 명칭을 canonical_text로 저장. 다음 PARSE_MATCH부터 해당 alias로 자동매칭 시도.
- **테이블:** shuttle_stop_alias(필수), shuttle_stop_normalize_rule(선택). 새 페이지 없음, route_review 내부만 확장.

## v0.6-13 alias 등록 즉시 재매칭

- route_review에서 **alias 등록** 시, 해당 candidate 1건을 **재파싱 없이** 즉시 seoul_bus_stop_master로 재조회해 `matched_stop_id`/`matched_stop_name`/`match_score`(0.95)/`match_method`(=`alias_live_rematch`)를 UPDATE.
- canonical이 stop_master에 없으면 alias만 저장하고 flash: "alias saved but canonical not found in master". latest 스냅샷이 아닌(stale) 후보는 alias만 저장, rematch 생략.
- DDL 변경 없음.

## v0.6-14 매칭 품질·안전장치

- **Candidates 테이블:** match_method, match_score 컬럼 표시 추가(운영자가 매칭 원인/실패 즉시 확인).
- **like_prefix 보수화:** normalized 길이 2글자 이하일 때 like_prefix 미적용(짧은 단어 과매칭 방지).
- **alias 등록 가이드:** "stop_master에 존재하는 정식 정류장명으로 입력하세요." 문구 추가. DDL/신규 테이블 없음.

## v0.6-15 Stop Master Quick Search

- **route_review** 상단 meta 아래에 "Stop Master Quick Search" 카드 추가: stop_name 입력 → exact → normalized → like_prefix(2글자 초과 시만) 순으로 최대 10건 표시. alias canonical 입력 전 존재 여부 확인용.
- **Candidates** raw_stop_name을 readonly input으로 표시해 선택·복사 편의 제공. 새 페이지/테이블 없음.

## v0.6-16 매칭 실패 1번에 정리

- **매칭 실패만 보기:** GET only_unmatched=1 시 matched_stop_id NULL/'' 인 후보만 표시(latest 스냅샷 기준). 토글 링크로 전체/실패만 전환.
- **추천 canonical:** Candidates에 "추천 canonical" 컬럼 추가. raw_stop_name으로 Quick Search와 동일 규칙(exact→normalized→like_prefix) 1순위 stop_name 표시. alias 등록 시 참고용.
- **alias 폼:** canonical_text input의 placeholder에 추천값 반영(있을 때). DDL 없음(필요 시 shuttle_stop_alias 인덱스만 추가 허용).

## v0.6-17 추천 canonical 계산 최적화

- **only_unmatched에서만 계산:** 전체 보기(only_unmatched=0)에서는 추천 canonical 컬럼·placeholder 모두 "—"/"정식 명칭" 고정. 매칭 실패만 보기일 때만 stop_master 조회.
- **요청 단위 캐시:** 동일 raw_stop_name(정규화 키)당 DB 조회 1회. hits/misses는 meta에 "추천 canonical 계산: ON/OFF, cache hits=X, misses=Y"로 표시(전체 보기 시 OFF, 0/0). SoT·approve/reject/promote/alias_live_rematch 로직 불변.

## v0.6-18 매칭 신뢰도 표시 + summary 집계

- **매칭 신뢰도 컬럼:** route_review Candidates에 표시 전용. exact/alias_live_rematch/alias_exact → HIGH, normalized/alias_normalized → MED, like_prefix → LOW, 그 외/NULL → NONE (텍스트만, 신규 CSS 없음).
- **summary 4개 카운트:** latest 스냅샷 기준 auto_matched_cnt, low_confidence_cnt(like_prefix), none_matched_cnt, alias_used_cnt. promote 전 모호매칭 비중 파악용. only_unmatched=1일 때도 동일 latest 기준으로 표시.
- **검증:** sql/v0.6-18_validation.sql 에 검증 쿼리 7개(주석 블록). 매칭 로직/SoT 변경 없음.

## v0.6-19 LOW(like_prefix) 필터 + Promote 경고

- **only_low 필터:** GET only_low=1 시 latest 스냅샷 후보 중 `match_method='like_prefix'` 인 후보만 표시. only_unmatched=1과 동시 사용 가능(like_prefix 이면서 unmatched만).
- **토글 링크:** Candidates 상단에 "LOW만 보기" / "LOW 해제" 링크 추가. only_unmatched와 조합 유지.
- **Promote 경고:** Promote 버튼 위에 경고 문구 표시. 조건: low_confidence_cnt > 0 AND (low_confidence_cnt / auto_matched_cnt) >= 0.30. "주의: like_prefix(LOW) 비중이 높습니다. Promote 전 후보를 재검토하세요."
- **SQL 없음:** 화면 필터/표시만 추가. 매칭 로직/SoT 불변.

## v0.6-20 seoul_bus_stop_master 실데이터 import

- **Import 스크립트:** `scripts/import_seoul_bus_stop_master_full.php` (euc-kr CSV → UTF-8 변환, UPSERT, idempotent)
- **입력 파일:** `data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv` (Git 커밋 금지, 로컬 전용)
- **실행:** `php scripts/import_seoul_bus_stop_master_full.php` (Cursor 터미널)
- **검증:** `sql/v0.6-20_validation.sql` (9개 쿼리: 건수, 인덱스, EXPLAIN, match_method 분포)
- **인덱스:** `sql/v0.6-20_stop_master_indexes.sql` (stop_name 인덱스 확인, 추가 인덱스는 v0.6-21로 미룸)

## v0.6-21 운영 안전장치 강화 (LOW 승인 + alias 검증)

- **LOW 승인 게이트:** match_method='like_prefix'인 pending 후보는 **체크박스 "LOW(like_prefix) 확인함"** 체크 후에만 Approve 가능. 미체크 시 서버에서 차단, 에러: "LOW... 확인 체크 후 승인할 수 있습니다." (DB UPDATE 없음)
- **alias 등록 검증 강화:** (a) canonical_text가 stop_master에 **존재**해야만 저장. 없으면 차단, 에러: "alias blocked: canonical not found". (b) alias_text(정규화 후) **길이 <=2** 이면 저장 차단, 에러: "alias blocked: alias_text too short". 검증 통과 시에만 alias 저장 + live rematch.
- **검증:** `sql/v0.6-21_validation.sql` (8개 쿼리: LOW pending/approved, alias canonical 존재, alias_text 길이 분포, 회귀 확인)
- **매칭 로직/SoT 불변:** 승인/등록 단계 게이트만 강화.

## v0.6-22 PARSE_MATCH 품질 지표 저장

- **테이블 1개 추가:** `shuttle_parse_metrics` (job_id + route_label 단위로 매칭 품질 수치 저장). 인덱스: UNIQUE(parse_job_id, route_label), INDEX(source_doc_id, route_label).
- **저장 컬럼:** cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt. 분류는 v0.6-18과 동일(HIGH/MED/LOW/NONE).
- **run_job.php:** PARSE_MATCH 성공 후 DB 집계 쿼리로 metrics 계산 → UPSERT 저장 (PHP 루프 금지). 저장 실패 시 PARSE_MATCH는 성공 유지(비치명적).
- **doc.php:** "PARSE_MATCH Metrics (latest job)" 테이블 추가. latest_parse_job_id 기준 route별 품질 지표 표시. route_review는 기존 그대로 유지.
- **검증:** `sql/v0.6-22_validation.sql` (8개 쿼리: 테이블 존재, metrics row count, candidate 집계와 일치, UPSERT idempotent 확인).
- **SQL 실행:** Cursor PC 앱(Workbench)에서만. 매칭 로직/SoT 불변.

## v0.6-23 RC 종료

- v0.6-23 RC 종료. v0.6-24부터 운영 UX 개선 진행.

## v1.1 운영 속도팩 완료

- v1.1-01~09: 단축키(a/r/n/t/j/k), 오늘 작업 시작, 마지막 검수 시각, focus_cand_id 흐름, 연타 방지 등 운영 속도/안정화 반영.

## v1.2 운영 대시보드 3페이지

- v1.2-06: 운영 3페이지 성능 노트/EXPLAIN 문서화 완료 (docs/PERF_NOTES_v1_2.md). 인덱스 후보는 v1.3에서 적용 예정.
- Review Queue (review_queue.php), Alias Audit (alias_audit.php), Ops Dashboard (ops_dashboard.php). read-only, 승인/승격은 route_review만.

## v1.3 성능/운영 안정

- **검증 통합팩:** sql/v1.3-06_validation_pack.sql (SHOW INDEX 4건 + EXPLAIN 3건). 운영 3페이지 핵심 쿼리 한 파일로 검증.
- **인덱스:** v1.3-01 candidate 2개·alias 1개, v1.3-05 job_log 1개(idx_joblog_doc_type_status_id). ops_dashboard NOT EXISTS·derived agg 정렬 기본값(최신), sort=risky 옵션. review_queue sort=simple 옵션.
- **문서:** docs/PERF_NOTES_v1_2.md에 v1.3-01~09 결론 표·v1.4 후보(job_log 인덱스 정리, 집계 테이블 도입 여부, 대량 데이터 LIMIT/필터 가이드) 확정.
- v1.4에서 새 테이블(ops_events, alias_audit_log, job_snapshot_summary 등) 도입 후보 검토.
- **MVP2 v1.4 one-shot expansion** 준비: docs/SECURITY_BASELINE.md, docs/ERROR_POLICY.md, docs/ROUTING_STRUCTURE_v1_4.md 로 보안·에러·라우팅 규칙을 문서화하여 v1.4 사용자 페이지·구독·알림 확장 전 기준을 고정.
- **v1.4 계획 문서** (docs/v1.4-00): PRD_v1_4_MVP2.md, ARCH_v1_4_SYSTEM.md, ERD_v1_4_DRAFT.md, WIREFRAME_v1_4.md 생성 완료.

## v1.4 MVP2 사용자 페이지·구독·알림 (v1.4-10)

- **사용자 페이지:** public/user/ — home.php, routes.php, alerts.php. 공통 네비: Home / Routes / Alerts. home에 구독 노선 목록(최대 10) + alerts.php?route_label= 링크.
- **DB 테이블:** app_* 5개. DDL: v1.4-02_schema.sql, v1.4-06_delivery_unique.sql (배달 UNIQUE), v1.4-07_route_label.sql (app_alert_events.route_label). PC에서만 실행.
- **구독·알림:** routes.php Subscribe/Unsubscribe, "(Subscribed)" 표시. alerts.php type·route_label·subscribed only 필터, Review 링크(route_review/doc). 배달 로깅: app_alert_deliveries (노출 시 channel=web, status=shown).
- **배치:** run_alert_ingest_stub.php (더미). run_alert_generate_from_metrics.php — shuttle_parse_metrics 직전 job 대비 NONE/LOW 증가 시 이벤트 삽입(content_hash idempotent). v1.4-07_route_label.sql 적용 후 실행.
- **스모크:** docs/v1.4-10_smoke.md 참고.

## v1.5 MVP2.5 hardening (v1.5-01 ~ v1.5-03)

- **Observability:** docs/OBSERVABILITY_v1_5.md. 증거는 app_alert_deliveries + (선택) error_log. subscribe_toggle/delivery_written 로그. docs/v1.5-01_smoke.md.
- **Alert ref contract:** docs/ALERT_REF_CONTRACT_v1_5.md. ref_type=route/doc/NULL 규칙, 스크립트·검증 sql/v1.5-02_validation.sql.
- **Delivery semantics:** 렌더된 이벤트에만 delivery 기록. alerts.php pagination (50 per page), Previous/Next. docs/DELIVERY_SEMANTICS_v1_5.md, docs/v1.5-03_smoke.md.
- **v1.6-10** MVP3 운영콘솔 안정화: alert_ops create contract + content_hash + redirect, alert_event_audit 필터·요약·드릴다운, user alerts delivery 가드. No new tables. docs/v1.6_RELEASE_GATE.md S1–S7 + Evidence SQL 블록. **v1.7** 부터: 자동 발송·타겟팅·승인 플로우 확장 예정.

## v0.6-24 관리자 UI 정보구조 정리

- **기능 변경 없음.** 관리자 화면 가독성/정보구조만 정리(운영용 UX 리팩터).
- **doc.php:** 운영 플로우 순 재배치(문서 메타 → 실행 버튼 상단 → latest job 요약 → PARSE_MATCH Metrics). Metrics 표 컬럼 순서 고정, route_review와 동일 기준(latest snapshot) 설명 1줄 추가.
- **route_review.php:** 3단 구조(상단 상태 요약 / 중단 필터·검색 / 하단 Candidates·Actions). 필터 2줄·현재 상태 1줄, 테이블 헤더 운영자 관점 라벨(원문 정류장명, 정규화, 매칭 결과, 근거, 신뢰도). Actions 기능 유지, 레이아웃만 정리.

## v0.6-25 doc Metrics 직전 job 대비 delta

- **doc.php만 변경.** PARSE_MATCH Metrics에 직전 PARSE_MATCH job 대비 변화량(delta) 표시.
- prev_parse_job_id: shuttle_doc_job_log에서 source_doc_id·PARSE_MATCH·success·id&lt;latest 기준 직전 1건. prev 없으면 delta 열은 "—".
- delta 열: auto_delta, low_delta, none_delta (latest − prev). 표시 형식: +n / -n / 0. 표시 전용, 데이터/로직 변경 없음.
- route_review.php·매칭 로직·게이트·새 테이블 변경 없음.

## v0.6-26 doc Metrics History (최근 5회)

- **doc.php만 변경.** "PARSE_MATCH Metrics (latest job)" 아래에 "PARSE_MATCH Metrics History (recent 5 jobs)" 표 추가.
- source_doc_id 기준 PARSE_MATCH success job 최근 5개 job_id 조회 후, 해당 job_id들의 shuttle_parse_metrics를 parse_job_id DESC, route_label ASC로 표시. 데이터 없으면 "no history".
- 표시 전용. route_review·매칭·게이트·새 테이블 변경 없음.

## v0.6-27 doc 운영 경고(LOW/NONE 증가)

- **doc.php만 변경.** PARSE_MATCH Metrics 표 상단에 표시 전용 경고 2종 추가. prev job 없으면 미표시.
- 경고 A: low_delta 합계 > 0 일 때 "주의: LOW(like_prefix) 후보가 직전 job 대비 +N 증가했습니다."
- 경고 B: none_delta 합계 > 0 일 때 "주의: NONE(미매칭) 후보가 직전 job 대비 +N 증가했습니다." v0.6-25 delta 값 활용.

## v0.6-28 doc 다음 행동 유도(링크+정렬)

- **doc.php만 변경.** Metrics(latest)·History 표에서 route_label을 route_review 링크로 제공(source_doc_id, route_label 파라미터). 표 상단 안내 1줄: "route_label을 클릭하면 해당 노선의 검수 화면(route_review)으로 이동합니다."
- latest metrics 표 정렬: low_confidence_cnt DESC, none_matched_cnt DESC, cand_total DESC(리스크 우선). History 표 정렬: parse_job_id DESC, low_confidence_cnt DESC, none_matched_cnt DESC, cand_total DESC(최근/리스크 우선). 표시/링크/정렬만, 로직·게이트 변경 없음.

## v0.6-29 doc 검수 진행률(Review Progress)

- **doc.php만 변경.** Metrics(latest) 표 바로 아래에 "Review Progress (latest job)" 표 추가. 기준: latest_parse_job_id, shuttle_stop_candidate(created_job_id=latest) route_label별 집계 1회(cand_total, pending_cnt, approved_cnt, rejected_cnt, done_cnt, done_rate%). 정렬: pending_cnt DESC, cand_total DESC. route_label은 route_review 링크 동일. 안내: "pending이 0이 되면 Promote 가능 여부를 route_review에서 확인하세요." 표시/집계만, 승인 로직 변경 없음.

## v0.6-30 doc Next Actions (Top 20 pending)

- **doc.php만 변경.** Review Progress 아래·Metrics History 위에 "Next Actions (Top 20 pending candidates)" 섹션 추가. latest_parse_job_id 기준 pending 후보 1쿼리, 정렬: like_prefix(LOW) 우선 → match_method NULL(NONE) 우선 → match_score NULL/낮은 순, LIMIT 20. 컬럼: route_label(링크), 원문 정류장명, 정규화, 매칭 결과, 근거, 신뢰도, Action(route_review에서 처리). Approve/Reject 없음. 데이터 없으면 "no pending candidates".

## v0.6-31 doc Next Actions 요약 + risky 토글

- **doc.php만 변경.** Next Actions 상단에 GET only_risky=1 토글 추가. only_risky=1이면 Top20을 LOW/NONE만 조회. "Next Actions Summary (by route)" 섹션 추가(Summary 1쿼리): route_label별 pending_total, pending_low_cnt, pending_none_cnt, pending_risky_cnt, 정렬 risky DESC·pending_total DESC. Top20 제목/안내에 (all pending)/(LOW/NONE only) 표시. 요약 1쿼리 + Top20 1쿼리만 사용.

## v0.6-39 v1.0 RC Gate 문서화

- **문서만 변경.** v1.0 RC 종료 조건 6개를 docs/STATUS_FOR_GPT.md 상단에 체크박스로 정리. docs/RELEASE_GATE_v1_0.md(게이트 체크리스트 + 검증 1회 원칙), docs/KNOWN_ISSUES.md(alias_text<=2 기존 3건·운영 확인 항목) 신규 추가. 코드/UI/SQL 변경 없음.

## 폴더 구조(확정)
- /public/admin : 웹에서 접근하는 관리자 페이지(실제 URL은 /admin 로 유지)
- /app/inc      : PHP 공통 코드(config/db/auth)
- /storage      : 업로드/워처/로그(웹 직접 접근 차단)
- /tools        : 배치 스크립트(웹 직접 접근 차단)
- /sql          : 스키마/시드(웹 직접 접근 차단)

## 로컬 설정 (DB 비밀값)

- DB 비밀값은 코드에 넣지 않음. `app/inc/config.local.php.example` 를 복사해 `config.local.php` 로 만들고, `DB_HOST`/`DB_USER`/`DB_PASS` 등 실제 값 입력. (`config.local.php` 는 .gitignore 대상.)

## XAMPP(htdocs)에서 실행
1) `C:\xampp\htdocs\gilime_mvp_01\` 에 이 폴더를 그대로 복사
2) 위 로컬 설정으로 `config.local.php` 생성
3) Apache 재시작
4) 접속
   - http://localhost/gilime_mvp_01/admin/login.php

## 왜 /public/admin 인데 URL은 /admin 인가?
- XAMPP 기본은 프로젝트 루트가 webroot라서,
  `.htaccess`로 `/admin/*` 요청을 `/public/admin/*`로 rewrite 합니다.
- 운영 서버에서는 DocumentRoot를 `/public`으로 두는 방식이 더 흔합니다(추후 전환).
