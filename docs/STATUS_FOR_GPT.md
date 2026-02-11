# GILIME MVP — 상태 요약 (GPT/운영 참고)

## v1.0 RC 종료 조건 (6개)

| # | 조건 | 충족 | 비고 |
|---|------|:----:|------|
| 1 | stop_master 실데이터(11,461건) + 인덱스/EXPLAIN | [x] | v0.6-20 검증 완료 |
| 2 | LOW 승인 게이트(like_prefix 시 체크 강제) | [x] | v0.6-21 |
| 3 | alias 등록 검증(canonical 존재 + alias_text 길이) | [x] | v0.6-21. alias_text<=2 기존 3건은 Known Issues |
| 4 | PARSE_MATCH 품질 지표 저장·노출(shuttle_parse_metrics, doc.php) | [x] | v0.6-22 |
| 5 | route_review/doc 운영 동선·필터(show_advanced 기본, 단순뷰 진입) | [x] | v0.6-24~38 |
| 6 | SoT 유지(stale 차단, promote latest만, 매칭 로직 변경 없음) | [x] | 회귀 없음 확인 |

- alias_text<=2 기존 3건 처리 방향: docs/KNOWN_ISSUES.md 참고.
- v1.0 RC 종료: v1.0-rc.1 (commit 3898ac0) 기준으로 게이트 통과 기록 고정.

## 현재 버전

