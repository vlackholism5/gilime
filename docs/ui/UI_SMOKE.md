# UI_SMOKE

## 8-Step 수동 스모크
1. 관리자 로그인 후 `public/admin/index.php` 진입
2. `public/admin/doc.php?id=1` 진입 후 카드/표/버튼 가독성 확인
3. `public/admin/alert_ops.php`에서 필터/발행 버튼 렌더링 확인
4. `public/admin/ops_summary.php`에서 섹션 카드/표 hover 확인
5. `public/admin/ops_control.php`에서 상태표/빠른 링크 확인
6. `public/admin/upload_pdf.php`에서 업로드 폼 정렬 확인
7. `public/user/home.php`, `routes.php`, `alerts.php` 순차 확인
8. 각 페이지에서 Ctrl+F5 후 스타일 유지되는지 재확인

## 회귀 리스크 포인트
- `form` inline 렌더링 깨짐 여부
- 긴 테이블의 `table-responsive` 누락 여부
- 배지 상태 클래스 미매핑
- 플래시 메시지 색상/가독성 저하
- bootstrap 클래스 충돌로 버튼 크기 불일치

## 추가 품질 체크 (타이포/간격/모바일)
- 제목/섹션/힌트가 역할별로 크기와 톤이 구분되는지
- 섹션 간격이 8px 리듬(`mb-3`, `mb-4`)으로 일관적인지
- `alerts.php` 표에서 날짜/검수링크 열이 모바일에서 과도하게 줄바꿈되지 않는지
