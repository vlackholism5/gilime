# SoT 03A — UX Principles

## Purpose

Single source of truth for user experience principles. Aligns Admin/User flows and copy with minimal, controllable UX.

## Definitions

- **Admin flow:** 관리자(운영자) 전용 화면. 문서·검수·승격·알림 운영·감사.
- **User flow:** 일반 사용자. 노선/정류장 조회·구독·알림·경로 안내.
- **3-click rule:** 사용자 구독/알림 확인 흐름이 3클릭 내 완결 목표.

## Principles

1. **정보 계층:** 제목 → 본문 → 힌트 순. Material 스타일 정보 계층 + Apple 스타일 절제된 밀도.
2. **운영 가독성:** Admin에서 다음 행동이 1초 내 파악 가능.
3. **카피:** 짧고 친절(Toss/당근 톤). 운영 화면은 명령형 문장 통일. 동사형 우선(실행/발행/검수/업로드).
4. **모달 최소화:** 텍스트 기반 안내 우선. 모달 의존 금지.
5. **접근성:** 키보드 포커스 `:focus-visible` 유지. 단축키 도움말은 페이지 상단 `details.kbd-help`.

## Assumptions

- PHP SSR 구조 유지. 단일 공통 CSS(`gilaime_ui.css`)로 일관성 확보.
- Bootstrap 우선. 커스텀은 토큰/컴포넌트 SoT(03B·03C) 준수.

## Open Questions (확인 필요)

- 모바일 전용 UX 목표(3클릭 등) 측정 방법.
- 다국어/접근성 등급(WCAG) 목표 수준.
