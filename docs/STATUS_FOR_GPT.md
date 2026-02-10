# 길라임(ShuttleMap) PoC — 현재 반영 상태 (GPT 공유용)

프로젝트 루트: `gilime_mvp_01` (XAMPP: PHP 8.x + MySQL)

---

## 1. 현재 버전 및 완료 범위

| 버전 | 상태 | 요약 |
|------|------|------|
| **v0.6-5** | 완료 | route_stop 스냅샷 누적 (DELETE 제거, is_active=0/1), promote/route_review/doc 일치 |
| **v0.6-6** | 완료 | latest PARSE_MATCH 고정, stale candidate 차단, promote는 latest만, route_stop에 PROMOTE job_id 표시 |
| **v0.6-7** | 완료 | doc Routes=latest 스냅샷 기준, 스냅샷 비교 표시, PROMOTE 히스토리 10건, promote 보수적 조건(matched_stop_id 빈값 차단) |
| **v0.6-8** | 완료 | promoted_job_id 레거시 제거 DDL, promoted=0이면 failed 처리+원인 안내, 히스토리 rows=0 legacy 문구 |
| **v0.6-9** | 완료 | job_log에 base_job_id·route_label 추가(PROMOTE 추적), PROMOTE 히스토리에 base_job_id·후보/승인 수 표시 |
| **v0.6-10** | 완료 | seoul_bus_stop_master 테이블·import 파이프라인(inbound CSV → DB), data/README import 실행법 |
| **v0.6-11** | 완료 | PARSE_MATCH 시 seoul_bus_stop_master 기반 자동매칭 추천(matched_stop_id/name/score/method), pending UI 선채움 |
| **v0.6-12** | 완료 | 정류장명 정규화 파이프라인 + 동의어 사전(shuttle_stop_alias), alias 적용 단계·match_method 확장, route_review에 normalized_name 표시·alias 등록 버튼 |
| **v0.6-13** | 완료 | alias 등록 즉시 candidate 1건 live rematch(matched_* UPDATE, match_method=alias_live_rematch), canonical 없으면 alias만 저장 |
| **v0.6-14** | 완료 | match_method/match_score 노출, like_prefix 2글자 이하 시 미적용, alias 입력 가이드 문구 |
| **v0.6-15** | 완료 | route_review에 Stop Master Quick Search(exact/normalized/like_prefix 최대 10건), raw_stop_name readonly 복사용 |
| **v0.6-16** | 완료 | 매칭 실패만 보기(only_unmatched=1), 추천 canonical 컬럼, alias 입력 placeholder에 추천값 |
| **v0.6-17** | 완료 | 추천 canonical 계산을 only_unmatched 시에만 수행, 요청 단위 캐시(normalized 키), meta에 ON/OFF·hits/misses 표시, SoT 불변 |

**코드 기준 SoT(Source of Truth):** v0.6-7 규칙 유지 + v0.6-8/9/10/11/12/13/14/15/16/17 확장 반영됨.

---

## 2. DB 상태 (반영 완료 가정)

- **shuttle_route_stop**  
  - `created_job_id`, `is_active`, `updated_at` 사용.  
  - UNIQUE: `uq_route_snapshot_order(source_doc_id, route_label, created_job_id, stop_order)`  
  - 인덱스: `ix_route_stop_doc_route_active`, `ix_route_stop_created_job`  
  - `promoted_job_id` 컬럼/인덱스는 v0.6-8에서 제거됨.

- **shuttle_doc_job_log**  
  - v0.6-9: `base_job_id`(BIGINT UNSIGNED NULL), `route_label`(VARCHAR(50) NULL) 추가.  
  - 인덱스: `ix_job_doc_type_status`, `ix_job_base_job`.

- **shuttle_stop_candidate**  
  - `created_job_id` = PARSE_MATCH job_id (candidate 스냅샷), `is_active`.  
  - v0.6-11: `matched_stop_id`, `matched_stop_name`, `match_score`, `match_method`(자동매칭 추천, status는 계속 pending).

- **seoul_bus_stop_master** (v0.6-10)  
  - 서울시 버스 정류장 마스터. `scripts/import_seoul_stop_master.php`로 적재. 자동매칭 조회용.

- **shuttle_stop_alias** (v0.6-12)  
  - 동의어 사전. alias_text(유니크) → canonical_text. route_review에서 "alias 등록"으로 추가. PARSE_MATCH 시 alias 적용 후 canonical으로 stop_master 재시도.

