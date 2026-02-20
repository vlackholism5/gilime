# v1.7 (MVP3) 로드맵 — 확정안

현재 DB/코드/운영 원칙(SoT, read-only 페이지, delivery semantics) 기반 설계.  
Cursor 지시 프롬프트 + PC 실행 체크 + Gate까지 한 번에 확정.

---

## v1.7 목표 및 제약

**v1.7_goal**
- 알림 운영을 **"작성 → 검토/승인 → 발행(publish) → 타겟팅 → 발송(채널별) → 감사/추적"**으로 완결.
- 현 v1.6의 admin alert_ops(작성) + audit(조회) 위에 **승인/발행/발송**을 얹는다.

**constraints**
- 기존 app_* 테이블/컬럼명 유지 (v1.4 DDL 기준).
- 스키마 변경은 최소 단위로, PC(Workbench)에서만 실행.
- 중복 방지: content_hash/UNIQUE, deliveries UNIQUE 유지.
- 불확실한 보안요소(OAuth/2FA 등)는 "확인 필요"로만 기록.

---

## 로드맵 개요 (7개 버전)

| 버전 | 핵심 산출물 | DB 변경 | 주요 게이트 |
|------|-------------|---------|-------------|
| v1.7-01 | "승인/발행 상태 모델" 문서 + 운영 규칙 고정 | 없음(문서) | Gate-01: 승인 플로우 정의 고정 |
| v1.7-02 | Admin에서 "Draft/Publish" 구현(발행 시각/상태) | app_alert_events 컬럼 1~2개 추가(확인 필요) | Gate-02: draft→publish 전환 증거 |
| v1.7-03 | 타겟팅 v1: route_label 구독자 대상 "inapp" 대상 산출 | 없음(쿼리/로직) | Gate-03: 타겟 유저 목록/카운트 |
| v1.7-04 | 발송 v1: deliveries를 "pending→sent"로 처리(채널=web/inapp) | 없음 또는 인덱스(선택) | Gate-04: sent 전환 + 중복 0 |
| v1.7-05 | Retry/Idempotency: 실패 재시도/재집계 규칙 문서+최소 구현 | 없음 | Gate-05: 재실행해도 중복/폭증 없음 |
| v1.7-06 | Audit 강화: 이벤트별 퍼널(대상/전달/노출) 요약 1쿼리 | 없음 | Gate-06: ops에서 수치 일관 |
| v1.7-07 | Release Gate + E2E 시나리오(작성→승인→발행→타겟→전달→감사) | 없음(문서) | Gate-07: E2E PASS |

**확인 필요:** v1.7-02에서 "상태"를 새 컬럼으로 둘지(예: status) / published_at 의미를 재정의할지(이미 NOT NULL 사용 중) 결정 필요. 아래는 "최소 변경" 기준.

---

## v1.7-01 — 승인/발행 모델 문서 고정 (코드/DB 변경 없음)

### Cursor 지시 프롬프트 (복붙용)

```
[ROLE] You are the maintainer of GILIME (PHP+MySQL). Deterministic engineering mode.

[GOAL] Define MVP3 (v1.7) approval/publish/send flow as a frozen contract.

[CONSTRAINT]
- Do not change code or SQL.
- Use existing tables: app_users, app_user_sessions, app_subscriptions, app_alert_events, app_alert_deliveries.
- Keep delivery semantics: rendered-only + UNIQUE(uq_delivery_user_event_channel).

[OUTPUT FILES]
1) docs/references/ALERT_LIFECYCLE_v1_7.md
   - States: draft -> approved -> published -> delivered(shown/sent)
   - Who can do what: admin vs user
   - Idempotency rules: content_hash, deliveries unique
   - "확인 필요" section: whether to add status columns or reuse published_at
2) docs/releases/v1.7/gate/v1.7-01_GATE.md
   - Gate checklist + evidence placeholders (no SQL yet)

PC 실행: 없음
Gate-01: 문서에 "상태/권한/중복 규칙"이 충돌 없이 정의되어 있는지
```

### PC 실행
없음.

### Gate-01
문서에 상태/권한/중복 규칙이 충돌 없이 정의되어 있는지.

---

## v1.7-02 — Admin 승인/발행 (최소 DB 변경 가능)

### Cursor 지시 프롬프트 (복붙용)

