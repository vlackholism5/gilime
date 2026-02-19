# SoT 03C — Components Spec (MVP minimal)

## Purpose

Minimal component specification for MVP: buttons, inputs, lists, cards, UI states. Keeps design system implementable without scope creep.

## Definitions

- **g-card:** 공통 카드 래퍼. `card g-card`.
- **g-table:** 공통 테이블. `table table-hover align-middle g-table`.

## Typography (3)

- Page title: `.g-page-head h1/h2`
- Section title: `h5/h6`
- Body / Hint: `.g-text-body`, `.g-text-hint` 또는 `.text-muted.g.small`

(상세는 03B 참고.)

## Spacing / Radius

- 8px rhythm: `--g-space-1`~`4`. Radius: Bootstrap 기본 또는 별도 토큰(확인 필요).

## Buttons

- Primary: `btn btn-gilaime-primary`
- Secondary: `btn btn-outline-secondary btn-sm`
- 동사형 라벨(실행/발행/검수/업로드/적용).

## Inputs

- `form-control-sm`, `form-select-sm`. Inline: `g-form-inline`.

## Lists

- 목록은 테이블 또는 카드 리스트. 테이블: `g-table`, 데이터 많을 때 `g-table-dense`, 좁은 화면 `table-responsive`.
- 헤더 nowrap, 본문 `word-break: keep-all`, hover/striped.

## Cards

- `card g-card`. 카드 내부 제목/본문/힌트 계층 유지.

## UI states

| State | 용도 | Badge/표시 |
|-------|------|------------|
| draft | 미발행·작성 중 | gray, `badge-draft` |
| published / shown / sent | 발행·노출·전달 완료 | lime-soft, `badge-published` 등 |
| pending | 대기·전달 대기 | yellow-soft, `badge-pending` |
| failed | 실패 | red-soft, `badge-failed` |

- Flash: Bootstrap `alert-success` / `alert-warning` / `alert-danger` / `alert-info`.

## Assumptions

- 컴포넌트는 Bootstrap + gilaime_ui.css 조합. 신규 컴포넌트 추가 시 이 스펙과 03B 토큰 준수.
- 모달 의존 금지; 텍스트 안내·페이지 전환 우선.

## Open Questions (확인 필요)

- 리스트 "카드 리스트" 레이아웃 상세(그리드/단일 열).
- Badge 클래스명과 CSS 매핑 일치 여부 검증.