- **shuttle_stop_normalize_rule** (v0.6-12, 선택)  
  - 정규화 규칙 확장용. 현재 매칭 로직은 trim+collapse_space 고정.

- **shuttle_source_doc**  
  - 문서 메타 (기존 유지).

---

## 3. 규칙 요약 (절대 되돌리지 말 것)

1. **latest PARSE_MATCH**  
   - `source_doc_id` 기준, `job_type='PARSE_MATCH'`, `job_status='success'`, `ORDER BY id DESC LIMIT 1`.

2. **Candidate**  
   - `created_job_id` = latest PARSE_MATCH job_id.  
   - stale(이전 job_id) 승인/거절은 서버·UI 모두 차단.

3. **Promote**  
   - latest PARSE_MATCH job만 승격.  
   - approved 후보 중 `matched_stop_id` NULL/빈값 1건이라도 있으면 차단.  
   - promoted=0이면 rollback + job_status=failed + flash 원인 안내.

4. **route_stop**  
   - DELETE 금지. 기존 is_active=1 → 0 후 신규 INSERT(is_active=1, created_job_id=PROMOTE job_id).  
   - 화면/조회는 `is_active=1`만.

5. **doc.php Routes**  
   - latest PARSE_MATCH 스냅샷(`created_job_id=latest`) 기준으로만 route_label 노출.

6. **PROMOTE 추적**  
   - job_log에 `route_label`, `base_job_id`(=parse_job_id) 저장.  
   - “이번 PROMOTE가 어떤 PARSE_MATCH에서 왔는지” DB에서 추적 가능.

---

## 4. 수정 대상 파일 (PoC 범위)

| 파일 | 역할 |
|------|------|
| `public/admin/doc.php` | 문서 상세, Routes(latest 스냅샷), Run Parse/Match |
| `public/admin/route_review.php` | 후보 승인/거절, 스냅샷 비교, PROMOTE 히스토리(base_job_id 포함), Promote 버튼 |
| `public/admin/promote.php` | POST 전용, route_label·base_job_id 저장, promoted=0 시 failed |
| `public/admin/run_job.php` | PARSE_MATCH 더미 파서, candidate 스냅샷 생성 (이번 단계에서 로직 변경 없음) |
| `app/inc/auth.php`, `app/inc/db.php` | 변경 금지로 유지 |

---

## 5. SQL 마이그레이션

- `sql/v0.6-8_drop_promoted_job_id.sql` — promoted_job_id 컬럼/인덱스 제거(프로시저로 존재 시에만 실행).
- `sql/v0.6-9_joblog_base_job.sql` — job_log에 base_job_id, route_label 추가 및 인덱스 2개.

---

## 6. 데이터 폴더 구조 (v0.6-9)

- **inbound** (원본):  
  `data/inbound/seoul/bus/stop_master`, `route_master`, `route_stop_master`;  
  `data/inbound/seoul/subway/station_distance`;  
  `data/inbound/source_docs/shuttle_pdf_zip`.

- **raw** (ETL 입력):  
  `data/raw/seoul_stop_master`, `seoul_route_master`, `seoul_route_stop_master`, `seoul_subway_station_distance`, `source_docs`.

- **derived** (가공/DB용):  
  `data/derived/seoul/bus`, `data/derived/seoul/subway`, `data/derived/source_docs`.

폴더 생성: `scripts/ensure-data-dirs.js` 또는 `data/README_DATA_DIRS.md` 내 PowerShell/bash 명령 참고.  
**Git:** `data/inbound/`는 `.gitignore`로 커밋 제외, `data/raw/`·`data/derived/`는 커밋 대상(폴더 구조는 .gitkeep 유지).

---

## 7. 로드맵 (참고만, 이번 작업에서 구현하지 말 것)

- **Phase 0 (PoC, 현재):** 더미 파서, 관리자 승인/거절, promote 스냅샷, job_log 추적.
- **Phase 1 (MVP):** 실제 PDF OCR/파싱, 공공데이터 stop_id 매칭, route_label 다중 등.
- **Phase 2 이후:** PDF 변경 감지, 버전 비교, 다기관/다노선 등.

---

## 8. 제약 (새 작업 시 유지)

- 새 테이블/새 페이지 추가 금지(지시 없는 한).  
- SoT·stale 차단·promote=0 failed·route_stop 스냅샷 규칙 약화 금지.  
- 대규모 리팩터링·실제 파서/배치/공공데이터 연동은 PoC 범위 밖.

---

위 내용까지 Cursor에서 반영된 상태입니다. DB는 Workbench에서 DDL 적용 여부만 별도 확인하면 됩니다.
