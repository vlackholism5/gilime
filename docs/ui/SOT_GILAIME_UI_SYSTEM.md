# SOT_GILAIME_UI_SYSTEM

## 1) 목표와 품질 기준
- 목적: PHP SSR 구조를 유지하면서 "저비용/고효율"로 UI 일관성을 확보한다.
- 운영 품질: Admin 화면에서 다음 행동이 1초 내 파악 가능해야 한다.
- 사용자 품질: User 화면에서 구독/알림 확인 흐름이 3클릭 내 완결되어야 한다.
- 기술 제약: Bootstrap 우선, 공통 CSS 1개(`public/assets/css/gilaime_ui.css`)만 사용한다.

## 2) 디자인 방향 (실무형 가이드 반영)
- Material 스타일의 정보 계층(제목-본문-힌트)과 Apple 스타일의 절제된 시각 밀도를 결합한다.
- 브랜드는 라임을 포인트로만 사용하고, 기본 표면은 중립 톤으로 유지한다.
- 카피는 Toss/당근 톤처럼 짧고 친절하게 유지하되 운영 화면은 명령형 문장으로 통일한다.

## 3) 토큰 시스템
### Spacing (8px rhythm)
- `--g-space-1` = 8px (`0.5rem`)
- `--g-space-2` = 16px (`1rem`)
- `--g-space-3` = 24px (`1.5rem`)
- `--g-space-4` = 32px (`2rem`)
- Bootstrap 매핑: `mb-2 ~= 8px`, `mb-3 ~= 16px`, `mb-4 ~= 24px` 기준 사용
- **네이버 지도 참조:** 8-point grid 확장 시 `--g-space-0`(4px), `--g-space-5`(40px) 추가 가능. 상세: [NAVER_MAP_GRID_GUIDE_v1_8.md](../ux/NAVER_MAP_GRID_GUIDE_v1_8.md)

### Typography
- 폰트 스택: Pretendard/Noto Sans KR/맑은 고딕/system-ui
- `--g-fs-page-title`, `--g-fs-section-title`, `--g-fs-body`, `--g-fs-hint`
- 역할 분리:
  - Page Title: `.g-page-head h1/h2`
  - Section Title: `h5/h6`
  - Body: `.g-text-body`
  - Hint: `.g-text-hint` 또는 `.text-muted-g.small`

### Color
- Primary CTA: `--gilaime-lime`
- Primary Hover: `--gilaime-lime-700`
- Border/Surface: `--gilaime-border`, white surface
- Text: `--gilaime-ink`, muted는 `--gilaime-muted`
- 상태 컬러:
  - draft: gray
  - published/shown/sent: lime-soft
  - pending: yellow-soft
  - failed: red-soft

## 4) 컴포넌트 규격
- Card: `card g-card`
- Table:
  - 기본: `table table-hover align-middle g-table`
  - 데이터가 많은 화면: `g-table-dense`
  - 좁은 화면: 반드시 `table-responsive`
  - 시각 규칙: 헤더 nowrap, 본문 `word-break: keep-all`, hover/striped 적용
- Button:
  - 주요 액션: `btn btn-gilaime-primary`
  - 일반 액션: `btn btn-outline-secondary btn-sm`
- Badge:
  - 상태별 badge class 사용 (draft/published/pending/failed)
- Form:
  - `form-control-sm`, `form-select-sm`, inline은 `g-form-inline`
- Flash:
  - Bootstrap alert(`alert-success|warning|danger|info`) 사용

## 5) 접근성/상호작용 최소 규격
- 키보드 포커스: `:focus-visible` 아웃라인 유지 (버튼/링크/입력)
- 단축키 도움말: 페이지 상단 `details.kbd-help` 고정
- 모달 의존 금지, 텍스트 기반 안내 우선

## 6) Admin/User 카피 규칙
- 동사형 우선: 실행/발행/검수/업로드/적용
- 실패 문구: 원인 먼저, 조치 가능성 다음
- 영어 고유명은 최소 유지(`Publish`, `Parse/Match` 등)

## 7) 적용 상태 점검 (2026-02, v1.8 갱신)

### Admin 전체 적용 대상
| 페이지 | g-admin-nav | breadcrumb | admin_header | 비고 |
|--------|-------------|------------|--------------|------|
| index.php | o | o | o | 문서 허브 |
| doc.php | o | o | o | 검수 바로가기 CTA |
| upload_pdf.php | o | o | o | |
| review_queue.php | o | o | o | |
| route_review.php | o | o | o | |
| ops_dashboard.php | o | o | o | |
| ops_summary.php | o | o | o | |
| ops_control.php | o | o | o | |
| alias_audit.php | o | o | o | |
| alert_ops.php | o | o | o | |
| alert_event_audit.php | o | o | o | |
| login.php | - | - | - | |

### 공통 헤더 (app/inc/admin/admin_header.php)
- `render_admin_nav()`: 그룹 링크(문서|운영|알림|감사) + 로그아웃
- `render_admin_header($breadcrumbs, $showLogout=true)`: breadcrumb 배열 + 로그아웃 (nav 사용 시 false)
- 적용: 모든 Admin 페이지 (login 제외)

### 체크리스트
- [x] 모든 Admin 페이지 `render_admin_nav` + `render_admin_header` 사용 (v1.8)
- [x] breadcrumb 형식: `문서 허브 / 현재 페이지`
- [x] inline `style=""` 제거 (route_review → `.g-form-file-input`)
- [x] 버튼 SOT: `btn-gilaime-primary`, `btn-outline-secondary` (route_review, index)
- [x] form-control-sm 적용 (login.php)
- [ ] page-local `<style>` 제거 (해당 시 검토)
- [x] Bootstrap + gilaime_ui.css 조합으로 렌더링
- [x] g-page-head margin 통일, g-page-header-row 도입 (2026-02 QA)
- [x] 빈 테이블 메시지 통일: `(없음)`/`(none)` → `데이터가 없습니다`

### 적용 완료
- `public/user/home.php`, `routes.php`, `alerts.php`
- `public/admin/*` 전체 (v1.8 admin layout 확장)

### 참조
- [ADMIN_WIREFRAME_v1_8.md](../ux/ADMIN_WIREFRAME_v1_8.md) — 관리자 화면 와이어프레임
- [ADMIN_QA_CHECKLIST.md](./ADMIN_QA_CHECKLIST.md) — QA 체크리스트 (v1.8)

## 8) Non-goals
- DB 스키마/정책/핵심 로직 변경
- 페이지 전면 재작성
- JS 프레임워크 도입