- **v1.7-17** (현재) Minimal ingest one pdf. `public/admin/upload_pdf.php` 추가(업로드→source_doc 생성→run_job 즉시 실행 버튼), `public/admin/index.php`에 Upload PDF 링크 추가. docs/releases/v1.7/specs/spec_17_ingest_one_pdf.md, docs/releases/v1.7/smoke/smoke_17_ingest_one_pdf.md, docs/releases/v1.7/gate/gate_17_ingest_one_pdf.md.
- **v1.7-16** parse_status/batch policy 통일. `scripts/run_parse_match_batch.php` 선별 규칙 고정(only_failed=1: failed+legacy failed job, 기본: success), dry_run 3줄 요약 + selected_by_status/selected_reason_top 추가. docs/releases/v1.7/specs/spec_16_parse_status_policy.md, docs/releases/v1.7/smoke/smoke_16_parse_status_policy.md, docs/releases/v1.7/gate/gate_16_parse_status_policy.md.
- **v1.7-15** Legacy error normalization. `app/inc/error_normalize.php` 추가, `public/admin/doc.php` Failure TopN/last_parse_error_code를 표준 코드 집계로 통일해 UNKNOWN 축소. docs/releases/v1.7/specs/spec_15_legacy_error_normalize.md, docs/releases/v1.7/smoke/smoke_15_legacy_error_normalize.md, docs/releases/v1.7/gate/gate_15_legacy_error_normalize.md.
- **v1.7-12** Ops control. public/admin/ops_control.php(A: retry/backoff·failed top 20·outbound stub CLI, B: metrics ingest CLI·최근 metrics 10, C: quick links), index 링크. docs/releases/v1.7/smoke/smoke_12_ops_control.md, docs/releases/v1.7/gate/gate_12_ops_control.md, sql/releases/v1.7/validation/validation_12_ops_control.sql.
- **v1.7-11** Real metrics ingest. scripts/run_alert_ingest_real_metrics.php. docs/releases/v1.7/specs/spec_11_real_metrics_ingest.md, docs/releases/v1.7/smoke/smoke_11_real_metrics_ingest.md, docs/releases/v1.7/gate/gate_11_real_metrics_ingest.md, sql/releases/v1.7/validation/validation_11_real_metrics_ingest.sql.
- **v1.7-10** Retry/backoff. app_alert_deliveries.retry_count, run_delivery_outbound_stub pending+failed(backoff). docs/releases/v1.7/specs/spec_10_retry_backoff.md, docs/releases/v1.7/smoke/smoke_10_retry_backoff.md, docs/releases/v1.7/gate/gate_10_retry_backoff.md, sql/releases/v1.7/schema/schema_10_retry_backoff.sql, sql/releases/v1.7/validation/validation_10_retry_backoff.sql.
- **v1.7-09** Ops Summary. public/admin/ops_summary.php(approvals/events/deliveries/outbound 안내), index 링크, docs/releases/v1.7/specs/spec_09_ops_summary.md, docs/releases/v1.7/smoke/smoke_09_ops_summary.md, docs/releases/v1.7/gate/gate_09_ops_summary.md, sql/releases/v1.7/validation/validation_09_ops_summary.sql.
- **v1.7-08** Subscription alert_type FIND_IN_SET 매칭. alert_ops 4곳, app/inc/subscription_match.php, docs/releases/v1.7/specs/spec_08_subscription_matching.md, docs/releases/v1.7/smoke/smoke_08_subscription_matching.md, docs/releases/v1.7/gate/gate_08_subscription_matching.md, sql/releases/v1.7/validation/validation_08_subscription_matching.sql.
- **v1.7-07** Outbound stub. app_alert_deliveries(delivered_at, last_error), scripts/run_delivery_outbound_stub.php, docs/releases/v1.7/specs/spec_07_outbound_stub.md, docs/releases/v1.7/smoke/smoke_07_outbound_stub.md, docs/releases/v1.7/gate/gate_07_outbound_stub.md, sql/releases/v1.7/schema/schema_07_outbound_stub.sql, sql/releases/v1.7/validation/validation_07_outbound_stub.sql.
- **v1.7-06** Approver 분리 + 감사로그. app_users.role(user/approver), app_alert_approvals 테이블. Publish는 role=approver만 허용, 그 외 blocked_not_approver·approval 기록. docs/releases/v1.7/specs/spec_06_approver_role.md, docs/releases/v1.7/smoke/smoke_06_approver_role.md, docs/releases/v1.7/gate/gate_06_approver_role.md, sql/releases/v1.7/schema/schema_06_approver_role_audit.sql, sql/releases/v1.7/validation/validation_06_approver_role.sql.
- **v1.7-05** Deliveries pre-write. Publish 시 pending 적재, user/alerts에서 pending→shown만 UPDATE. flash=published_with_queue&queued_cnt=N. docs/releases/v1.7/specs/spec_05_delivery_queue.md, docs/releases/v1.7/smoke/smoke_05_delivery_queue.md, docs/releases/v1.7/gate/gate_05_delivery_queue.md, sql/releases/v1.7/schema/schema_05_deliveries_index.sql, sql/releases/v1.7/validation/validation_05_delivery_queue.sql.
- **v1.7-04** Approval + Publish guard. alert_ops에 draft/published 상태 뱃지, Publish 시 target_user_cnt=0이면 차단(blocked_no_targets), >0이면 published_at=NOW()·flash=published. docs/releases/v1.7/specs/spec_04_approval_flow.md, docs/releases/v1.7/smoke/smoke_04_publish_guard.md, docs/releases/v1.7/gate/gate_04_publish_guard.md, sql/releases/v1.7/validation/validation_04_publish_guard.sql.
- **v1.7-03** Targeting Preview. alert_ops에서 event_id 지정 시 app_subscriptions 기준 매칭으로 target_user_cnt·상위 20명 리스트 read-only 프리뷰. docs/releases/v1.7/specs/spec_03_targeting_preview.md, docs/releases/v1.7/smoke/smoke_03_targeting_preview.md, docs/releases/v1.7/gate/gate_03_targeting_preview.md, sql/releases/v1.7/validation/validation_03_targeting_preview.sql. 실제 발송·채널·스케줄러 Non-goal.
- **v1.7-02** Admin alert lifecycle Draft/Publish. published_at NULL 허용(스키마 1회 ALTER), alert_ops 초안 생성·Publish 액션·draft_only/published_only 필터, user/alerts는 published_at IS NOT NULL만 노출. sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql, sql/releases/v1.7/validation/validation_02_draft_publish.sql, docs/releases/v1.7/smoke/smoke_02_draft_publish.md, docs/releases/v1.7/gate/gate_02_draft_publish.md. Unpublish·approval workflow·outbound 채널은 Non-goal.
- **v1.6-10** MVP3 운영콘솔 안정화·증거화 완료. v1.6-06 create contract + content_hash + redirect, v1.6-07 audit 필터·요약·드릴다운, v1.6-08 user alerts delivery 가드, v1.6-09 인덱스/EXPLAIN(선택), v1.6-10 gate S5–S7 + Evidence 템플릿. No new tables.
- **v1.6** 핵심: admin alert_ops (list + create + contract), alert_event_audit (filters + summary + drilldown), user alerts delivery guard, docs/v1.6_RELEASE_GATE.md S1–S7 + Evidence SQL 블록.
- **v1.7 (MVP3)** 로드맵 확정: docs/v1.7_ROADMAP.md (7개 버전: 01 문서→02 승인/발행→03 타겟팅→04 dispatch→05 retry→06 funnel→07 release gate). v1.6 게이트 통과 후 착수. 착수 전 published_at NULL 여부 확인 쿼리 포함.
- **v1.5-03** MVP2.5 hardening: observability baseline, alert ref contract, delivery semantics + pagination.
  - **v1.5-01:** Observability baseline (no new tables). docs/OBSERVABILITY_v1_5.md, subscribe_toggle/delivery_written 시 error_log 1줄, app_alert_deliveries·app_subscriptions로 증거. docs/v1.5-01_smoke.md.
  - **v1.5-02:** Alert ref contract. docs/ALERT_REF_CONTRACT_v1_5.md (ref_type route/doc/NULL, Review 링크 규칙). run_alert_ingest_stub ref_type=route·ref_id=1·route_label=R1. sql/v1.5-02_validation.sql (read-only 위반 탐지).
  - **v1.5-03:** Delivery semantics: 렌더된 목록에만 delivery 기록. alerts.php pagination (page, per_page=50), Previous/Next. docs/DELIVERY_SEMANTICS_v1_5.md, docs/v1.5-03_smoke.md.
