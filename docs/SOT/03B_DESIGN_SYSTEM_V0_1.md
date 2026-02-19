# SoT 03B — Design System v0.1

## Purpose

Minimal design system for MVP: typography(3), spacing/radius, and core tokens. No code; reference for 03C and implementation.

## Definitions

- **Token:** 재사용 가능한 디자인 변수(예: `--g-space-1`).
- **8px rhythm:** 간격은 8px 단위 우선.

## Typography (3 roles)

| Role | 용도 | 클래스/변수 |
|------|------|-------------|
| Page title | 페이지 제목 | `.g-page-head h1/h2`, `--g-fs-page-title` |
| Section title | 섹션 제목 | `h5/h6`, `--g-fs-section-title` |
| Body / Hint | 본문·보조 | `.g-text-body`, `.g-text-hint` / `--g-fs-body`, `--g-fs-hint` |

- 폰트 스택: Pretendard / Noto Sans KR / 맑은 고딕 / system-ui.

## Spacing

- `--g-space-1` = 8px (0.5rem)
- `--g-space-2` = 16px (1rem)
- `--g-space-3` = 24px (1.5rem)
- `--g-space-4` = 32px (2rem)
- Bootstrap 매핑: `mb-2`≈8px, `mb-3`≈16px, `mb-4`≈24px.

## Radius

- 카드/버튼: Bootstrap 기본 또는 `g-card` 등 공통 클래스. 구체 수치 확인 필요.

## Color (minimal)

- Primary CTA: `--gilaime-lime`, Hover: `--gilaime-lime-700`
- Border/Surface: `--gilaime-border`, white
- Text: `--gilaime-ink`, muted: `--gilaime-muted`
- 상태: draft(gray), published/shown/sent(lime-soft), pending(yellow-soft), failed(red-soft)

## Assumptions

- 구현은 `public/assets/css/gilaime_ui.css` 및 Bootstrap과 연동. 이 문서는 규격만 정의.
- 확장 시 `--g-space-0`(4px), `--g-space-5`(40px) 등 docs/ui 참고.

## Open Questions (확인 필요)

- Border-radius 토큰 명시값(숫자).
- 다크 모드·고대비 지원 범위.
