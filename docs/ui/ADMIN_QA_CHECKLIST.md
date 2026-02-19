# Admin UI QA 체크리스트 (v1.8)

## 1. 공통 레이아웃

| 항목 | 규격 | 확인 |
|------|------|------|
| `render_admin_nav()` | 모든 Admin 페이지 (login 제외) | o |
| `render_admin_header($breadcrumbs, false)` | nav 사용 시 showLogout=false | o |
| breadcrumb 형식 | `문서 허브 / 현재 페이지` | o |
| main 구조 | `<main class="container-fluid py-4">` | o |

## 2. 페이지 제목(헤더) 구조

| 요소 | 클래스 | 용도 |
|------|--------|------|
| breadcrumb 영역 | `g-top` | admin_header 내부 |
| 제목+액션 행 | `g-page-header-row` | 제목과 버튼을 한 줄에 배치 (index, ops_control, ops_summary) |
| 페이지 제목 | `g-page-head` | h2 + helper 텍스트 |
| helper 텍스트 | `helper mb-0` | g-page-head 내부, `.helper` (SOT: text-muted-g) |

## 3. g-page-head 규칙

- **margin**: CSS에서 `margin-bottom: var(--g-space-2)` 적용 (개별 mb-3 제거)
- **g-page-header-row 사용 시**: `.g-page-header-row .g-page-head { margin-bottom: 0 }`로 중복 제거

## 4. 빈 테이블 메시지 통일

| 용도 | 메시지 |
|------|--------|
| 일반 데이터 | `데이터가 없습니다` |
| 이슈 | `이슈가 없습니다` |
| 후보 | `후보가 없습니다` |
| 검색 결과 | `검색 결과가 없습니다` |
| 노선 정류장 | `노선 정류장이 없습니다` |
| 승격 이력 | `승격(PROMOTE) 이력이 없습니다` |
| 작업 이력 | `작업 이력이 없습니다` |

- X `(없음)`, `(none)` → O `데이터가 없습니다`

## 5. 테이블 규격

- 래퍼: `table-responsive`
- 클래스: `table table-hover align-middle g-table g-table-dense mb-0`
- 데이터가 적은 테이블: `g-table-dense` 생략 가능 (index 문서 목록 등)

## 6. 버튼 SOT

- 주요: `btn btn-gilaime-primary btn-sm`
- 일반: `btn btn-outline-secondary btn-sm`

## 7. 폼 SOT

- 입력: `form-control form-control-sm`
- 인라인 폼: `g-form-inline`

## 8. 참조

- [SOT_GILAIME_UI_SYSTEM.md](./SOT_GILAIME_UI_SYSTEM.md)
- [ADMIN_WIREFRAME_v1_8.md](../ux/ADMIN_WIREFRAME_v1_8.md)

---

*문서 버전: v1.8. 2026-02 QA 반영.*
