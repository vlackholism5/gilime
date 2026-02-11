# SOT_GILAIME_UI_SYSTEM

## 목적
- PHP 서버 렌더링 기반에서 저비용/고효율 UI 일관성 확보
- 운영(Admin) 처리 속도 향상과 사용자(User) 가독성 개선
- Bootstrap 중심 + 공통 CSS 1개(`public/assets/css/gilaime_ui.css`) 유지

## 브랜딩 방향
- 톤: 명확함, 절제, 빠른 스캔
- 포인트: 길라임 라임 색상은 CTA와 상태 강조에만 제한적으로 사용
- 기본 배경/테이블은 저채도 중립 톤 유지

## 컬러 시스템 (Lime 기반)
- Primary CTA: `--gilaime-lime`
- Primary Hover: `--gilaime-lime-700`
- Border/Surface: `--gilaime-border`, white card
- Muted Text: `--gilaime-muted`
- 상태:
  - draft: 회색
  - published/sent/shown: 연녹색
  - pending: 노란색
  - failed: 붉은색

## 타이포 시스템 (한글 가독성)
- 폰트 우선순위: Pretendard/Noto Sans KR/맑은 고딕/system-ui
- 제목 계층:
  - Page Title: `h1/h2` + 굵기 800
  - Section Title: `h5/h6`
- 본문/도움말:
  - 본문은 Bootstrap 기본 크기
  - 보조 문구는 `.text-muted-g.small`

## 간격 규칙 (8px 리듬)
- 기본 단위: 8px
- 섹션 간격: `mb-3`, `mb-4`
- 카드 내부: `card-body` 기본 패딩 유지
- 짧은 설명/보조행: `mt-2`, `mb-0`

## 컴포넌트 규칙
- Table: `table table-hover align-middle g-table` + 필요 시 `table-responsive`
- Button:
  - 주요 액션: `btn btn-gilaime-primary`
  - 일반 액션: `btn btn-outline-secondary btn-sm`
- Badge:
  - draft/published/pending/failed 상태 클래스 사용
- Card: `card g-card`
- Flash:
  - 성공/실패/안내는 Bootstrap `alert-*` 사용
- Form:
  - `form-control-sm`, `form-select-sm`, inline은 `g-form-inline`

## Admin/User 카피 규칙 (한국어)
- 동사형 중심: 실행/발행/검수/업로드
- 상태는 짧고 즉시 이해 가능하게 유지
- 에러/차단 메시지는 원인 먼저 표기

## 단축키 안내 규칙
- 모달 금지
- 페이지 상단에 `details.kbd-help` 접기 영역으로 표시
- 텍스트만 제공 (`/`, `Esc`, `Ctrl+Enter`)

## Non-goals
- DB 스키마/정책/핵심 로직 변경
- 페이지 전면 재작성
- JS 프레임워크 도입
