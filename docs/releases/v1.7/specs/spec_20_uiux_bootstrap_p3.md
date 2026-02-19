# spec_20_uiux_bootstrap_p3

## 목표
- 고복잡도 검수화면 Bootstrap 정렬
- 유지보수성 우선으로 단계별 정리

## 대상 파일
- `public/admin/review_queue.php`
- `public/admin/route_review.php`

## 작업 원칙
- 대규모 전면 재작성 금지
- 레이아웃 컨테이너/표/필터 구역부터 순차 정리
- 회귀 리스크를 낮추기 위해 기능 코드는 건드리지 않음

## 완료 기준
- 화면 구조 통일성 확보
- 스모크 체크리스트 기준 회귀 없음
