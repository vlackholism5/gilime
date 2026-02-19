# Admin 권한 설계 (Phase 2 초안)

## 목적
- CTO 검토용: role 기반 접근 제어, 민감 작업(승격·발행) 추적 가능 설계
- 현재 `require_admin()`는 user_id 존재 여부만 확인

## 1. Role 정의 (초안)

| Role | 설명 | 허용 작업 |
|------|------|----------|
| admin | 전체 관리 | 문서 업로드, 파싱, 검수, 승격, 알림 발행, 운영 대시보드 |
| approver | 승인자 | 노선 검수, 승격, 알림 발행(선택) |
| viewer | 조회 전용 | 문서/대기열/운영 요약 조회 |

## 2. 권한 매트릭스

| 페이지/액션 | admin | approver | viewer |
|-------------|-------|----------|--------|
| doc.php (조회) | o | o | o |
| run_job.php (파싱) | o | - | - |
| route_review (승격) | o | o | - |
| alert_ops (발행) | o | o | - |
| upload_pdf | o | - | - |
| ops_control | o | - | - |

## 3. 구현 방향 (Phase 2)

### auth.php 확장
- `require_admin(?string $minRole = null)`: role 파라미터 옵션
- `get_current_user_role(): string`: session 또는 DB에서 role 조회
- role 저장: `app_user` 테이블 또는 session

### 공통 헤더
- role에 따라 메뉴 링크 필터링
- approver: "검수 대기열", "노선 검수", "알림 운영" 등
- viewer: "문서 허브", "운영 요약" (조회만)

### 감사
- 승격(PROMOTE): `shuttle_doc_job_log`에 `user_id`, `timestamp` 기록 (기존)
- 발행(Publish): `app_alert_events` 또는 이벤트 로그에 `actor` 기록
- trace_id로 요청별 추적

## 4. CTO 검토 항목
- [ ] Role 저장소 (DB vs session vs 외부 IdP)
- [ ] 승격·발행 시 감사 로그 스키마
- [ ] role 변경 시 기존 세션 무효화 정책
