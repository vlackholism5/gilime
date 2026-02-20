# SoT — Gilime Admin 와이어프레임 MVP v2

## Purpose

Single source of truth for 관리자 이슈·임시셔틀 등록·수정 UI 및 검증 규칙. Stop Sequence Builder·Publish 토글 포함.

**Lock 조건:** StopSequenceBuilder 검증 규칙·Publish UI 합의, DB 제약과 1:1 매핑.

---

## 목적

- 이슈/임시셔틀 등록·수정
- 정류장 순서 무결성 보장
- Publish(Activate/Deactivate) 워크플로

---

## 이슈 CRUD

- 제목, 심각도
- 시작/종료 시각
- 영향 노선·모드
- 지도 선택(선택)
- 셔틀 노선 추가: 정류장 순서, 배차

---

## Stop Sequence Builder

- **2패널:** 검색 패널 / 순서 패널
- 드래그 재정렬
- **검증 규칙:**
  - stop_order 연속 (1, 2, 3, …)
  - 동일 route 내 stop_id 중복 금지
  - 최소 2정류장

---

## Publish UI

- **Activate / Deactivate** 버튼
- ConfirmModal (서버 재검증 안내)
- 서버 검증: 필수값·stop 무결성 통과 후만 활성화

---

## DB 요약 (상세는 ERD SoT 참조)

- issues(id, title, start_at, end_at, affected_lines)
- shuttle_routes(issue_id, route_name)
- shuttle_stops(route_id, stop_order, stop_id)

---

## 연계 문서

- [Gilime_Admin_ERD_MVP_v1](Gilime_Admin_ERD_MVP_v1.md)
- [11_API_V1_IMPL_PLAN](11_API_V1_IMPL_PLAN.md)
- [길찾기_와이어프레임_회의_아젠다별_정리](../CHAT_EXPORTS/길찾기_와이어프레임_회의_아젠다별_정리.md)
