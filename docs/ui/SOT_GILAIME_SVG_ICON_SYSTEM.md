# SOT_GILAIME_SVG_ICON_SYSTEM

## 1) 목적
- 홈/길찾기/이슈/마이노선 화면에서 아이콘 스타일을 단일 규격으로 통일한다.
- 이모지, 페이지별 임의 SVG를 제거하고 공통 심볼 스프라이트 기반으로 운영한다.
- Naver/Google/Apple의 공통 패턴(단순한 형태, 라운드 라인, 고대비 상태 변화)을 참고하되 길라임 색상 규칙을 우선한다.

## 2) 설계 원칙
- **단순성:** 작은 크기(14~20px)에서도 형태를 즉시 인지 가능해야 한다.
- **일관성:** stroke 굵기, 곡률, 캔버스(viewBox 24x24)를 통일한다.
- **상태 표현:** active/inactive/disabled를 색상과 배경으로 구분한다.
- **접근성:** 아이콘 버튼은 `aria-label` 또는 시각적 텍스트를 반드시 가진다.

## 3) 토큰 규격
- 캔버스: `24x24`
- 선 스타일: `stroke-linecap=round`, `stroke-linejoin=round`
- 기본 선 두께: `1.9`
- 크기:
  - 상단 CTA: `22px`
  - 하단 시트 탭: `16px`
  - 하단 네비 내부: `18px` (active `19px`)
- 컬러:
  - active-green: `#03c75a`
  - active-blue: `#2f80ff`
  - inactive-gray: `#7b8794`
  - icon-bg: `#f4f7fb`
  - active-bg: `#e8f9ef`

## 4) 파일/사용 규칙
- 아이콘 소스 파일: `public/assets/icons/gilaime_nav.svg`
- 공통 렌더 방식:
  - `<svg class="g-icon-svg ..."><use href=".../gilaime_nav.svg#icon-id"></use></svg>`
- 공통 CSS:
  - `.g-icon-svg { fill:none; stroke:currentColor; }`
  - 색상은 컴포넌트가 `currentColor`로 제어한다.

## 5) 표준 아이콘 목록 (v1)
- `icon-home`: 홈
- `icon-issue`: 이슈/경고
- `icon-route`: 길찾기/경로
- `icon-star`: 즐겨찾기/마이노선
- `icon-locate`: 현재 위치 추적
- `icon-place`: 장소 탭/핀
- `icon-bus`: 버스/셔틀 (모의 주행 마커 등)
- `icon-time`: 시간/소요시간
- `icon-flag`: 도착/목적지
- `icon-check`: 완료/성공
- `icon-list`: 목록/상세
- `icon-play`: 시작/재생

## 6) 적용 범위 (현재)
- `public/user/home.php`
  - 상단 CTA: `icon-locate`, `icon-route`
  - 하단 시트 탭: `icon-issue`, `icon-route`
  - 하단 네비: `icon-home`, `icon-issue`, `icon-route`, `icon-star`
- 스타일 파일: `public/assets/css/gilaime_ui.css`

## 7) 금지 규칙
- 이모지 아이콘 사용 금지 (`📍`, `🧭` 등)
- 페이지별 인라인 아이콘 임의 생성 금지 (공통 스프라이트 외)
- filled/outline 스타일 혼용 금지 (예외는 명시 승인 후)

## 8) 확장 절차
1. 새 아이콘을 `gilaime_nav.svg`에 `symbol`로 추가
2. `SOT_GILAIME_SVG_ICON_SYSTEM.md` 목록 갱신
3. 적용 페이지에서 `<use>` 방식으로 교체
4. 크기/색상은 기존 토큰만 사용
