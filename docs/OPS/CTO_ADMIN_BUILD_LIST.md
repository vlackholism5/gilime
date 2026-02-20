# CTO Admin Build 목록

**출처:** [CTO_ADMIN_QA_MEETING_MINUTES_20260219.md](CTO_ADMIN_QA_MEETING_MINUTES_20260219.md)  
**규칙:** Build 반영 시 아래 표의 확정된 행만 읽어 파일/위치별로 구현. 한 항목씩 구현 후 검증 방법으로 확인.

| ID | 내용(한 줄) | 파일/위치 | 검증 | 담당 |
|----|-------------|-----------|------|------|
| B1 | ADMIN_QA_GAP_LIST.md 신규 작성 — 갭·Minimal Fix·검증·Owner (회의록 §4 반영) | docs/OPS/ADMIN_QA_GAP_LIST.md | 문서 존재·표 항목과 회의록 4) 대응 | QA |
| B2 | doc.php 파싱 실패 시 error_code 표시 보강 — job_log failed·result_note 파싱 검토 및 insertFailedJobLog 모든 실패 경로 호출 확인 | public/admin/doc.php, public/admin/run_job.php | parse_status=failed 문서에서 last_parse_error_code 값 표시 | Backend |
| B3 | 알림 운영·알림 목록 출력 경로 h() 적용 및 입력 길이 제한(필요 시) | public/admin/alert_ops.php | title/본문에 script 입력 후 이스케이프 확인 | Backend |
| B4 | 운영 제어 "실패 상위 20건" empty state 문구를 "실패한 배달이 없습니다"로 변경 | public/admin/ops_control.php | failed 0건일 때 해당 문구 노출 | PM/QA |
| B5 | 운영 대시보드 또는 Admin 파이프라인 1페이지에 "리스크 대기 = pending 중 match_method like_prefix 또는 NULL" 1문장 추가 | docs/OPS/ADMIN_PIPELINE_ONE_PAGER.md 또는 public/admin/ops_dashboard.php | 문서 또는 화면 안내 존재 | Data/ETL |
| B6 | (본 Build 목록 유지) CTO_ADMIN_BUILD_LIST.md에 B1~B6 반영 완료 | docs/OPS/CTO_ADMIN_BUILD_LIST.md | 표에 B1~B6 행 존재 | CTO/Release |

---

**실행 순서:** B1 → B2 → B3 → B4 → B5 → B6. 각 Bn 구현·검증 후 Bn+1 진행.
