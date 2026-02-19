# v1.4-08: run_alert_generate_from_metrics

## 실행 방법

1. **선행:** `sql/releases/v1.4/schema/v1.4-07_route_label.sql` 실행 (app_alert_events.route_label 컬럼 추가).
2. 프로젝트 루트에서:
   - `php scripts/php/run_alert_generate_from_metrics.php`
   - Windows(XAMPP) PATH 미설정 시: `C:\xampp\php\php.exe scripts\run_alert_generate_from_metrics.php`
3. 출력: `run_alert_generate_from_metrics: inserted N event(s).`

## 생성 규칙

- **소스:** shuttle_parse_metrics + shuttle_doc_job_log. doc별 최신 PARSE_MATCH job과 직전 job 비교.
- **규칙(결정론):**
  - 직전 대비 `none_matched_cnt` 증가 → `event_type=update`, 제목 "NONE 증가", body에 doc_id/route_label/delta(prev/cur) JSON.
  - 직전 대비 `low_confidence_cnt` 증가 → `event_type=update`, 제목 "LOW 증가", body에 doc_id/route_label/delta JSON.
- **idempotent:** content_hash로 동일 (doc, route, kind, latest_job_id) 중복 삽입 방지. 재실행 시 증가가 없으면 0건 삽입.
- **설정:** ref_type='route', ref_id=doc_id, route_label=route_label (alerts 필터·Review 링크에 사용).
