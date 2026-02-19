# 알림 vs 이슈 매핑 (v1.8)

## 1. 역할 정의

| 구분 | 목적 | 페이지 | 대상 사용자 |
|------|------|--------|-------------|
| **마이노선** | 구독·알림 통합 랜딩 | public/user/my_routes.php | 노선 구독 관리 + 구독 노선 알림 진입 |
| **노선 구독/해제** | 구독 관리 | public/user/routes.php | 알림 받을 노선 구독·해제 |
| **구독 노선 알림** | 구독 노선 변경 알림 | public/user/alerts.php | **사용자용** — 구독 노선 알림, 검수 링크 Admin 연동 |
| **이슈** | 긴급 상황·길찾기 중심 | public/user/issues.php, issue.php | 경로 찾기 맥락, 이슈 기반 길찾기 CTA |

- **알림**은 관리자 전용이 아님. 일반 사용자가 구독 노선 알림을 보는 페이지.

- 둘 다 **app_alert_events** 단일 SoT 사용
- UI·필터·CTA만 다름

---

## 2. event_type 매핑표

### 2.1 DB event_type (실제 값)

| event_type | 출처 | 용도 |
|------------|------|------|
| strike | run_alert_ingest_stub | 파업/Strike |
| event | run_alert_ingest_stub | 이벤트/행사 |
| update | run_alert_ingest_real_metrics, run_alert_generate_from_metrics | [Metrics] Review needed, 공지 등 |

### 2.2 알림(alerts.php) 필터

| 필터 | event_type |
|------|------------|
| 전체 | (필터 없음) |
| 파업 | strike |
| 이벤트 | event |
| 업데이트 | update |

### 2.3 이슈(issues.php) 필터

| 필터 | event_type |
|------|------------|
| 전체 | (필터 없음) |
| 긴급 | strike |
| 운행중단 | strike |
| 행사 | event |
| 공지 | update |

### 2.4 영향도(Impact)

| event_type | 영향도 |
|------------|--------|
| strike | High |
| event | Medium |
| update | Medium |

---

## 3. 사용 가이드

| 상황 | 이동 경로 |
|------|-----------|
| 노선 구독·알림 | 네비 "마이노선" → my_routes.php |
| 노선 구독/해제 | 마이노선 → [노선 구독 관리] → routes.php |
| 구독 노선 알림 | 마이노선 → [구독 노선 알림 보기] → alerts.php |
| 구독 노선만 보기 | alerts.php → "구독 노선만: 켜기" |
| Admin 검수 | alerts 테이블 "검수 보기" 또는 이슈 상세 "검수 보기" |
| 긴급 이슈 Top3 | 홈 → 긴급 이슈 Top3 카드 |
| 전체 이슈·필터 | 네비 "이슈" 또는 홈 [전체 이슈 보기] → issues.php |
| 이슈 기반 길찾기 | 이슈 카드/상세 → [이슈 기반 길찾기] → route_finder.php |

---

## 4. 구현 참조

- 매핑 로직: app/inc/alert/alert_event_type.php
  - `filter_to_event_types($filter)`: 이슈 필터(UI) → DB event_type 배열
  - `event_type_to_filter($eventType)`: DB event_type → 이슈 필터 표시명
- delivery 기록: alerts.php, issues.php 모두 `record_alert_delivery` 호출
- 검수 링크: alerts.php, issue.php 동일 로직 (ref_id + route_label → route_review/doc)

---

*문서 버전: v1.8. 2026-02 기준.*
