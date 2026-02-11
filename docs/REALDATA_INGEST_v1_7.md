# Real-data ingest 1종 (v1.7-11)

실데이터 ingest 최소 구현: SoT(DB) 기반으로 app_alert_events 생성.

## SoT

- shuttle_doc_job_log: PARSE_MATCH success, updated_at 기준 최근 since_minutes
- shuttle_parse_metrics: parse_job_id별 route_label, none_matched_cnt, low_confidence_cnt, cand_total

## 동작

- scripts/run_alert_ingest_real_metrics.php
- 옵션: --since_minutes=1440, --limit=200
- (doc, job, route)당 1건: event_type=update, title=[Metrics] Review needed, body=JSON
- ref_type=route, ref_id=source_doc_id, route_label=route_label
- content_hash = SHA256(metrics|ref_id|route_label|job_id) 로 idempotent
- published_at=NOW(), INSERT IGNORE
- 출력: inserted=N, skipped_duplicate=M

## 확인 필요

- shuttle_parse_metrics 컬럼명은 run_job.php 기준. 변경 시 코드 반영 필요.

## Non-goals

- 외부 공공데이터 API, PDF 파서, 정교한 임계치. admin 승인 연동(이번 버전은 생성만).