- **v1.4-10** MVP2 알림 배달 로깅·실제 신호 생성·구독 UX 확장 (v1.4-06~10).
- **v1.4-05** MVP2 사용자 페이지·구독·알림 피드·배치 스텁 (home/routes/alerts, app_* 5테이블, run_alert_ingest_stub).
- **v1.4-00 (planning-only)** MVP2 v1.4 계획 문서 4종. docs/PRD_v1_4_MVP2.md, ARCH_v1_4_SYSTEM.md, ERD_v1_4_DRAFT.md, WIREFRAME_v1_4.md.
- v1.3-06: SECURITY_BASELINE, ERROR_POLICY, ROUTING_STRUCTURE_v1_4 준비 문서.
- **v1.2-06** 운영 3페이지 성능 노트/EXPLAIN 문서화. PERF_NOTES_v1_2.md 추가, 핵심 SELECT 3개 EXPLAIN 증거화. 인덱스 후보는 v1.3에서 적용 예정.
- **v1.2-05** 운영 대시보드 3페이지 확장 완료. Review Queue / Alias Audit / Ops Dashboard (read-only). 새 테이블 없음.
- v1.2-01~04: review_queue.php(필터/ focus 링크), alias_audit.php(리스크+최근 alias), ops_dashboard.php(문서별 검수 필요·최근 job·promote 후보).
- v1.1-07: 운영 속도팩 안정화, 단축키 치트시트, 문서 갱신.

## 운영자 단축키 치트시트 (route_review)

- **a** 선택 행 Approve (LOW는 체크박스 필수). **r** 선택 행 Reject.
- **n** 다음 노선 검수(즉시 이동). **t** 검수 후 다음 노선 자동 점프 토글(jump_next=1/0).
- **j** 선택 행 아래로 이동. **k** 선택 행 위로 이동.
- 진입 시 첫 pending 행 자동 선택. focus_cand_id 있으면 해당 행 선택.
- Approve/Reject 후 jump_next=0이면 같은 노선 유지 + 다음 pending 행 자동 선택(redirect 시 focus_cand_id).
- INPUT/TEXTAREA/SELECT 포커스 중에는 단축키 무시(연타 방지 800ms 포함).
- Known Issues: 필터로 후보 숨김 시 안내 문구, show_advanced 기본 숨김, alias_text<=2 legacy 3건 유지.

## v1.2 신규 Admin 페이지 (read-only)

- **Review Queue** (review_queue.php): 문서/노선별 pending Summary + Top N 후보. doc_id/route_label 필터, focus_cand_id 링크. index/doc 링크.
- **Alias Audit** (alias_audit.php): Alias Issues(alias_text<=2, canonical 미존재) + Recent Alias Writes 50건. index/doc 메타 링크.
- **Ops Dashboard** (ops_dashboard.php): Docs needing review, Recent PARSE_MATCH 20건, Promote candidates(pending=0). index 링크.
- 승인/승격은 route_review에서만. 새 페이지는 조회·링크 전용.

## SoT (변경 금지)

