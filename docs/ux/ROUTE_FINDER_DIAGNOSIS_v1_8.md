# 길찾기 동작 여부 진단 — 멀티롤 회의 (v1.8)

## 0. CTO 관점 멀티롤 회의 요약 (2026-02-12)

### CTO 결론

| 질문 | CTO 판단 |
|------|----------|
| **길찾기 미동작 원인** | **원인 4 우선** — 정류장은 resolve되나, **동일 노선 상 순차 경유**가 없음 |
| 로직/구현 결함? | **아니오** — 파이프라인 정상 |
| 데이터 문제? | **일부** — stop_id 매핑·노선-정류장 Coverage 검증 필요 |
| 긴급 조치 | 1) 디버그 로그 추가 2) stop_id 매칭률 점검 3) 샘플 경로 검증 |

### 실패 시나리오 2가지

| 시나리오 | 조건 | 사용자 경험 |
|----------|------|-------------|
| **A: resolve 실패** | 출발/도착 정류장명이 stop_master에 없음 | "출발지/도착지를 정류장으로 찾을 수 없습니다" |
| **B: search 0건** | resolve 성공, 동일 노선 상 from→to 순차 경유 없음 | "경로를 찾을 수 없습니다" (현재 이미지) |

**이미지 입력("123전자타운.2001아울렛" → "63빌딩.한강유람선")** → 시나리오 B.  
정류장은 존재하나, 한 노선이 두 정류장을 **출발→도착 순서**로 지나지 않음.

---

## 1. 질문 정리

| 질문 | 핵심 |
|------|------|
| 길찾기가 안 되는 이유? | 로직 없음? / 미구현? / API 필요? |
| 정류장ID + 공공데이터로 가능? | 네이버·카카오 API 없이? |
| 네이버·카카오 API가 필수인가? | — |

---

## 2. 현재 구현 상태

### 2.1 로직 — 이미 구현됨

```
[사용자 입력] "문래역", "강남역"
    ↓
route_finder_resolve_stop() → seoul_bus_stop_master에서 stop_id 조회
    ↓
route_finder_search() → seoul_bus_route_stop_master에서 출발·도착을 지나는 노선 조회
    ↓
[경로 결과] 버스 N건, 임시셔틀 N건
```

**파일:** `app/inc/route/route_finder.php`, `public/user/route_finder.php`  
**테이블:** `seoul_bus_stop_master`, `seoul_bus_route_master`, `seoul_bus_route_stop_master`, `shuttle_temp_route`, `shuttle_temp_route_stop`

### 2.2 네이버·카카오 API — 불필요

현재 구조는 **정류장ID + 서울시 공공데이터**만 사용한다.  
네이버·카카오 Map API는 **지도 표시**(Phase 2)에만 필요하고, 경로 조회 로직에는 사용하지 않는다.

---

## 3. 길찾기가 안 되는 원인 후보

| 원인 | 설명 | 확인 방법 | CTO 우선순위 |
|------|------|----------|--------------|
| **1. 데이터 미적재** | `seoul_bus_*` 테이블이 비어 있음 | `SELECT COUNT(*) FROM seoul_bus_stop_master` 등 | P0 |
| **2. 정류장명 불일치** | "문래역" vs "문래역1번출구" 등 형식 차이 | `route_finder_resolve_stop`의 exact/LIKE 매칭 실패 | P1 |
| **3. 노선-정류장 stop_id 부재** | route_stop_master에 stop_id NULL 또는 미매칭 | `SELECT COUNT(*) FROM seoul_bus_route_stop_master WHERE stop_id IS NULL` | P0 |
| **4. 경로 없음(노선 순차 미만족)** | 동일 노선 상 출발→도착 순서로 지나는 노선 없음 | `route_finder_search` 결과 0건 | P1 |

**CTO 정리:** 원인 4는 "데이터 문제"가 아니라 **실제 경로 부재**를 의미.  
같은 노선이 두 정류장을 f.seq < t.seq 순으로 지나야 함.  
데이터는 있지만 해당 구간 직통 노선이 없으면 0건이 정상.

---

## 4. 멀티롤 회의 결론

### 4.1 Product

| 항목 | 판단 |
|------|------|
| 로직 부재? | **아니오** — 이미 구현됨 |
| 미구현? | **아니오** — route_finder.php 동작 중 |
| API 필수? | **아니오** — 공공데이터 DB 적재만으로 가능 |
| 정류장ID + 공공데이터 | **가능** — 현재 설계가 이미 이 방식 |

### 4.2 Tech

