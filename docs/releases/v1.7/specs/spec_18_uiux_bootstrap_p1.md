# spec_18_uiux_bootstrap_p1

## 목표
- P1 화면 5개에서 inline/style 블록 제거
- Bootstrap 컴포넌트 중심 정렬
- 기존 로직/DB 영향 0 유지

## 대상 파일
- `public/user/home.php`
- `public/user/routes.php`
- `public/user/alerts.php`
- `public/admin/index.php`
- `public/admin/upload_pdf.php`
- `public/assets/css/gilaime_ui.css`

## 규칙
- Bootstrap CDN 연결
- `container-fluid`, `card`, `table`, `btn`, `alert` 표준 클래스 우선 사용
- 공통 커스텀은 `gilaime_ui.css` 최소 보정만 사용

## 완료 기준
- 대상 5개 페이지에서 `<style>` 블록 없음
- 대상 5개 페이지에서 `style=""` 인라인 없음
- `php -l` 전부 통과
