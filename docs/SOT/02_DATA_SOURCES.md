# SoT 02 — Data Sources

## Purpose

Single source of truth for data categories, sources, refresh cadence, and license/rights. Supports MVP planning and compliance checks.

## Definitions

- **Refresh cadence:** 데이터 갱신 주기(수동/일회/일별 등).
- **License/rights:** 이용 조건·저작권·재배포 가능 여부.

## Data categories (A–F)

| Category | Description | Source | Refresh cadence | License/rights |
|----------|-------------|--------|-----------------|----------------|
| **A** | 정류장 마스터 (서울) | seoul_bus_stop_master 등 CSV/공개 데이터 | 수동 import 또는 확인 필요 | 확인 필요 |
| **B** | 노선 마스터 (서울) | seoul_bus_route_master, seoul_bus_route_stop_master | 수동 import 또는 확인 필요 | 확인 필요 |
| **C** | 문서/PDF (업로드) | 관리자 업로드 → shuttle_source_doc | 수동 업로드 | 내부 생성·업로드 |
| **D** | 구독·알림 이벤트·배달 | app_subscriptions, app_alert_events, app_alert_deliveries | 배치/스크립트·수동 발행 | 내부 |
| **E** | 파싱/검수/승격 결과 | shuttle_stop_candidate, shuttle_route_stop, shuttle_doc_job_log, shuttle_parse_metrics | Job 실행 시 | 내부 |
| **F** | 이슈/운영/뉴스/수동 탐지 | 이슈·공지·수동 등록 이벤트 (app_alert_events 등) | 수동·배치·확인 필요 | 확인 필요 |

### Category F (issue/ops/news/manual detection)

- **역할:** 이슈·운영 공지·뉴스성 알림·수동 탐지 결과를 알림 이벤트로 관리.
- **소스:** 수동 작성(alert_ops), 스텁/메트릭 기반 생성(run_alert_ingest_stub 등), 외부 연동(미구현 — 확인 필요).
- **갱신:** 수동 발행 또는 배치. 실시간 연동 여부 확인 필요.

## Assumptions

- A·B는 서울 공개 데이터 기반. 타 지역/타 소스는 비범위.
- C는 관리자 업로드에 한함; 자동 수집 파이프라인은 비범위(설계만 유지).
- D·E는 앱 내 DB SoT 유지. F의 외부 뉴스/이슈 소스는 미정.

## Open Questions (확인 필요)

- A·B 공개 데이터의 공식 라이선스·출처 문서.
- F의 "뉴스/이슈" 자동 수집 소스 및 라이선스.
- 정류장/노선 마스터 갱신 주기(일별/주별/수동) 확정.
