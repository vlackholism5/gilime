# spec_19_uiux_bootstrap_p2

## 목표
- 운영핵심 4개 화면을 Bootstrap 기준으로 통일
- 액션 우선 UI(발행/검수/재실행) 가독성 강화

## 대상 파일
- `public/admin/alert_ops.php`
- `public/admin/ops_summary.php`
- `public/admin/ops_control.php`
- `public/admin/doc.php`

## 작업 원칙
- 핵심 로직 변경 금지 (렌더링/클래스 정리만)
- 상태 배지/버튼 규칙 통일
- 표는 `table-responsive` + `table-hover` 기본

## 완료 기준
- 대상 파일 inline/style 감소
- 운영 시나리오(발행/문서실행/요약조회) 스모크 통과
