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
node scripts/ensure-data-dirs.js
```

스크립트 경로: `scripts/ensure-data-dirs.js`

---

## Import 실행 방법 (v0.6-10)

**서울시 정류장 마스터 (inbound CSV → DB)**

1. DDL 적용: Workbench에서 `sql/v0.6-10_seoul_stop_master.sql` 실행.
2. CSV 위치: `data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv` (euc-kr).  
   (inbound는 .gitignore 대상이므로 배포 시 해당 파일을 별도 배치해야 함.)
3. CLI 실행 (프로젝트 루트에서):

```bash
php scripts/import_seoul_stop_master.php
```

성공 시 예: `OK rows_processed=11462 inserted=... updated=...`  
중복 실행 시에도 결과가 안정적(upsert, idempotent).  
커밋 정책: inbound 제외 유지, raw/derived·스크립트·SQL은 커밋 대상.

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
