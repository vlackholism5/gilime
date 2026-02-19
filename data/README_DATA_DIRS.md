# 길라임 표준 데이터 폴더 (v0.6-9: inbound + raw + derived)

## 폴더 트리 (SoT 기준)

```
data/
├── inbound/                    # 원본 — Git 제외 (.gitignore)
│   ├── seoul/
│   │   ├── bus/
│   │   │   ├── stop_master/
│   │   │   ├── route_master/
│   │   │   └── route_stop_master/
│   │   └── subway/
│   │       └── station_distance/
│   └── source_docs/
│       └── shuttle_pdf_zip/
├── raw/                         # 전처리 결과 — 커밋 가능(용량 주의)
│   ├── seoul_stop_master/
│   ├── seoul_route_master/
│   ├── seoul_route_stop_master/
│   ├── seoul_subway_station_distance/
│   └── source_docs/
└── derived/                     # 가공 산출물 — 커밋 가능(용량 주의)
    ├── seoul/
    │   ├── bus/
    │   └── subway/
    └── source_docs/
```

## Git 정책

- **data/inbound/** — `.gitignore`에 포함, 커밋 금지.
- **data/raw/**, **data/derived/** — 커밋 대상(최소한 폴더 구조는 .gitkeep으로 유지).

---

## 생성 대상 (프로젝트 루트 기준 상대경로)

**A) inbound** — 원본 그대로 보관  
- `data/inbound/seoul/bus/stop_master`  
- `data/inbound/seoul/bus/route_master`  
- `data/inbound/seoul/bus/route_stop_master`  
- `data/inbound/seoul/subway/station_distance`  
- `data/inbound/source_docs/shuttle_pdf_zip`  

**B) raw** — ETL 입력 기준 (파일명/인코딩 정리된 버전)  
- `data/raw/seoul_stop_master`  
- `data/raw/seoul_route_master`  
- `data/raw/seoul_route_stop_master`  
- `data/raw/seoul_subway_station_distance`  
- `data/raw/source_docs`  

**C) derived** — 가공 산출물 (DB import용 CSV 등)  
- `data/derived/seoul/bus`  
- `data/derived/seoul/subway`  
- `data/derived/source_docs`  

---

## Step 1: Windows PowerShell (프로젝트 루트에서)

```powershell
cd "c:\xampp\htdocs\gilime_mvp_01"
@(
  "data/inbound/seoul/bus/stop_master",
  "data/inbound/seoul/bus/route_master",
  "data/inbound/seoul/bus/route_stop_master",
  "data/inbound/seoul/subway/station_distance",
  "data/inbound/source_docs/shuttle_pdf_zip",
  "data/raw/seoul_stop_master",
  "data/raw/seoul_route_master",
  "data/raw/seoul_route_stop_master",
  "data/raw/seoul_subway_station_distance",
  "data/raw/source_docs",
  "data/derived/seoul/bus",
  "data/derived/seoul/subway",
  "data/derived/source_docs"
) | ForEach-Object { New-Item -ItemType Directory -Path $_ -Force | Out-Null }
```

---

## Step 2: macOS / Linux bash (프로젝트 루트에서)

```bash
cd /path/to/gilime_mvp_01
mkdir -p data/inbound/seoul/bus/stop_master
mkdir -p data/inbound/seoul/bus/route_master
mkdir -p data/inbound/seoul/bus/route_stop_master
mkdir -p data/inbound/seoul/subway/station_distance
mkdir -p data/inbound/source_docs/shuttle_pdf_zip
mkdir -p data/raw/seoul_stop_master
mkdir -p data/raw/seoul_route_master
mkdir -p data/raw/seoul_route_stop_master
mkdir -p data/raw/seoul_subway_station_distance
mkdir -p data/raw/source_docs
mkdir -p data/derived/seoul/bus
mkdir -p data/derived/seoul/subway
mkdir -p data/derived/source_docs
```

---

## Step 3: 대체안 — Node 스크립트

**실행 방법 (프로젝트 루트에서):**

```bash
node scripts/node/ensure-data-dirs.js
```

스크립트 경로: `scripts/node/ensure-data-dirs.js`

---

## Import 실행 방법

### v0.6-20: 서울시 정류장 마스터 실데이터 (inbound CSV → DB)

1. **CSV 파일 위치:**  
   `data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv` (euc-kr)  
   ⚠️ **Git 커밋 금지** (inbound는 .gitignore 대상)

2. **Import 실행 (Cursor 터미널, 프로젝트 루트에서):**
   ```bash
   php scripts/php/import_seoul_bus_stop_master_full.php
   ```

3. **성공 예:**  
   `OK: imported 11462 rows` (inserted / updated / skipped 로그 포함)

4. **검증 (Workbench):**  
   `sql/archive/v0.6/validation/v0.6-20_validation.sql` 주석 해제 후 9개 쿼리 실행

5. **특징:**  
   - Idempotent (중복 실행 시 UPSERT)
   - euc-kr → UTF-8 자동 변환
   - 헤더 기반 자동 매핑 (정류장ID, 정류장명칭, 시군구코드, 위도, 경도)

---

### v1.7-18: 서울시 노선/노선정류장 마스터 실데이터 (inbound CSV → DB)

1. **사전 작업 (스키마 적용):**  
   Workbench에서 아래 SQL 실행
   - `sql/releases/v1.7/schema/schema_18_seoul_route_public_data.sql`

2. **CSV 파일 위치:**  
   - `data/inbound/seoul/bus/route_master/서울시 노선마스터 정보.csv`
   - `data/inbound/seoul/bus/route_stop_master/서울시 노선 정류장마스터 정보.csv`  
   ⚠️ **Git 커밋 금지** (inbound는 .gitignore 대상)

3. **Import 실행 (프로젝트 루트):**
   ```bash
   php scripts/php/import_seoul_bus_route_master_full.php
   php scripts/php/import_seoul_bus_route_stop_master_full.php
   ```

4. **검증 (Workbench):**  
   `sql/releases/v1.7/validation/validation_18_seoul_route_public_data.sql` 주석 해제 후 실행

5. **특징:**  
   - Idempotent (UPSERT, route_master는 route_id 기준 / route_stop_master는 route_id+seq 기준)
   - UTF-8/EUC-KR 입력 자동 대응
   - 현재 단계는 적재/검증까지이며, 사용자 경로 계산과의 결합은 다음 단계에서 진행

---

### v0.6-10: 테스트용 더미 (이전 방식)

1. DDL 적용: Workbench에서 `sql/archive/v0.6/schema/v0.6-10_seoul_stop_master.sql` 실행.
2. 더미 데이터 10건만 생성 (강남역, 서울역, 홍대입구역 등)

**커밋 정책:** inbound 제외 유지, raw/derived·스크립트·SQL은 커밋 대상.

---

## Step 4: 확인 커맨드

**Windows PowerShell:**

```powershell
Get-ChildItem -Path data -Recurse -Directory | Sort-Object FullName | ForEach-Object { $_.FullName.Replace((Get-Location).Path + "\", "").Replace("\", "/") }
```

**macOS / Linux (tree 있으면):**

```bash
tree data
```

**macOS / Linux (tree 없으면):**

```bash
find data -type d | sort
```
