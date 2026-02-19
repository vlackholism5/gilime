# PM Sync — 2/20 플랜 실행 (2026-02-12)

## Done
- Phase A: 기준선 고정 문서 (`PHASE_A_BASELINE_v1_7.md`) — MVP 범위/지도·경로 정의/증거 수집 템플릿
- Phase B: 이슈 기반 길찾기 — route_finder `issue_id` 지원, 이슈 컨텍스트 배너, 임시 셔틀 기본 체크, 셔틀 구간 뱃지, 마이노선→경로 안내 링크
- Phase C: 품질 기준선 경고 — doc.php LOW+NONE 비중 30% 초과 시 경고 문구
- Phase D: PRD/ERD/UML/v1.7 gate 문서 갱신
- Phase E: SMOKE_RUNBOOK A6(이슈 기반 길찾기) 추가, gate_20 판정 갱신

## Evidence
- 코드: `public/user/route_finder.php`, `public/user/my_routes.php`, `public/admin/doc.php`
- 문서: `docs/operations/PHASE_A_BASELINE_v1_7.md`, `docs/releases/v1.7/RELEASE_GATE.md`, `docs/releases/v1.7/v1.7_E2E.md`, `docs/references/UML_v1_7_MVP.md`
- 스모크: `docs/operations/SMOKE_RUNBOOK_2026_02_20.md`

## Risk
- route_stop_active=0 환경에서는 승격 1회 수동 수행 후 사용자 경로안내 확인 필요

## Next(24h)
- 수동 완주 시나리오 1회 실행 (SMOKE_RUNBOOK A1~A6)
- 실패 시나리오 1회 실행 (B1, B2)
- 최종 증거 패키지(스크린샷+검증쿼리) PM 전달