| 항목 | 판단 |
|------|------|
| 데이터 적재 | `import_seoul_bus_stop_master_full.php`, `import_seoul_bus_route_master_full.php`, `import_seoul_bus_route_stop_master_full.php` 실행 필요 |
| 데이터 출처 | 서울시 공공데이터(공공데이터포털, 서울 열린데이터광장) CSV 다운로드 |
| 정류장명 검색 | exact → LIKE prefix 순서. "문래역1번출구 19318" 형식 지원 시 별도 파싱 필요 |
| 네이버/카카오 API | 지도 표시·geocoding(주소→좌표)에만 필요. 경로 조회에는 불필요 |

### 4.3 Design / UX

| 항목 | 판단 |
|------|------|
| 검색 UX | "문래역" 입력 시 "문래역1번출구", "문래역2번출구" 등 다중 후보 표시 → [선택] 플로우 (Phase 2) |
| 정류장ID 표시 | 네이버 지도처럼 "문래역1번출구 19318" 형식 노출 시 사용자 혼란 감소 |

---

## 5. CTO 팀 권장 조치

### 5.0 즉시 진단 (CTO 체크리스트)

**PHP가 PATH에 없을 때 (Windows):** `scripts/ps1/run_route_scripts.ps1` 사용

```powershell
.\scripts\run_route_scripts.ps1 check      # 테이블 건수
.\scripts\run_route_scripts.ps1 sample     # 샘플 정류장
.\scripts\run_route_scripts.ps1 diagnose "출발지" "도착지"  # 구간 진단
.\scripts\run_route_scripts.ps1 import     # CSV import
```

**PHP가 PATH에 있을 때:**

```bash
php scripts/php/check_route_public_data_counts.php
php scripts/php/list_route_finder_sample_stops.php
php scripts/php/diagnose_route_finder.php "출발지" "도착지"
```

| 체크 | 기대값 | 실패 시 |
|------|--------|---------|
| seoul_bus_stop_master | > 0 | CSV import |
| seoul_bus_route_stop_master | > 0 | CSV import |
| stop_id NULL 비율 | < 5% | CSV 정류장ID 컬럼 확인 |
| 샘플 구간 경로 1건 이상 | 있음 | 해당 구간 직통 노선 없음(정상) |

### 5.1 데이터 적재 (최우선)

```bash
# 1) CSV 다운로드 (공공데이터포털 / 서울 열린데이터광장)
#    - 서울시_정류장마스터_정보.csv → data/inbound/seoul/bus/stop_master/
#    - 서울시_노선마스터_정보.csv → data/inbound/seoul/bus/route_master/
#    - 서울시 노선 정류장마스터 정보.csv → data/inbound/seoul/bus/route_stop_master/

# 2) Import 실행
php scripts/php/import_seoul_bus_stop_master_full.php
php scripts/php/import_seoul_bus_route_master_full.php
php scripts/php/import_seoul_bus_route_stop_master_full.php
```

### 5.2 정류장 검색 확장 (선택)

- ~~`route_finder_resolve_stop`에서 다중 후보 반환~~ → **적용:** exact → prefix → **contains**(LIKE %input%) 추가
- "문래역" 검색 시 "문래역1번출구", "문래역2번출구" 등 리스트 + [선택] UI — Phase 2
- 정류장ID(19318)로 직접 검색 가능하도록 확장 — Phase 2

### 5.3 운영 점검 — 적용됨

- `check_route_public_data_counts.php`: stop_id NULL 건수·비율 출력
- Admin 운영 요약(ops_summary): seoul_bus_stop_master, stop_id NULL 건수 모니터링

- `scripts/php/check_route_public_data_counts.php`로 테이블 건수 확인
- Admin 운영 요약 화면에서 `seoul_bus_*` 건수 모니터링

---

## 6. 정리

| 질문 | 답변 |
|------|------|
| 길찾기가 안 되는 이유? | **로직은 있음.** 데이터 미적재 또는 정류장명 불일치 가능성 큼 |
| 구현 전? | **아니오.** 이미 구현됨 |
| API 연결 필요? | **경로 조회에는 불필요.** 지도 표시·geocoding에만 필요 |
| 정류장ID + 공공데이터로 가능? | **가능.** 현재 설계가 이 방식 |
| 네이버·카카오 API 필수? | **아니오.** 공공데이터 DB 적재만으로 버스 경로 조회 가능 |

---

## 7. 참조 문서

- [data/README_DATA_DIRS.md](../data/README_DATA_DIRS.md)
- [ROUTE_FINDER_UX_NOTES_v1_8.md](./ROUTE_FINDER_UX_NOTES_v1_8.md)
- [NAVER_MAP_UI_ADOPTION_v1_8.md](./NAVER_MAP_UI_ADOPTION_v1_8.md)

---

*문서 버전: v1.8. 2026-02 기준. 멀티롤 회의 검토 결과.*