```
[ROLE] Maintainer.

[GOAL] Add "approval + publish" control in admin alert_ops.

[ASSUMPTION]
- If possible, avoid schema change by using published_at as "published time" and treat NULL as draft.
- If published_at cannot be NULL in current schema, mark "확인 필요" and propose minimal ALTER.

[TASKS]
1) Update public/admin/alert_ops.php
   - List supports filter: published_only=1/0, draft_only=1/0
   - Create form: allow draft mode (published_at optional) OR enforce published_at (if schema requires)
   - Add actions per row: Approve/Publish (POST)
   - Use INSERT/UPDATE with guard + flash + highlight

2) Add SQL file if schema change is needed:
   - sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql with ALTER TABLE app_alert_events (published_at nullable OR add status)
   - Include rollback comments.

3) Add docs:
   - docs/releases/v1.7/smoke/smoke_02_draft_publish.md (admin flow)
   - docs/releases/v1.7/gate/gate_02_draft_publish.md (evidence placeholders)

PC 실행(확인 필요): published_at이 NOT NULL이면 draft 지원이 막힘 → 그 경우에만 sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql 실행

Gate-02(증거): draft 1건 생성 → 목록에서 draft_only로 조회 / publish action 수행 → published_only에서 조회 / content_hash 중복은 duplicate ignored 유지
```

### PC 실행
published_at이 NOT NULL이면 draft 지원 불가 → 그 경우에만 `sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql` 실행.

### Gate-02
draft 1건 생성 → draft_only 조회 / publish 수행 → published_only 조회 / content_hash 중복 duplicate ignored 유지.

---

## v1.7-03 — 타겟팅 v1 (구독 기반 대상 산출)

### Cursor 지시 프롬프트 (복붙용)

```
[GOAL] For a given alert_event_id, compute target users.

[IMPLEMENT]
- Target rule v1:
  - event.ref_type='route' and route_label not null
  - target users = app_subscriptions where is_active=1 and target_type='route' and target_id matches '{ref_id}_{route_label}'
- Add helper: compute_target_user_ids($event)

[FILES]
- public/admin/alert_ops.php: For each published event, show "target_user_cnt"; Add "Preview targets" link to new page
- public/admin/alert_targets.php (new, read-only): event_id required, list up to 200 user_id with subscription match evidence
- docs/releases/v1.7/smoke/smoke_03_targeting_preview.md + docs/releases/v1.7/gate/gate_03_targeting_preview.md
- sql/releases/v1.7/validation/validation_03_targeting_preview.sql (optional) for key queries

PC 실행: 없음
Gate-03: event_id 기준 target_user_cnt가 0이 아닌 케이스 확인(구독 존재 시)
```

### PC 실행
없음.

### Gate-03
event_id 기준 target_user_cnt가 0이 아닌 케이스 확인(구독 존재 시).

---

## v1.7-04 — 발송 v1 (deliveries 상태 전환: pending→sent)

### Cursor 지시 프롬프트 (복붙용)

```
[GOAL] Implement "dispatch" in admin for published events.

[BEHAVIOR]
- Dispatch creates/updates app_alert_deliveries rows per target users:
  - channel='inapp' (or 'web' if defined)
  - status='pending' then immediately set status='sent' with sent_at=NOW() (MVP)
- Must be idempotent: Use INSERT ... ON DUPLICATE KEY UPDATE to avoid duplicates.

[FILES]
- public/admin/alert_ops.php: Action button "Dispatch" on published events; After dispatch: show delivery_cnt (by channel/status)
- scripts/run_alert_dispatch.php (optional CLI): php scripts/run_alert_dispatch.php --event_id=123
- docs/releases/v1.7/smoke/smoke_04_publish_guard.md + docs/releases/v1.7/gate/gate_04_publish_guard.md
- sql/releases/v1.7/validation/validation_04_publish_guard.sql: duplicates check (0 rows), counts

PC 실행: 없음(성능 이슈면 인덱스 "확인 필요")
Gate-04: dispatch 1회 후 delivery_cnt 증가 + 중복 0 rows
```

### PC 실행
없음. (성능 이슈 시 인덱스 "확인 필요".)

### Gate-04
dispatch 1회 후 delivery_cnt 증가 + 중복 0 rows.

---

## v1.7-05 — Retry/Idempotency 최소 정책 (운영 사고 방지)

### Cursor 지시 프롬프트 (복붙용)

```
[GOAL] Define and minimally implement retry behavior.

[POLICY]
- Re-dispatch is allowed: Should not create new rows if same channel already exists. If status='failed', allow update to 'sent' on retry.
- For MVP, simulate failed by manually setting a row status to 'failed' in DB (optional test).

[FILES]
- docs/references/RETRY_POLICY_v1_7.md
- public/admin/alert_event_audit.php: Filter status=failed; Add "Retry dispatch" button (calls same dispatch with WHERE status IN ('failed','pending'))
- docs/releases/v1.7/smoke/smoke_05_delivery_queue.md + docs/releases/v1.7/gate/gate_05_delivery_queue.md

PC 실행: 선택(테스트용)
Gate-05: retry 실행해도 중복 0 + failed→sent 전환 증거
```

