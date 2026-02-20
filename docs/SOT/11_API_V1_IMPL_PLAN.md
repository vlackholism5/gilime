# SoT 11 — API v1 구현 계획

## Purpose

Single source of truth for /api/v1 엔드포인트·공통 응답·Admin 보호·Publish 스펙. Phase B 구현 시 준수.

**Lock 조건:** activate/deactivate 엔드포인트·검증 규칙 확정, curl 시나리오 정의.

---

## 공통

- Base: `/api/v1`
- 포맷: JSON
- 응답 래퍼: ok, data, error, meta(trace_id, server_time)
- 에러 코드: VALIDATION_ERROR, NOT_FOUND 등

---

## User API

| Method | Path | 설명 |
|--------|------|------|
| GET | /places/suggest | 장소 자동완성 |
| GET, POST | /issues | 이슈 목록·상세 |
| POST | /routes/search | 경로 검색(스코어 v1 반영) |
| POST, GET | /guidance/* | 안내 시작·조회 |
| POST, GET, DELETE | /subscriptions | 구독 CRUD |

---

## Admin API

- POST /admin/issues (생성·수정)
- POST /admin/shuttles/routes, PUT .../stops (셔틀 노선·정류장)
- **Publish:** POST .../issues/{id}/activate, .../issues/{id}/deactivate  
  POST .../shuttles/routes/{id}/activate, .../shuttles/routes/{id}/deactivate
- 보호: X-ADMIN-TOKEN 필수, 미제공 시 403

---

## Publish 검증 규칙

- activate 전 서버 검증: 필수값·stop 무결성(연속·중복 없음)
- 검증 실패 시 400 + 메시지

---

## 응답 예 (경로 검색)

- 경로 목록 + 각 경로에 score, issue_impact 포함 (스코어 시뮬레이션 v1 준수)

---

## 연계 문서

- [09_API_CONTRACTS_G1](09_API_CONTRACTS_G1.md) — 기존 G1 계약
- [Route_Scoring_Model](Route_Scoring_Model.md), [Route_Scoring_Simulation_Model_v1](Route_Scoring_Simulation_Model_v1.md)
- [Gilime_Admin_ERD_MVP_v1](Gilime_Admin_ERD_MVP_v1.md)
