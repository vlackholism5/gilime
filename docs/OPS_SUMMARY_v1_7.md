# Ops Summary (v1.7-09)

운영자가 한 화면에서 approvals / events / deliveries 요약과 outbound 스텁 실행 안내를 보는 페이지.

## 섹션

1. **Approvals** — app_alert_approvals 최근 20. event_id → alert_ops.php, alert_event_audit.php 링크.
2. **Events** — app_alert_events 최근 50. draft_cnt / published_cnt 표시. event_id → Alert Ops 링크.
3. **Deliveries** — 상태별 카운트 + 최근 20. event_id → Alert Audit 링크.
4. **Outbound 안내** — `php scripts/run_delivery_outbound_stub.php --limit=200` 실행 안내.

## 진입

- public/admin/index.php 상단에 "Ops Summary" 링크 추가. public/admin/ops_summary.php.

## Non-goals

- 대시보드 시각화(차트/그래프) 없음. 권한 세분화 없음.
