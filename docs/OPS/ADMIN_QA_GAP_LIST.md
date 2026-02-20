# Admin QA 갭 리스트

**출처:** [CTO_ADMIN_QA_MEETING_MINUTES_20260219.md](CTO_ADMIN_QA_MEETING_MINUTES_20260219.md) §4 GAP LIST 요약.  
관리자 페이지 QA 검수·검증 방법·Minimal Fix·Owner 정리.

---

## GAP LIST (Priority / Gap / Minimal Fix / Verification / Owner)

| Priority | Gap | Minimal Fix | Verification | Owner |
|----------|-----|-------------|--------------|-------|
| **P0** | 문서 파싱 실패 시 last_parse_error_code/last_parse_duration_ms가 "-"로만 표시됨 | 실패 분기 전부에서 insertFailedJobLog 호출 및 result_note에 error_code=XXX·duration_ms(가능 시) 포함. doc.php 표시 로직이 해당 필드 파싱하는지 확인. | parse_status=failed인 문서에서 error_code 값 표시 확인. | Backend |
| **P0** | 알림 운영·알림 목록에서 사용자 입력(title, 본문, route_label 등) XSS/레이아웃 위험 | 출력 경로 전부 htmlspecialchars(h()) 적용. 입력 길이/형식 검증(필요 시 API와 동일 1~60자 등). | title/본문에 `<script>` 입력 후 저장·목록/상세에서 이스케이프 확인. | Backend, QA |
| **P1** | 운영 대시보드 "대기 건수"와 "리스크 대기 건수"가 전부 동일하게 보이는 이유 미문서화 | 집계 정의(리스크 = pending 중 match_method like_prefix 또는 NULL)를 ops_dashboard 또는 ADMIN 파이프라인 1페이지에 1문장 추가. | 문서 존재·SQL로 pending match_method 분포 확인. | Data/ETL |
| **P1** | 운영 제어 "실패 상위 20건" empty state가 "데이터가 없습니다"만 표시 | "실패한 배달이 없습니다" 등 의도 명확 문구로 변경. | 화면 확인. | PM/QA |
| **P1** | 유저 이슈 화면에 [Metrics] Review needed 등 update 타입 알림 본문이 JSON 그대로 노출 | 운영용 메시지와 유저용 구분: 본문 마스킹 또는 "상세는 관리자에서 확인" 등 정책 1문장 문서화 후 필요 시 표시 로직 조정. | 이슈 목록/상세에서 update 타입 본문 확인. | PM/Backend |
| **P2** | 관리자 QA 갭 리스트·검증 방법 통합 문서 없음 | ADMIN_QA_GAP_LIST.md 신규 작성(갭·Minimal Fix·검증·Owner). | 문서 존재·회의 GAP과 링크. | QA |
| **P2** | 알림 목록 Pagination v1.6-06 "확인 필요" 문구 | 요구사항 확인 후 완료 시 문구 제거 또는 버전 고정. | ADMIN_QA_GAP_LIST 또는 스펙 반영. | QA, Release |

---

**Build 반영:** 위 P0/P1 갭에 대응하는 작업은 [CTO_ADMIN_BUILD_LIST.md](CTO_ADMIN_BUILD_LIST.md) B1~B6 순서로 구현·검증함.
