# 임시 셔틀버스 PDF 파싱·저장 설계 (v1.7)

## 개요

`서울버스일부파업_01차` 등의 PDF에서 **노선·정류장·운행시간**을 추출하여 DB에 저장하고, 길찾기 그래프에 포함시키기 위한 설계.

---

## 1. 대상 데이터

| 항목 | 내용 |
|------|------|
| PDF 경로 | `data/inbound/source_docs/shuttle_pdf_zip/서울버스일부파업_01차/*.pdf` |
| 1 PDF당 | 1 자치구, 1~7개 노선 |
| 추출 대상 | 노선명, 정류장명 순서, 출발시간, 막차시간, 배차간격, 운행거리 등 |

---

## 2. DB 스키마

### 2.1 shuttle_temp_route (임시 노선 마스터)

route_master와 독립. route_id는 자체 시퀀스 사용.

```sql
-- v1.7-21: 임시 셔틀버스 노선 마스터
CREATE TABLE IF NOT EXISTS shuttle_temp_route (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  route_label       VARCHAR(80) NOT NULL DEFAULT '' COMMENT '노선명 ex) 동작1(임시), 서초1(임시)',
  district_name     VARCHAR(40) NULL DEFAULT NULL COMMENT '자치구명',
  source_doc_id     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '원본 PDF (shuttle_source_doc)',
  first_bus_time    VARCHAR(20) NULL DEFAULT NULL COMMENT '첫차 ex) 06:00',
  last_bus_time     VARCHAR(20) NULL DEFAULT NULL COMMENT '막차 ex) 22:00',
  headway_min       VARCHAR(30) NULL DEFAULT NULL COMMENT '배차간격 ex) 20분, 15~20분',
  distance_km       DECIMAL(6,2) NULL DEFAULT NULL COMMENT '운행거리 km',
  bus_count         INT UNSIGNED NULL DEFAULT NULL COMMENT '배정대수',
  run_count         INT UNSIGNED NULL DEFAULT NULL COMMENT '운행횟수',
  raw_json          JSON NULL DEFAULT NULL COMMENT '원본 추출 JSON',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_route_label (route_label),
  KEY ix_district (district_name(20)),
  KEY ix_source_doc (source_doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='임시 셔틀버스 노선 마스터';
```

### 2.2 shuttle_temp_route_stop (임시 노선-정류장)

길찾기 그래프용. route_stop_master와 동일 개념.

```sql
-- v1.7-21: 임시 셔틀버스 노선-정류장 (길찾기 그래프용)
CREATE TABLE IF NOT EXISTS shuttle_temp_route_stop (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  temp_route_id     BIGINT UNSIGNED NOT NULL COMMENT 'FK shuttle_temp_route.id',
  seq_in_route      INT UNSIGNED NOT NULL COMMENT '노선 내 순번',
  raw_stop_name     VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'PDF 원본 정류장명',
  stop_id           BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'seoul_bus_stop_master 매칭 ID',
  stop_name         VARCHAR(120) NULL DEFAULT NULL COMMENT '매칭된 정류장명',
  match_method      VARCHAR(40) NULL DEFAULT NULL COMMENT 'exact, like_prefix, id_extract, manual',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_temp_route_seq (temp_route_id, seq_in_route),
  KEY ix_temp_route (temp_route_id),
  KEY ix_stop_id (stop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='임시 셔틀버스 노선-정류장 (길찾기 그래프용)';
```

---

## 3. 파싱 로직

### 3.1 흐름

```
[PDF 업로드] → shuttle_source_doc
       ↓
[PARSE_TEMP_SHUTTLE job] (신규 job_type)
       ↓
[1] 전체 텍스트 추출 (기존 pdf_parser / OCR)
[2] 자치구·운행시간 추출
[3] 노선 블록 분리 (연번 1, 2, 3... 기준)
[4] 각 노선별: route_label, 정류장 순서, 메타 추출
[5] shuttle_temp_route INSERT
[6] 정류장명 → stop_id 매칭
[7] shuttle_temp_route_stop INSERT
       ↓
[길찾기 그래프] route_stop_master + shuttle_temp_route_stop 병합
```

### 3.2 노선 블록 분리 규칙

| 패턴 | 예시 | 처리 |
|------|------|------|
| `연번` + 숫자 | `1`, `2`, `3` | 새 노선 시작 |
| `〔N호차〕` | `〔1호차〕` | 노선 시작 (서초구) |
| `노선도` | `동작1(임시) 노선도` | 노선 라벨 후보 (테이블 캡션) |

### 3.3 정류장 분리 규칙

| 구분자 | 예시 |
|--------|------|
| `→` | `노량진역→동작구청→성대시장` |
| `↔` | `대학동고시촌입구↔서울대학교↔서울대입구역` |
| `~`, `-`, `–` | `노량진역~동작구청~성대시장` |
| `,` (일부) | `경향렉스빌,롯데캐슬` → 2개 정류장 |

### 3.4 정류소ID 추출 (서초구 등)

```
양재역(22289)  → raw: "양재역", stop_id: 22289 (직접 매칭)
양재역말죽거리.강남베드로병원(23318,시점) → stop_id: 23318
```

정규식: `\((\d{5,})\)` → 괄호 안 5자리 이상 숫자를 stop_id 후보로 사용.

### 3.5 운행시간 추출

