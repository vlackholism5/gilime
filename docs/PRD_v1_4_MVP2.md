# PRD — v1.4 MVP2 (GILIME One-Shot Expansion)

**상태:** 계획 전용. 구현은 다음 단계에서 진행.

---

## Problem

- 통근자에게 **노선·정류장·공지·알림**을 한 곳에서 제공하는 수요가 있음.
- 현재는 관리자(운영자)만 PDF 파싱·검수·승격 플로우를 사용하며, **일반 사용자용 화면과 구독/알림**이 없음.

---

## Scope (v1.4)

- **사용자 페이지:** 노선 검색·정류장 조회, 알림 구독(공휴/사고/이벤트 등), 공지 피드 열람.
- **기존 운영 플로우 유지:** SoT·게이트·매칭 로직 변경 없음. Admin은 기존 ops + read-only 대시보드 유지.
- **데이터:** 사용자·구독·알림 이벤트·발송 이력 저장. 노선/정류장은 기존 promote 결과(route_stop 등) 기반 조회.

---

## Non-goals (v1.4)

- OAuth / 소셜 로그인 (확인 필요: 추후 도입 여부).
- 2FA, 비밀번호 만료 정책.
- 실시간 푸시(알림은 배치/이메일 또는 인앱 피드 우선).
- 관리자 UI 대규모 리뉴얼.

---

## Personas

| Persona | 목표 | 주요 행동 |
|---------|------|------------|
| **Commuter user** | 내가 타는 노선·정류장·공지·알림 확인 | 노선 검색, 정류장 보기, 알림 구독, 공지 피드 보기 |
| **Admin operator** | PDF 파싱·후보 검수·승격·품질 관리 | 기존 doc/route_review/review_queue/ops_dashboard 활용, 변경 없음 |

---

## User features (v1.4)

1. **Search routes** — 노선 목록/검색, promote된 route_stop 기준.
2. **View stops** — 노선별 정류장 목록·순서 (SoT: 기존 스냅샷).
3. **Subscribe alerts** — 공휴/사고/이벤트 등 알림 유형별 구독 설정.
4. **View notice feed** — 공지·알림 이벤트 타임라인(피드).

Admin features: 기존 ops flow 유지, read-only 대시보드 유지. (변경 없음)

---

## Data requirements (SoT)

- **저장 필요:** user, user_sessions(또는 기존 세션 확장 — 확인 필요), subscriptions(구독 대상·유형), alert_events(이벤트 원본), alert_deliveries(발송 이력).
- **Derived/기존:** 노선·정류장 목록은 기존 route_stop·shuttle_stop_candidate·seoul_bus_stop_master 등 promote/매칭 결과에서 조회. SoT 규칙 변경 없음.

---

## Metrics

- **Activation:** 가입·최초 로그인·첫 노선 조회 수.
- **Alert opt-in:** 구독 수·유형별 구독률.
- **Admin throughput:** 기존 지표 유지(검수 건수, promote 건수 등). v1.4에서 추가 지표는 별도 정의(확인 필요).

---

*문서 버전: v1.4-00 (planning-only).*
