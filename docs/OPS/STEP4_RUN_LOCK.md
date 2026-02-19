# STEP 4-Run — 실행 기반 LOCK 회의

## 1) STEP 4 결과물 검수 결론 (LOCK)

### PASS (즉시 진행 가능)

- 신규 파일 5개: OPS 1 + script 3 + helper SQL 1
- 런타임(public/*), 라우트, UI 미변경
- path resolver: 정식 경로 → inbound fallback
- 출력 경로: `data/derived/seoul/subway/`, `data/derived/seoul/bus/`
- edges 중복 제거: CSV 생성 단계에서 수행

### 확인 필요 (실행 결과로 확정) — I1 closed

- 공식 CSV 컬럼명(역코드/호선/역명) ↔ 스크립트 파서 일치 여부 → **확정됨** (역간거리 단일 역명 컬럼 + alias 매핑)
- OSM station master CSV 컬럼명(osm_name, lat, lon, id) 일치 여부 → **확정됨**
- match_rate_pct ≥90% 달성 여부(동명이역/환승/지선 포함) → **100% (315 matched, 0 unmatched)**
- bus stop master 컬럼명(정류장 id/명/좌표) 일치 여부 → **확정됨**

---

## 2) 이번 회의에서 LOCK할 것 (필수)

| ID | 항목 | 결론 (실행 후 채움) |
|----|------|---------------------|
| **D1** | OSM↔공식 매칭율 | >=90% / <90% |
| **D2** | <90%일 때 보정 전략 | alias 테이블 추가 / 컬럼 정규화 강화 / 거리 임계값 조정 |
| **D3** | unmatched top30 처리 | 수동 보정 파일 포맷 고정 |
| **D4** | WALK edge 임계값(400/600m) | 유지 / 조정 |
| **D5** | G1 station line_code 정책 | edges_unique만 채움; ambiguous/unresolved는 의도적으로 blank 유지. 단일 line_code 자동 선택/미해결 역 fabrication 금지. |

---

### Open issues (tracked, not blocking)

| ID | 내용 |
|----|------|
| **I2** | G1 station master lacks authoritative line_code; requires upstream source integration or multi-line station modeling. |

---

## 3) 로컬 실행 순서 (정답)

### A. 매칭 실행 (가장 먼저)

```bash
cd c:\xampp\htdocs\gilime_mvp_01
python scripts/python/subway_match_v1.py
```

실행 후 아래 3가지를 **이 문서의 "4) 붙여넣기 영역"**에 붙이거나, 다음 프롬프트에 그대로 붙여주세요.

1. `data/derived/seoul/subway/_qa_station_match_v1.json` **전문**
2. `data/derived/seoul/subway/_qa_station_unmatched_top30.csv` **상위 30줄**
3. `subway_station_match_v1.csv`의 HIGH/MED/LOW 행수 요약 (아래 PowerShell로 가능)

```powershell
Select-String -Path data\derived\seoul\subway\subway_station_match_v1.csv -Pattern ",HIGH," | Measure-Object
Select-String -Path data\derived\seoul\subway\subway_station_match_v1.csv -Pattern ",MED,"  | Measure-Object
Select-String -Path data\derived\seoul\subway\subway_station_match_v1.csv -Pattern ",LOW,"  | Measure-Object
Select-String -Path data\derived\seoul\subway\subway_station_match_v1.csv -Pattern ",NONE," | Measure-Object
```

### B. edges 생성 (매칭 OK 시)

```bash
python scripts/python/subway_edges_ingest_v1.py
```

→ `subway_edges_g1_v1.csv` **총 행수**만 알려주세요.

### C. WALK edges 생성 (매칭 OK 시)

```bash
python scripts/python/walk_edges_station_to_bus_v1.py
```

→ `walk_edges_station_to_bus_v1.csv` **총 행수**만 알려주세요.

---

## 4) 붙여넣기 영역 (A 실행 결과)

아래 블록을 복사한 뒤, 실행 결과로 채워서 GPT/다음 프롬프트에 붙여넣으면 됩니다.

```
--- A 실행 결과 시작 ---

[1] _qa_station_match_v1.json 전문:
(여기에 JSON 전체 붙여넣기)

[2] _qa_station_unmatched_top30.csv 상위 30줄:
(여기에 CSV 내용 붙여넣기)

[3] match level별 행수:
HIGH: (개)
MED:  (개)
LOW:  (개)
NONE: (개)

--- A 실행 결과 끝 ---
```

---

## 5) 에러 시 붙여넣을 3줄

실행 중 에러가 나면 아래 3가지만 붙여주세요.

1. **에러 메시지 전문**
2. 스크립트가 출력한 **"tried paths" 목록**
3. **입력 CSV 1개(문제된 파일)의 헤더 1줄(컬럼명)**

→ 파서/컬럼 매핑 최소 diff로 수정 가능합니다.

---

## 6) 다음 단계 (결과에 따라 갈림)

- **match_rate_pct ≥ 90%** → STEP 5: import helper SQL로 `subway_stations_master` / `subway_edges_g1` 로드. 중복이 관측될 때만 `subway_edges_g1` UNIQUE 패치.
- **match_rate_pct < 90%** → STEP 4.1: alias 룰/정규화 보강. "수동 보정 파일" 포맷 SoT 고정, top30만 1회 보정.

**Proceeding to STEP5.** Final row counts (LOCK): A station match 315 (unmatched 0), B subway_edges_g1_v1.csv 270, C walk_edges_station_to_bus_v1.csv 8364.

---

## 7) 회귀 체크 (A6)

파이프라인 변경 후 STEP4 출력이 LOCK 값에서 벗어나지 않았는지 확인하는 절차. (신규 테스트 프레임워크 없이 문서 + read-only 검증만 사용.)

### 기대값 (LOCK)

| 출력 | 기대 row count | 비고 |
|------|----------------|------|
| A. station match | 315 matched, 0 unmatched | _qa_station_match_v1.json |
| B. subway_edges_g1_v1.csv | **270** | 지하철 역간 엣지 (중복 제거 후) |
| C. walk_edges_station_to_bus_v1.csv | **8364** | 역↔버스정류장 보행 엣지 |

### 검증 명령 (선택, 복붙용)

**CSV 행수 확인 (헤더 제외 데이터 행):**

```powershell
# 프로젝트 루트에서. B: edges 270 기대
$b = (Get-Content data\derived\seoul\subway\subway_edges_g1_v1.csv | Measure-Object -Line).Lines - 1
# C: walk 8364 기대
$c = (Get-Content data\derived\seoul\bus\walk_edges_station_to_bus_v1.csv | Measure-Object -Line).Lines - 1
Write-Host "B edges: $b (expect 270), C walk: $c (expect 8364)"
```

**DB 적용 후 검증 (STEP5 import 이후):** `sql/validate/v0.8-03_validate_subway_g1.sql`에서 `subway_edges_g1`에 대한 `SELECT COUNT(*)` 등으로 270 (또는 정책상 허용 범위) 확인 가능. 해당 파일 참조.

---

## 8) 다음 프롬프트 (D1~D4 LOCK용)

naomi가 **A 실행 결과(qa json + unmatched top30 + level별 행수)** 만 위 "붙여넣기 영역" 형식으로 전달하면, GPT에게 아래처럼 요청하면 됩니다.

```
STEP 4-Run 실행 결과를 반영해서 D1~D4를 LOCK해주세요.

- D1: OSM↔공식 매칭율 >=90% / <90% 결론
- D2: <90%면 보정 전략 (alias 테이블 추가 vs 컬럼 정규화 강화 vs 거리 임계값 조정)
- D3: unmatched top30 수동 보정 파일 포맷 고정
- D4: WALK edge 임계값(400/600m) 유지 여부

아래가 A 실행 결과입니다.

(여기에 [1][2][3] 붙여넣기)

이 결과 기준으로 docs/OPS/STEP4_RUN_LOCK.md의 "2) LOCK할 것" 테이블을 채우고, STEP 5 진행 여부 또는 STEP 4.1 보정 작업을 제안해주세요.
```