### PC 실행
선택(테스트용).

### Gate-05
retry 실행해도 중복 0 + failed→sent 전환 증거.

---

## v1.7-06 — Audit 요약 강화 (운영자용 수치 1페이지)

### Cursor 지시 프롬프트 (복붙용)

```
[GOAL] Add per-event funnel summary.

[OUTPUT]
- public/admin/alert_funnel.php (new, read-only)
  - event_id filter optional
  - columns: event_id, target_cnt, deliveries_cnt, shown_cnt(web), sent_cnt(inapp), last_activity
- Use existing tables only.

[DOCS]
- docs/releases/v1.7/smoke/smoke_06_approver_role.md + docs/releases/v1.7/gate/gate_06_approver_role.md

PC 실행: 없음
Gate-06: ops에서 "target ≥ deliveries ≥ shown/sent" 일관성 확인
```

### PC 실행
없음.

### Gate-06
ops에서 "target ≥ deliveries ≥ shown/sent" 일관성 확인.

---

## v1.7-07 — Release Gate + E2E (최소 1케이스 통과)

### Cursor 지시 프롬프트 (복붙용)

```
[GOAL] Create v1.7 release gate doc and an E2E checklist.

[FILES]
- docs/releases/v1.7/RELEASE_GATE.md
  - Gate items: create(draft), approve, publish, target preview, dispatch, user view, audit funnel, duplicates=0
  - Copy-paste SQL blocks: event fetch, deliveries count, duplicates check
- docs/releases/v1.7/v1.7_E2E.md
  - Step-by-step with expected outputs and evidence fields

PC 실행: Gate용 SELECT만
Gate-07: E2E PASS(증거 채움)
```

### PC 실행
Gate용 SELECT만.

### Gate-07
E2E PASS(증거 채움).

---

## MVP2.5(v1.5)에서 미리 했으면 v1.7에서 효력 나는 3개

1. **Observability baseline** — 운영자 액션(approve/dispatch/retry)에 error_log 1줄씩 추가하면 추적 가능.
2. **Alert ref contract** — 타겟팅에서 ref_type/ref_id/route_label이 곧 join 키로 사용됨.
3. **Delivery semantics** — user 화면 노출 기록과 admin dispatch 기록이 충돌하지 않도록 채널 분리/UNIQUE 유지가 핵심.

---

## 다음 실행 순서 (권장)

1. **v1.7-01** 문서 고정(합의용).
2. **v1.7-02** 승인/발행부터 구현(운영 플로우 뼈대).
3. **v1.7-03** 타겟 프리뷰 → **v1.7-04** dispatch → **v1.7-06** funnel(운영 수치).
4. 마지막에 **v1.7-07** 릴리즈 게이트.

---

## v1.7-02 착수 전 확인: published_at NULL 가능 여부

v1.7-02에서 draft를 "published_at NULL"로 둘지 결정하려면, 현재 스키마에서 `published_at`이 NULL 허용인지 확인.

**Workbench에서 실행 (1개):**

```sql
-- app_alert_events.published_at NULL 허용 여부
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'app_alert_events'
  AND COLUMN_NAME = 'published_at';
```

- **IS_NULLABLE = 'NO'** 이면 draft 지원을 위해 `ALTER TABLE app_alert_events MODIFY published_at DATETIME NULL` (또는 status 컬럼 추가) 필요 → sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql 작성 후 PC에서만 실행.
- **IS_NULLABLE = 'YES'** 이면 스키마 변경 없이 published_at NULL = draft로 사용 가능.

**확인 결과 (실행 완료):**  
`published_at`, IS_NULLABLE=**NO**, COLUMN_DEFAULT=NULL, DATA_TYPE=datetime  
→ **draft 지원 시 스키마 변경 필요.** v1.7-02에서 sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql (published_at NULL 허용 또는 status 컬럼) 작성·실행 후 구현.

---

## Phase-2 (로드맵): 지도 기반 UI 및 길찾기 엔진

지도 기반 UI, 경로 폴리라인, 길찾기 엔진은 Phase-2(로드맵) 범주이다. 상세: docs/operations/PLAN_UX_OPERATIONS_ROUTE_FINDER_v1_7.md Part 3, docs/ux/NAVER_MAP_UI_ADOPTION_v1_8.md.

---

*문서 버전: v1.6-10 Gate PASS 이후 확정. v1.7 착수 시 이 로드맵을 기준으로 실행.*