| 패턴 | 예시 | first_bus | last_bus |
|------|------|-----------|----------|
| `HH:MM~HH:MM` | `06:00~22:00` | 06:00 | 22:00 |
| `HH시~HH시` | `06시~22시` | 06:00 | 22:00 |
| `HH:MM～HH:MM` | `05:00～22:00` | 05:00 | 22:00 |

### 3.6 배차간격 추출

| 패턴 | 예시 | headway_min |
|------|------|--------------|
| `N분` | `20분`, `15분` | `20`, `15` |
| `N~M분` | `15~20분`, `20~30분` | `15~20`, `20~30` |

---

## 4. 정류장 매칭 로직

`run_job.php`의 `matchStopFromRouteMaster`와 유사. 순서:

1. **id_extract**: 괄호 안 stop_id 추출 → stop_master 존재 여부 확인
2. **exact**: `stop_name = raw_stop_name` (trim, 공백 정규화)
3. **alias**: shuttle_stop_alias 조회
4. **like_prefix**: `stop_name LIKE CONCAT(prefix, '%')` (2글자 초과 시)
5. **fuzzy** (선택): Levenshtein 등 유사도

---

## 5. 기존 파이프라인 연동

### 5.1 방식 A: 기존 pipeline 확장

- `parse_shuttle_pdf()` → **다중 노선** 반환하도록 확장
- 반환: `routes => [{ route_label, stops, first_bus, last_bus, headway, ... }, ...]`
- `run_job.php`에 `PARSE_TEMP_SHUTTLE` 분기 추가

### 5.2 방식 B: 전용 파서 (권장)

- `parse_temp_shuttle_pdf()` 신규 함수
- 1 PDF → 복수 노선 구조에 맞춤
- `run_job_temp_shuttle.php` 또는 `run_job.php`에 `job_type=PARSE_TEMP_SHUTTLE` 추가

### 5.3 job_type

| job_type | 설명 |
|----------|------|
| `PARSE_MATCH` | 기존: 1 PDF 1 노선, shuttle_stop_candidate |
| `PARSE_TEMP_SHUTTLE` | 신규: 1 PDF N 노선, shuttle_temp_route + shuttle_temp_route_stop |

---

## 6. 파일 구조

```
app/inc/
  pdf_parser.php           # 기존 (단일 노선)
  temp_shuttle_parser.php  # 신규 (다중 노선, 시간/배차 추출)

public/admin/
  run_job.php              # job_type 분기 추가
  upload_pdf.php           # doc_type 파라미터 (temp_shuttle)

sql/releases/v1.7/
  schema/
    schema_21_shuttle_temp.sql   # shuttle_temp_route, shuttle_temp_route_stop

scripts/
  run_temp_shuttle_import.php   # (선택) 배치 일괄 처리
```

---

## 7. 길찾기 그래프 빌드

### 7.1 통합 쿼리

```sql
-- 기존 노선 + 임시 노선 통합
SELECT route_id, seq_in_route, stop_id, stop_name
  FROM seoul_bus_route_stop_master
  WHERE stop_id IS NOT NULL
UNION ALL
SELECT temp_route_id AS route_id, seq_in_route, stop_id, stop_name
  FROM shuttle_temp_route_stop
  WHERE stop_id IS NOT NULL
ORDER BY route_id, seq_in_route;
```

- `route_id` 구분: route_master는 양수, temp_route는 temp_route.id 사용 (길찾기 시 route_type 플래그로 구분 가능)

### 7.2 재처리 시 UPSERT

동일 PDF 재업로드 시 `route_label` 중복 방지:

```sql
-- shuttle_temp_route: ON DUPLICATE KEY UPDATE
INSERT INTO shuttle_temp_route (route_label, district_name, ...)
VALUES (...)
ON DUPLICATE KEY UPDATE
  district_name = VALUES(district_name),
  first_bus_time = VALUES(first_bus_time),
  ...
  updated_at = CURRENT_TIMESTAMP;

-- shuttle_temp_route_stop: 기존 삭제 후 INSERT
DELETE FROM shuttle_temp_route_stop WHERE temp_route_id = ?;
INSERT INTO shuttle_temp_route_stop (...);
```

---

## 8. 구현 단계

| Phase | 작업 | 산출물 |
|-------|------|--------|
| 1 | DDL | schema_21_shuttle_temp.sql |
| 2 | temp_shuttle_parser.php | parse_temp_shuttle_pdf() |
| 3 | run_job 분기 | PARSE_TEMP_SHUTTLE |
| 4 | 업로드 UI | doc_type=temp_shuttle 선택 |
| 5 | 길찾기 API | 그래프 병합 쿼리 |

---

## 9. 참고: PDF 구조 차이

| 구 | 노선명 | 정류소ID | 시간 표기 |
|----|--------|----------|-----------|
| 동작구 | 동작1(임시) | 없음 | 06:00~22:00 |
| 서초구 | 서초1(임시) | 있음 (22289) | 06시~22시 |
| 관악구 | 관악 임시1번 | 없음 | 06:00~22:00 |
| 금천구 | 금천1(임시) | 없음 | 05:00～22:00 |
| 영등포구 | 임시1번 | 없음 | 06:00~22:00 |

파서는 위 변형을 모두 처리하도록 설계.

---

*문서 버전: v1.7-21. 2026-02 기준.*
