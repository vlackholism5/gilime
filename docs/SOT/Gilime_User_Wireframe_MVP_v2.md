# SoT — Gilime User 와이어프레임 MVP v2

## Purpose

Single source of truth for 길찾기 사용자 앱 화면 구조·상태·컴포넌트. 네이버지도 대중교통 UI 참조, MVP=버스/지하철/임시셔틀 경로+실시간 도착.

**Lock 조건:** UX 승인, 상태머신·오버레이 규칙 확정.

---

## IA (Information Architecture)

- **HOME:** 검색창, 최근검색, 지도뷰, 하단 네비
- **ROUTE_RESULT:** 경로 요약 바, 필터 탭, 정렬, 경로 카드 리스트, 안내 시작
- **GUIDANCE_MODE:** 안내 상태 바, 지도 내비, 현재 스텝 패널, 종료
- **STOP_DETAIL (바텀시트):** 정류장명, 정류장ID, 노선 리스트, 실시간 도착, 더보기

---

## 화면 정의

### 화면 1 — 홈

- 검색창 고정 상단
- 최근검색 삭제 가능
- 지도 현재 위치 중심

### 화면 2 — 경로 결과

- 출발→도착 표시
- 버스/지하철/환승 탭
- 오늘 출발 시각·정렬 옵션
- 경로 카드: 최적/시간/요금, 세그먼트, 바로 안내시작
- 상태: 선택됨, 실시간 지연, 여유, 임시셔틀 포함(보라 배지)

### 화면 3 — 정렬 옵션 (바텀시트)

- 최적경로 / 최소시간 / 최소환승 / 최소도보 / 빠른도착
- 계단회피 토글
- (차별화) 이슈 최소 영향순

### 화면 4 — 안내 중

- 지도 네비
- 현재 단계 패널
- 도보(점선) / 버스(실선) / 지하철 / 임시셔틀 시각 구분

### 화면 5 — 정류장 상세 (바텀시트)

- 정류장명, 정류장ID
- 노선·도착정보
- 더보기

---

## 주요 컴포넌트

| 컴포넌트 | 용도 |
|----------|------|
| RouteCard | 경로 요약·세그먼트·CTA |
| TransitBadge | 버스/지하철/셔틀 배지 |
| ArrivalTag | 실시간 도착 정보 |
| BottomSheet | 정렬 옵션, 정류장 상세 |
| MapOverlay | 경로·현재 단계 오버레이 |
| FloatingTimer | 재탐색/구독 관련 플로팅 |

---

## 상태 머신

- **IDLE** → **SEARCHING** → **ROUTE_LOADED** → **GUIDING** → **END**
- 확장: **ISSUE_DETECTED**, **REROUTE_AVAILABLE**, **SUBSCRIBED_ROUTE**, **SHUTTLE_ACTIVE**

---

## 데이터 흐름 (길라임 차별화)

USER_INPUT → ISSUE_CHECK → ROUTE_ENGINE(NORMAL_TRANSIT, SHUTTLE_OVERLAY) → SCORE_ENGINE(TIME, TRANSFER, ISSUE_IMPACT) → RESULT_RENDER

---

## 연계 문서

- [길찾기_와이어프레임_회의_아젠다별_정리](../CHAT_EXPORTS/길찾기_와이어프레임_회의_아젠다별_정리.md)
- [PRD_MVP_20day](PRD_MVP_20day.md)
- 지도 기반 확장: [UX_ROUTE_FINDER_MAP_BASED_v1](../ux/UX_ROUTE_FINDER_MAP_BASED_v1.md)
