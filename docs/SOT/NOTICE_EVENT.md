# SOT — NOTICE_EVENT (MVP)

## 목적
- 유저에게 공지/이벤트를 읽기 전용으로 노출한다.
- 운영팀이 DB 기반으로 게시 상태와 기간을 관리한다.

## DB 스키마
- Migration: `sql/migrations/v0.8-12_create_notices.sql`
- 테이블: `notices`
  - `category`: `notice|event`
  - `label`: `공지|안내|이벤트`
  - `status`: `draft|published|archived`
  - `is_pinned`: 고정 노출 여부
  - `starts_at`, `ends_at`, `published_at`: 노출 기간/게시 시각

## 노출 규칙
- 기본(active) 노출 조건:
  - `status = 'published'`
  - `starts_at IS NULL OR starts_at <= NOW()`
  - `ends_at IS NULL OR NOW() <= ends_at`
- 정렬:
  - `is_pinned DESC, published_at DESC, id DESC`

## API 계약 (v1)
- 목록: `GET /api/index.php?path=v1/notices&category=notice|event&status=active|all&page=1&size=20`
- 상세: `GET /api/index.php?path=v1/notices/{id}`
- 구현:
  - `app/inc/api/v1/NoticeService.php`
  - `app/inc/api/v1/router.php` (`v1/notices*`)

## 유저 화면
- 페이지: `public/user/notices.php`
- 탭:
  - `공지사항` (`tab=notice`)
  - `이벤트` (`tab=event`)
- 상태:
  - Empty: 데이터 없음 안내
  - List: 아코디언 목록/상세
  - Ended: 이벤트 종료 상태 표시(`is_ended`)

## 검증
- Validation SQL: `sql/validate/v0.8-12_validate_notices.sql`
- Seed: `sql/seed/notices_seed.sql`
- 확인 포인트:
  - 탭별 데이터 분리
  - pinned 우선 정렬
  - active/all 토글 동작
