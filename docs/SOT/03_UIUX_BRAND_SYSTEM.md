# SoT 03 — UI/UX + Branding System (Single Source of Truth)

## 0) 목적 / 범위

- 목적: 길라임(GILIME)의 UI/UX와 브랜딩(컬러/토큰)을 **단일 기준(SoT)** 으로 고정하고, PHP SSR + 단일 CSS + 운영형(관리자 승인) 구조에서 **일관된 구현**이 가능하도록 한다.
- 범위: (1) 브랜딩 컬러 시스템 (2) 토큰(Colors/Spacing/Typography/Radii) (3) UI/UX 원칙 (4) 핵심 컴포넌트 규칙 (5) 접근성/QA 체크리스트
- 비범위: 로고/일러스트 가이드, 정교한 모션 시스템(고급 애니메이션), 다크모드 전체 설계(향후 MVP+)

## 1) 고정 전제

- 지도 중심 UX, 서울 한정 MVP
- 핵심 컨셉: 이슈 기반 탐색 + 임시셔틀 포함 길찾기 + 구독 기반 재탐색
- 구현 제약: Bootstrap 우선 + 공통 CSS 1개(`public/assets/css/gilaime_ui.css`)
- 운영 원칙: “자동 적용” 금지. DB 기반 + 관리자 승인/수정/삭제 가능해야 한다.

## 2) UX 원칙 (핵심)

1. 정보 계층: 제목 → 본문 → 힌트 (과밀 금지)
2. 운영 가독성: Admin 화면에서 “다음 행동”이 1초 내 파악 가능
3. 카피: 짧고 단정(운영 화면은 명령형 동사 우선)
4. 모달 남발 금지: 기본은 인라인 안내 / 시트(바텀시트)는 컨텍스트 유지 목적일 때만
5. 접근성: `:focus-visible` 유지, 터치 타깃 최소 44px 기준을 따른다 (외부 검증 필요)

## 3) 브랜딩 컬러 시스템 (Brand vs Functional 분리)

### 3.1 Brand (길라임 정체성)

- Brand Primary (Transit Lime): `#A4E600`
- Deep Base: `#0F1C2E`

역할:
- Brand Primary는 “임시셔틀/대체/회복”에만 강하게 사용한다.
- Deep Base는 “신뢰/데이터/인프라” 인상을 위해 텍스트/강조에 제한적으로 사용한다.

### 3.2 Neutrals (UI 기본면)

- Background Light: `#F5F7FA`
- Card: `#FFFFFF`
- Border: `#E4E7EC`
- Text Primary: `#101828`
- Text Secondary: `#667085`

원칙:
- 기본 UI는 중립 톤(Neutral) 중심. Lime은 포인트(기능 강조)로만 사용.

### 3.3 Functional Colors (의미 기반)

Issue:
- Critical: `#E03131`
- Warning: `#F76707`
- Notice: `#FAB005`
- Inactive: `#ADB5BD`

Transport:
- Bus: `#1C7ED6`
- Subway: `#7048E8`
- Shuttle: Brand Primary 사용

## 4) 토큰 시스템 (CSS Variables)

### 4.1 토큰 레이어

- Global Tokens: `--g-brand-*`, `--g-neutral-*`
- Functional Tokens: `--g-issue-*`, `--g-transport-*`
- Focus Tokens: `--g-focus-ring`
- Back-compat Alias: 기존 변수명(`--gilaime-*`)은 alias로 유지(점진 교체)

### 4.2 CSS 토큰(최종 기준)

Brand:
- `--g-brand-primary: #A4E600`
- `--g-brand-primary-soft: #F3FFD1` (가설: 소프트 배경)
- `--g-brand-primary-press: #86C800` (가설: hover/pressed)
- `--g-brand-deep: #0F1C2E`

Neutrals:
- `--g-neutral-bg: #F5F7FA`
- `--g-neutral-card: #FFFFFF`
- `--g-neutral-border: #E4E7EC`
- `--g-neutral-hover: #F2F4F7`
- `--g-neutral-ink: #101828`
- `--g-neutral-muted: #667085`

Issue:
- `--g-issue-critical: #E03131`
- `--g-issue-warning: #F76707`
- `--g-issue-notice: #FAB005`
- `--g-issue-inactive: #ADB5BD`

Transport:
- `--g-transport-bus: #1C7ED6`
- `--g-transport-subway: #7048E8`
- `--g-transport-shuttle: var(--g-brand-primary)`

Focus:
- `--g-focus-ring: #E8FF99` (가설: 포커스 링)

검증:
- 대비(WCAG AA) 충족 여부는 외부 검증 필요(확인 필요).

## 5) 컴포넌트 규칙 (MVP 고정)

### 5.1 필터 칩(상단)

- 시각 높이: 28px pill 유지
- 실제 탭 영역: 44px 이상 확보
- Active: Lime soft 배경 + Lime border + Deep 텍스트

### 5.2 Bottom Sheet (홈/길찾기 공용)

- 상태: `collapsed / half / full`
- Detent 기반 스냅(드래그 후 가장 가까운 상태로 정착)
- 하단 내비와 시트는 “틈 없이” 붙는다.

### 5.3 CTA/Link 색상

- 링크/강조 블루를 남발하지 않는다.
- 기본 링크는 Deep/Base 또는 Neutral Ink를 사용하고,
- “중요 행동”은 Brand Primary(또는 Functional)로만 강조한다.

## 6) 구현 규칙 (코드 반영 원칙)

1. HEX 직접 사용 금지(기존 코드 제외). 신규/수정은 토큰만 사용.
2. `--gilaime-*`는 유지하되, 점진적으로 `--g-*`로 통일한다.
3. Home UI에서 `#03c75a`, `#2f80ff` 등 외부 브랜드 컬러는 금지하고 토큰으로 치환한다.
4. `prefers-reduced-motion`을 존중한다.

## 7) QA 체크리스트 (MVP)

- 칩/버튼/탭: 탭 영역 44px 이상 (확인 필요: 실제 기기)
- 포커스: 키보드 탭 이동 시 outline 정상
- 시트: collapsed/half/full 상태 스냅 정상 + 내비와 gap 없음
- 색상: 임시셔틀/대체 강조가 Lime으로 일관
- 하드코딩 색상: Home 관련 컴포넌트에서 `#03c75a`, `#2f80ff` 잔존 여부 점검

## 8) 변경 로그

- 2026-02-20: Brand Primary를 `#A4E600`으로 확정, Functional Color 분리, hit target 44px 및 bottom sheet detent 도입.