# Admin UI/UX 일괄 점검·수정 가이드

**목적:** 페이지마다 UI/UX 규칙이 어긋난 곳을 SOT 기준으로 찾아 **일괄 점검·수정**하는 방법을 정리합니다.  
**참조:** [SOT_GILAIME_UI_SYSTEM.md](./SOT_GILAIME_UI_SYSTEM.md), [ADMIN_QA_CHECKLIST.md](./ADMIN_QA_CHECKLIST.md)

---

## 1) 일괄 적용을 위한 3가지 방법

| 방법 | 설명 | 적합한 경우 |
|------|------|-------------|
| **A. 감사 스크립트 실행** | `php scripts/php/admin_ui_audit.php`로 위반 패턴 자동 검출 | 먼저 “어디가 어긋났는지” 목록을 보고 싶을 때 |
| **B. grep으로 패턴 검색** | 아래 표의 검색어로 프로젝트 검색 후 수동 수정 | 특정 규칙만 한 번에 찾아 고칠 때 |
| **C. 공통 레이아웃 확장** | `admin_layout_start.php` / `admin_layout_end.php`로 `<main>`·nav·breadcrumb 공통화 | 새 페이지 추가 시 자동 적용·기존 페이지 단계적 마이그레이션 |

---

## 2) SOT 규칙별 점검·수정 (grep용)

### 2.1 공통 레이아웃

| 규칙 | 검색(위반 찾기) | 수정 예시 |
|------|------------------|-----------|
| main 래퍼 | `grep -L "container-fluid py-4" public/admin/*.php` (login 제외) | `<main class="container-fluid py-4">` 고정 |
| nav 사용 | `grep -L "render_admin_nav" public/admin/*.php` | 상단에 `<?php render_admin_nav(); ?>` 추가 |
| breadcrumb | `grep -L "render_admin_header" public/admin/*.php` | `render_admin_header([...], false)` 호출 |

### 2.2 페이지 제목·헤더

| 규칙 | 검색(위반 찾기) | 수정 예시 |
|------|------------------|-----------|
| 제목 영역 | `grep -L "g-page-head" public/admin/*.php` | 제목+설명을 `<div class="g-page-head"><h2>...</h2><p class="helper mb-0">...</p></div>` 로 감싸기 |
| 제목+버튼 한 줄 | 제목 옆에 버튼이 있는데 `g-page-header-row` 없음 | `<div class="g-page-header-row">` 로 제목 div와 버튼 div 감싸기 |
| Section 제목 | 섹션 제목이 h4/h6 등으로 되어 있음 | `h3 class="h5 mb-2"` 로 통일 (SOT: Section Title) |

### 2.3 힌트·보조 문구

| 규칙 | 검색(위반 찾기) | 수정 예시 |
|------|------------------|-----------|
| 힌트 클래스 | `class="text-muted"` (Bootstrap 기본만 사용) | `text-muted-g small` 또는 `g-text-hint` 로 통일 |
| helper | 페이지 제목 아래 설명이 `.helper` 아님 | `<p class="helper mb-0">` 또는 `class="text-muted-g small mb-2"` |

### 2.4 테이블

| 규칙 | 검색(위반 찾기) | 수정 예시 |
|------|------------------|-----------|
| 래퍼 | `<table` 앞에 `table-responsive` div 없음 | `<div class="table-responsive">` 로 테이블 감싸기 |
| 테이블 클래스 | `g-table` 없음 | `table table-hover align-middle g-table g-table-dense mb-0` |
| 빈 메시지 | `(없음)` / `(none)` / `데이터가 없습니다` 아닌 문구 | [ADMIN_QA_CHECKLIST §4] 표에 맞게 통일 |

### 2.5 버튼·링크

| 규칙 | 검색(위반 찾기) | 수정 예시 |
|------|------------------|-----------|
| 주요 버튼 | `btn-primary` (Bootstrap 기본) | `btn btn-gilaime-primary btn-sm` |
| 보조 버튼 | `btn-secondary` 만 사용 | `btn btn-outline-secondary btn-sm` |
| 링크 base | `href="/admin/` (APP_BASE 없음) | `href="<?= APP_BASE ?>/admin/...` |

### 2.6 카드

| 규칙 | 검색(위반 찾기) | 수정 예시 |
|------|------------------|-----------|
| 카드 클래스 | `card` 만 있고 `g-card` 없음 | `card g-card` + `card-body` |

---

## 3) 감사 스크립트 사용법

```bash
cd /path/to/gilime_mvp_01
php scripts/php/admin_ui_audit.php
```

- **출력:** 파일별로 위반 가능 항목을 목록으로 출력합니다. (예: `g-page-head 없음`, `table-responsive 없음`, 빈 메시지 비표준)
- **용도:** 수정 대상 파일·라인을 파악한 뒤, 위 §2 표에 따라 일괄 수정할 때 참고합니다.

---

## 4) 공통 레이아웃으로 일괄 적용 (선택)

현재는 각 Admin 페이지가 직접 `<main>`, `render_admin_nav()`, `render_admin_header()` 를 호출합니다.  
**옵션:** `app/inc/admin/admin_layout_start.php` / `admin_layout_end.php` 를 두고,

- `admin_layout_start.php`: doctype, head, body, `<main class="container-fluid py-4">`, nav, header 출력
- 각 페이지: `require admin_layout_start.php` 후 본문만 출력
- `admin_layout_end.php`: `</main></body></html>`

이렇게 하면 **main·nav·breadcrumb·container 클래스**를 한 곳에서 바꿀 수 있어, 이후 규칙 변경 시 일괄 반영이 쉽습니다.  
(기존 페이지는 한 번에 바꾸지 않고, 새 페이지부터 적용·기존은 점진적으로 마이그레이션 가능)

---

## 5) 빠른 체크리스트 (페이지별 수동 확인)

각 Admin 페이지를 열었을 때 다음만 확인해도 대부분의 규칙을 점검할 수 있습니다.

- [ ] 상단에 "길라임 Admin | 문서 | 운영 | 알림 | 감사" 네비 + 로그아웃
- [ ] breadcrumb "문서 허브 / 현재 페이지명"
- [ ] 메인 제목이 `g-page-head` 안의 h2 + helper 문단
- [ ] 테이블이 `table-responsive` 안에 있고, 빈 경우 "데이터가 없습니다" 등 표준 문구
- [ ] 주요 CTA는 초록(lime), 보조는 outline 회색
- [ ] 링크가 상대경로가 아닌 `APP_BASE` 포함 경로

---

**정리:**  
1) `admin_ui_audit.php` 실행 → 위반 목록 확인.  
2) §2 grep으로 해당 규칙 위반 파일 찾아 수정.  
3) 필요하면 §4처럼 공통 레이아웃 도입해 앞으로는 한 곳만 수정하도록 적용.
