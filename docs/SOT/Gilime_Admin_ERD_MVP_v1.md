# SoT — Gilime Admin ERD MVP v1

## Purpose

Single source of truth for 이슈·셔틀 관리용 DB 스키마. migration·검증 SQL 기준.

**Lock 조건:** issues / shuttle_routes / shuttle_stops 정의 확정, FK·UNIQUE·검증 쿼리 일치.

---

## 테이블 요약

### issues

- id, title, severity, status (= draft | active | ended)
- start_at, end_at (이슈 기간)

### issue_lines

- issue_id, line_code
- UNIQUE(issue_id, line_code)

### issue_modes

- issue_id, mode
- UNIQUE(issue_id, mode)

### shuttle_routes

- id, issue_id, route_name, headway_min, service_hours, status
- UNIQUE(issue_id, route_name)

### shuttle_stops

- shuttle_route_id, stop_order, stop_id, stop_name, lat, lng
- UNIQUE(shuttle_route_id, stop_order)
- UNIQUE(shuttle_route_id, stop_id)

---

## 검증 SQL (요구사항)

- stop_order 연속성
- stop_id 중복 없음 (route 내)
- active 이슈 기간·필수 컬럼 검증

---

## 연계 문서

- [Gilime_Admin_Wireframe_MVP_v2](Gilime_Admin_Wireframe_MVP_v2.md)
- [11_API_V1_IMPL_PLAN](11_API_V1_IMPL_PLAN.md)
- Phase B: sql/migrations/v0.8-xx_gilime_admin_core.sql, sql/validate/v0.8-xx_validate_publish_rules.sql