1. latest PARSE_MATCH 스냅샷 기준 후보 표시 (created_job_id=latest).
2. stale 후보 승인/거절 차단 (UI+서버).
3. promote는 latest PARSE_MATCH만 허용.
4. route_stop 스냅샷 누적 (기존 active is_active=0, 신규만 is_active=1).
5. alias 등록 시 latest 후보 1건 live rematch (가능할 때만).
6. 추천 canonical 계산은 only_unmatched=1에서만 (캐시 포함) — v0.6-17 고정.
7. 매칭 신뢰도(HIGH/MED/LOW/NONE) 표시는 표시/집계 전용, 매칭 로직 변경 금지 — v0.6-18 고정.
8. PARSE_MATCH 품질 지표는 DB에 저장(shuttle_parse_metrics). job_id + route_label 단위 집계 — v0.6-22 추가.

## v0.6-31 요약

- **doc.php만 변경.** Next Actions에 GET only_risky=1 토글. only_risky=1이면 Top20이 LOW/NONE만. "Next Actions Summary (by route)" 추가(1쿼리): route_label별 pending_total, pending_low_cnt, pending_none_cnt, pending_risky_cnt, 정렬 risky·total DESC. Top20 제목/안내에 (all pending)/(LOW/NONE only) 반영. 요약 1쿼리 + Top20 1쿼리만.

## v0.6-30 요약

- **doc.php만 변경.** Review Progress 아래에 "Next Actions (Top 20 pending candidates)" 섹션 추가. pending 후보 1쿼리, 정렬: like_prefix → match_method NULL → match_score ASC, LIMIT 20. route_label 링크, 원문/정규화/매칭결과/근거/신뢰도, Action="route_review에서 처리". Approve/Reject 없음. 신뢰도 함수(route_review와 동일) doc.php에 추가. 데이터 없으면 "no pending candidates".

## v0.6-29 요약

- **doc.php만 변경.** Metrics(latest) 표 바로 아래에 "Review Progress (latest job)" 표 추가. latest_parse_job_id 기준 shuttle_stop_candidate route_label별 집계 1회(cand_total, pending/approved/rejected_cnt, done_cnt, done_rate%). 정렬: pending_cnt DESC, cand_total DESC. route_label 링크·안내 1줄. 표시/집계만.

## v0.6-28 요약

- **doc.php만 변경.** Metrics(latest)·History 표에서 route_label을 route_review 링크로 제공. 안내 1줄 추가. latest 표 정렬: low_confidence_cnt DESC, none_matched_cnt DESC, cand_total DESC. History 표 정렬: parse_job_id DESC, low_confidence_cnt DESC, none_matched_cnt DESC, cand_total DESC. 표시/링크/정렬만.

## v0.6-27 요약

- **doc.php만 변경.** Metrics 표 상단에 운영 경고 2종(표시 전용). prev job 없으면 미표시. 경고 A: low_delta 합계 > 0 시 "주의: LOW(like_prefix) 후보가 직전 job 대비 +N 증가했습니다." 경고 B: none_delta 합계 > 0 시 "주의: NONE(미매칭) 후보가 직전 job 대비 +N 증가했습니다." v0.6-25 delta 활용.

## v0.6-26 요약

- **doc.php만 변경.** "PARSE_MATCH Metrics (latest job)" 아래에 "PARSE_MATCH Metrics History (recent 5 jobs)" 표 추가. source_doc_id 기준 PARSE_MATCH success 최근 5 job_id → shuttle_parse_metrics 조회(parse_job_id DESC, route_label ASC). 데이터 없으면 "no history". 표시 전용.

## v0.6-25 요약

- **doc.php만 변경.** PARSE_MATCH Metrics에 직전 job(prev_parse_job_id) 대비 delta 표시(auto_delta, low_delta, none_delta). +n/-n/0 또는 "—"(prev 없을 때). 표시 전용.
- prev_parse_job_id: shuttle_doc_job_log에서 PARSE_MATCH success, id &lt; latest 중 최대 id 1건. route_review·매칭·게이트 변경 없음.

## v0.6-24 요약

- **기능 변경 없음.** 관리자 UI 가독성/정보구조만 정리(운영 UX 리팩터).
- **doc.php:** 운영 플로우 순 재배치(메타 → 실행 버튼 상단 → latest job → PARSE_MATCH Metrics). Metrics 표 route_review와 동일 기준(latest snapshot) 설명 추가.
- **route_review.php:** 3단(상단 요약 / 중단 필터·검색 / 하단 Candidates·Actions). 필터 2줄·현재 상태 1줄, 테이블 헤더 운영자 관점 라벨, Actions 레이아웃 정리.
- 새 테이블/페이지·SQL·매칭 로직·게이트 규칙 변경 없음.

