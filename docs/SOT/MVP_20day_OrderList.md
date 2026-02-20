# SoT — MVP 20일 OrderList

## Purpose

Phase A 산출물 기반 Phase B 실행 순서·태스크·의존성. "Phase A LOCKED" 선언 후에만 Phase B 진입.

**Lock 조건:** Phase B 태스크·의존성·Lock 조건 명시, 실행 순서 무결.

---

## Phase A 완료 조건

- 아래 7종 산출물 생성·리뷰 완료:
  - Gilime_User_Wireframe_MVP_v2.md
  - Gilime_Admin_Wireframe_MVP_v2.md
  - Route_Scoring_Model.md, Route_Scoring_Simulation_Model_v1.md
  - PRD_MVP_20day.md
  - Gilime_Admin_ERD_MVP_v1.md
  - 11_API_V1_IMPL_PLAN.md
  - MVP_20day_OrderList.md (본 문서)
- **Phase A LOCKED** 선언 후 Phase B 진행

---

## Phase B — 구현 순서

| ID | 작업 | Depends On | 산출물 | 검증 |
|----|------|------------|--------|------|
| B1 | DB migration 적용 | A5 | sql/migrations/v0.8-xx_gilime_admin_core.sql | migration 실행, validate 0건 |
| B2 | Admin publish 검증 SQL | B1 | sql/validate/v0.8-xx_validate_publish_rules.sql | 4종 검증 쿼리 |
| B3 | API v1 라우팅·공통 응답 | A6 | public/api/index.php, ApiResponder, ApiV1Controller | /api/v1/*, ok/error 래퍼 |
| B4 | IssueService + Admin Issue CRUD·activate/deactivate | B1,B3 | IssueService.php | GET/POST, activate/deactivate curl |
| B5 | ShuttleService + Admin Shuttle CRUD·stops·activate/deactivate | B1,B3 | ShuttleService.php | stops 검증 400/200 |
| B6 | RouteService + 스코어링 v1 | A3,B1 | RouteService.php | Test Set A~D 일치 |
| B7 | GuidanceService·SubscriptionService 최소 | B3 | GuidanceService, SubscriptionService | start/get/reroute, subscriptions CRUD stub |
| B8 | 스코어링 검증 스크립트 | B6 | scripts/php/verify_scoring_model_v1.php | A~D 출력 = 정답표 |
| B9 | API·Admin 보호 (X-ADMIN-TOKEN) | B3 | middleware/controller | 403 when token missing |
| B10 | 통합 검증·문서 정리 | B1~B9 | curl 예시, OPS_DB_MIGRATIONS 등 | 응답 ≤2초, 체크리스트 |

---

## Phase C — 아젠다 통합 실행 (A+B+C)

기준: `docs/CHAT_EXPORTS/길라임_UIUX_아젠다별_실행정리_20260220.md`

| ID | 트랙 | 작업 | Depends On | 산출물 | 검증 |
|----|------|------|------------|--------|------|
| C1 | 공통 | 아젠다 정리본 확정 및 실행 범위 잠금 | Phase B | 아젠다 정리 문서, 본 OrderList | 결정/실행/보류 분리 |
| C2 | A(UI) | 홈 지도 UI 이슈 중심 탭/칩/시트 정합 | C1 | home.php, gilaime_ui.css, home_map.js | 홈 진입/탭 전환/드래그 스냅 정상 |
| C3 | A(UI) | SVG 아이콘 공통 규격 적용(상단 CTA/탭/하단내비) | C2 | gilaime_nav.svg, UI_SYSTEM/SVG_ICON_SYSTEM 문서 | 이모지 미사용, 정렬/가독성 확인 |
| C4 | B(Notice) | 공지/이벤트 DB 마이그레이션/검증/시드 | C1 | create_notices.sql, validate_notices.sql, notices_seed.sql | published+기간 노출 규칙 검증 |
| C5 | B(Notice) | 공지/이벤트 API 목록/상세 + 유저 화면 | C4 | v1 Notice 서비스, router, user/notices.php | 탭 필터/정렬/pinned/상세 확인 |
| C6 | C(Routing) | 이슈 가중치 정책셋(BLOCK/PENALTY/BOOST) 적용 | C1 | RouteService 확장 | issue_context_id 유/무 결과 차이 확인 |
| C7 | C(Routing) | 라우팅 검증 스크립트/케이스 보강 | C6 | verify_scoring_model_v1.php | 정책별 재현 가능한 출력 |
| C8 | 공통 | 문서/OPS 동기화 및 최종 점검 | C2~C7 | SOT/OPS 갱신 | lint/php/API 시나리오 통과 |

### Phase C 완료 조건
- C2~C8 산출물이 모두 생성되고 문서 링크가 유효하다.
- 홈 UI(A), 공지/이벤트(B), 라우팅 가중치(C) 각각 최소 1개 이상 검증 시나리오를 통과한다.
- 기존 Phase B 동작을 깨지 않고 통합 동작이 유지된다.

---

## 확인 필요 (PRD/OrderList 공유)

- 인증 방식 (User/Admin)
- 실시간 ETA 공급원
- 지도 타일·외부 라우팅 베이스 연동

---

## 연계 문서

- 플랜 원본: `.cursor/plans/길찾기_와이어프레임_회의_정리_및_실행_플랜_e09c9524.plan.md`
- [길찾기_와이어프레임_회의_아젠다별_정리](../CHAT_EXPORTS/길찾기_와이어프레임_회의_아젠다별_정리.md)
