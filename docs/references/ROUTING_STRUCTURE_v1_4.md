# GILIME — Routing & Structure for v1.4 (MVP2 prep)

**We are preparing for v1.4 one-shot expansion (MVP2).**  
이 문서는 MVP2(v1.4) 일괄 확장 전에, URL·폴더·네이밍·쿼리 파라미터 규칙을 고정하기 위한 것이다.

---

## Folder structure

- **public/admin/** — 관리자 전용. 기존 페이지 유지. (index, doc, login, logout, route_review, review_queue, ops_dashboard, alias_audit, run_job, parse_match, promote 등)
- **public/user/** — (v1.4 신규) 일반 사용자 페이지. 구독·알림·노선 조회 등.
- **public/api/** — (선택) REST/JSON API. v1.4에서 필요 시 추가.
- **app/inc/** — 공통 부트스트랩: auth, db, config. admin/user에서 공통 require.
- **docs/** — 설계·정책·검증 문서. (SECURITY_BASELINE, ERROR_POLICY, PERF_NOTES 등)
- **sql/** — DDL, 마이그레이션, 검증 스크립트. 웹에서 직접 접근 불가.

---

## URL conventions

- **/admin/...** — 관리자. `require_admin()` 적용. 예: `/admin/`, `/admin/doc.php?id=1`, `/admin/route_review.php?source_doc_id=1&route_label=...`
- **/user/...** — (v1.4) 사용자. 예: `/user/`, `/user/home.php`, `/user/routes.php`, `/user/alerts.php`
- **루트(/)**: 리다이렉트 정책은 v1.4에서 정의. (예: `/` → `/user/` 또는 `/admin/`)

---

## Naming conventions for new pages (v1.4)

- **public/user/**  
  - **home.php** — 사용자 대시/홈  
  - **routes.php** — 노선 목록·조회  
  - **alerts.php** — 알림/구독 관련  
  - 기타: 소문자, 언더스코어 없이 단어 구분은 필요 시 일관된 규칙으로 추가

---

## Query parameter conventions (유지)

- **admin 쪽 (기존 유지):**  
  - **quick_mode**, **show_advanced** — route_review 뷰 옵션  
  - **jump_next** — 검수 후 다음 노선 자동 이동  
  - **focus_cand_id** — 포커스할 후보  
  - **doc_id**, **route_label**, **source_doc_id** — 문서/노선 식별  
  - **sort** — 정렬 옵션 (예: updated, risky, default, simple)  
  - **only_risky**, **limit** — review_queue 필터·페이징
- **user 쪽 (v1.4):** 별도 규칙은 v1.4 설계 시 정의.

---

*문서 버전: v1.3-06. v1.4 one-shot expansion 시 이 구조를 기준으로 페이지·라우팅을 추가한다.*
