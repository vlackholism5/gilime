# OPS — 배포·운영 로드맵

## 목적

MVP/MVP+ ~ Phase-2 구간의 배포 방식, 운영 로그·trace_id·검증 절차, 롤백 경로를 문서로 정리한다. 배포 환경은 로컬 → 단일 서버 가정이며, 스케일아웃·CI는 Roadmap 이후로 둔다. 일부 항목은 가설로 명시하고, 구현 시 본 문서를 갱신한다.

---

## MVP/MVP+ 배포 방식 (가설 허용)

- **로컬:** PHP 내장 또는 XAMPP, 단일 DB(MySQL). 코드는 워크스페이스 기준 배포(복사 또는 git pull).
- **단일 서버:** 웹서버(Apache/Nginx) + PHP-FPM + MySQL. 배포는 파일 배치(git pull 또는 rsync) + 필요 시 마이그레이션 수동 실행. secrets·환경변수 정책은 Phase-2 착수 전 1문단으로 확정(가설: .env 또는 서버 환경변수, 저장소에 커밋하지 않음).
- **검증:** 배포 후 아래 "검증 절차" 실행. E1/E2 path 200 응답 및 trace_id 존재 확인 권장.

---

## 운영 로그 및 trace_id

- **API 요청:** public/api/index.php 진입 시 observability 레이어에서 api_enter, db_query_start/end, api_exit 기록. 응답 헤더 또는 본문에 trace_id 포함(구현 위치: app/inc/observability.php 등).
- **목적:** 장애 시 요청 단위 추적. 로그 수집·저장소(파일/외부 서비스)는 단일 서버 가정 하에 로컬 파일 또는 단일 서버 로그 디렉터리로 충분하다고 가정(가설).
- **검증:** curl로 E1/E2 호출 후 응답에 trace_id(또는 동일 식별자)가 포함되는지 확인.

---

## 검증 절차

1. **배포 직후:**  
   - E1 by-name: `GET .../api/index.php?path=g1/station-lines/by-name&station_name=서울역` → 200, data.line_codes 존재.  
   - E2 by-code: `GET .../api/index.php?path=g1/station-lines/by-code&station_cd=0150` → 200.  
2. **DB:** OPS_DB_MIGRATIONS 기준 validation 쿼리 실행(STEP5~STEP8 검증 파일). vw_subway_station_lines_g1 존재 및 row 수 확인.  
3. **trace_id:** 위 API 응답 본문 또는 헤더에서 trace_id 확인(구현된 경우).

---

## Rollback (뷰 / 엔드포인트 / 데이터)

| 대상 | 조치 | 참조 |
|------|------|------|
| **뷰** | G1 API가 vw_subway_station_lines_g1에 의존. 롤백 시 `DROP VIEW IF EXISTS vw_subway_station_lines_g1;` 실행. 복구 시 sql/views/v0.8-07_view_station_lines_g1.sql 재실행. | OPS_G1_RUNBOOK, OPS_DB_MIGRATIONS File reference v0.8-07 |
| **엔드포인트** | G1 path만 비활성화하려면: (가설) 환경변수 G1_API_DISABLED=1 시 by-name/by-code에 503 또는 404 반환. 미구현 시 웹서버/라우팅에서 해당 path 차단. | OPS_G1_RUNBOOK § G1 API 비활성화 |
| **데이터** | 테이블·마이그레이션 롤백은 스키마 변경이 필요하므로 별도 롤백 스크립트 또는 수동 DDL. STEP4~STEP9 의미 변경 없이 유지. 데이터만 되돌리려면 백업 복원 또는 ingest 재실행(문서화된 ingest 절차 참조). | OPS_DB_MIGRATIONS |

---

## 요약

- 배포: 로컬 → 단일 서버, 파일 배치 + 마이그레이션 수동. 가설 허용.
- 운영: trace_id·API 로그로 요청 추적. 검증은 E1/E2 200 + trace_id + DB validation.
- 롤백: 뷰(DROP VIEW/재생성), 엔드포인트(환경변수 또는 라우팅 차단), 데이터(백업 복원 또는 ingest 재실행).