## v0.6-22 요약

- **테이블 추가:** shuttle_parse_metrics (PARSE_MATCH job_id + route_label 단위로 매칭 품질 저장). UNIQUE(parse_job_id, route_label).
- **자동 저장:** run_job.php에서 PARSE_MATCH 성공 후 DB 집계 쿼리로 metrics 계산 → UPSERT. 실패해도 PARSE_MATCH는 성공 유지(비치명적).
- **화면 추가:** doc.php에 "PARSE_MATCH Metrics (latest job)" 테이블. route별 품질 지표(cand_total, auto_matched, LOW, NONE, alias, HIGH/MED/LOW/NONE) 표시.
- **검증:** sql/v0.6-22_validation.sql (8개 쿼리: 테이블 존재, metrics row count, candidate 집계 일치, UPSERT idempotent).
- **매칭 로직/SoT 불변:** 품질 지표 저장만 추가. route_review 변경 없음.

## v0.6-21 요약

- **LOW 승인 게이트:** match_method='like_prefix' pending 후보는 체크박스 "LOW(like_prefix) 확인함" 체크 후에만 Approve. 미체크 시 서버 차단.
- **alias 검증 강화:** (a) canonical이 stop_master에 존재해야만 저장. (b) alias_text 길이 <=2 차단. 검증 실패 시 alias 저장 금지.
- **검증:** sql/v0.6-21_validation.sql (8개 쿼리: LOW pending/approved, alias canonical 존재, alias_text 길이, 회귀)
- **매칭 로직/SoT 불변:** 승인/등록 게이트만 강화.

## v0.6-20 요약

- **Import 스크립트:** scripts/import_seoul_bus_stop_master_full.php (euc-kr CSV → UTF-8, UPSERT, idempotent)
- **입력:** data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv (Git 커밋 금지)
- **검증:** sql/v0.6-20_validation.sql (9개 쿼리: 건수, 인덱스, EXPLAIN, match_method 분포)
- **인덱스:** stop_name 인덱스 확인만 (v0.6-10 기존). 추가 인덱스는 v0.6-21로 미룸.
- **실행:** Cursor PC 앱(터미널)에서만. 매칭 로직/SoT 불변.

## v0.6-19 요약

- **only_low 필터:** GET only_low=1 시 match_method='like_prefix'만 표시. only_unmatched와 동시 사용 가능.
- **토글 링크:** "LOW만 보기" / "LOW 해제" 추가. only_unmatched와 파라미터 조합 유지.
- **Promote 경고:** low_confidence_cnt/auto_matched_cnt >= 30% 시 경고 문구. "주의: like_prefix(LOW) 비중이 높습니다. Promote 전 후보를 재검토하세요."
- **SQL 없음:** 화면 필터/표시만. 매칭 로직/SoT 불변.

## v0.6-18 요약

- **매칭 신뢰도 표시:** route_review Candidates에 컬럼 1개 추가. exact/alias_live_rematch/alias_exact → HIGH, normalized/alias_normalized → MED, like_prefix → LOW, 그 외/NULL → NONE. 텍스트만 표시.
- **summary 집계:** latest 스냅샷 기준 auto_matched_cnt, low_confidence_cnt(like_prefix), none_matched_cnt, alias_used_cnt. only_unmatched=1일 때도 동일 기준.
- **검증:** sql/v0.6-18_validation.sql 에 검증 쿼리 7개 (주석 블록, Workbench 실행용). 매칭 로직/SoT 변경 없음.

## SQL 검증 (GPT 대화에서)

- **GPT는 SQL을 실행할 수 없음.** 사용자가 Workbench(또는 Cursor 터미널)에서 실행 후, **실행 결과를 GPT 대화창에 붙여넣으면** GPT가 결과를 해석하고 다음 지시(정상/추가 점검 등)를 낸다.
- 절차: (1) GPT에게 "검증 쿼리 알려줘" 요청 → (2) GPT가 sql/v0.6-18_validation.sql 기준으로 쿼리+치환값 제시 → (3) 사용자 실행 → (4) 결과 붙여넣기 → (5) GPT가 다음 지시.

## 제약

- 새 테이블/페이지 추가 금지 (지시 없는 한).
- 풀스캔·LIKE %...% 금지.
- SoT·stale 차단·promote 규칙 훼손 금지.
