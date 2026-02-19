# v1.7-14 Smoke: PARSE_MATCH 운영형 고도화

## 사전 조건
- `composer` 의존성 설치 완료 (`vendor/autoload.php` 존재)
- 테스트 PDF 존재: `public/uploads/test_route_r1.pdf`
- `shuttle_source_doc`에 테스트 레코드 존재

---

## 시나리오 A: 성공 경로

### 1) Web 실행 경로 검증
1. `admin/doc.php?id=<doc_id>` 접속
2. `파싱/매칭 실행` 버튼 클릭
3. 기대:
   - flash: `파싱/매칭 성공: rows=...`
   - `parse_status = success`
   - `last_parse_duration_ms` 값 표시

### 증거 수집 (성공)
| 구분 | 포맷 | 예시 |
|------|------|------|
| SQL | `SELECT parse_status, updated_at FROM shuttle_source_doc WHERE id=<doc_id>` | `parse_status=success` |
| 로그 | `GILIME_DEBUG=1` 시 `[TRACE ...] parse_job_end` | `result=success elapsed_ms=... stop_cnt=... auto_matched_ratio_pct=...` |
| 화면 | doc.php 메타 그리드 | `최근 파싱 상태=success`, `최근 파싱 소요=...ms` |

---

## 시나리오 B: 실패 경로

### 2) 경로/확장자 가드 검증
1. `shuttle_source_doc.file_path`를 `../secret.txt`로 변경
2. `재실행` 버튼 클릭
3. 기대:
   - 실패 flash 표시
   - `parse_status = failed`
   - `job_log.result_note`에 `error_code=PARSE_...` 포함

### 증거 수집 (실패)
| 구분 | 포맷 | 예시 |
|------|------|------|
| SQL | `SELECT job_status, result_note FROM shuttle_doc_job_log WHERE source_doc_id=<doc_id> ORDER BY id DESC LIMIT 1` | `job_status=failed`, `result_note`에 `error_code=PARSE_PATH_TRAVERSAL` |
| 로그 | `[TRACE ...] parse_job_end` | `result=failed error_code=PARSE_PATH_TRAVERSAL` |
| 화면 | doc.php 메타 그리드 | `최근 파싱 오류코드=PARSE_PATH_TRAVERSAL` 또는 `PARSE_FILE_NOT_FOUND` 등 |

### 3) 실패 원인 TopN 확인
1. 실패 케이스 2~3회 실행
2. `admin/doc.php?id=<doc_id>` 확인
3. 기대:
   - `PARSE_MATCH 실패 TopN` 표에 `error_code` 집계 표시

---

## 시나리오 C: 재처리 경로

### 4) 배치 재처리(dry-run)
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20 --dry_run=1
```

기대:
- JSON 출력
- `selected`, `processed`, `dry_run`, `fail_topn` 필드 존재

### 5) 배치 재처리(실행)
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20
```

기대:
- 성공 시 `success` 증가
- 실패 시 `fail_topn`에 코드별 카운트
- 대상 문서 `parse_status`가 `success/failed`로 갱신

### 6) pending/failed 문서 재처리(기본)
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --limit=10 --dry_run=1
```

기대:
- `selection_policy`에 `parse_status IN (pending,failed)` 포함
- `parse_status`가 pending 또는 failed인 문서만 대상

### 재처리 증거 수집
| 구분 | 포맷 | 예시 |
|------|------|------|
| SQL | `SELECT is_active, created_job_id FROM shuttle_stop_candidate WHERE source_doc_id=<doc_id>` | 이전 후보 `is_active=0`, 새 후보 `created_job_id=최신 job_id` |
| 화면 | doc.php 실행 버튼/안내 문구 | "동일 문서 재실행 시 이전 후보(is_active=1)는 비활성화되고 새 job_id로 후보가 생성됩니다" |

---### 7) 실패건 빠른 필터
1. `admin/index.php?parse_status=failed` 접속
2. 기대: `parse_status=failed` 문서만 표시
3. `admin/doc.php` 재실행 버튼 주변에 "실패건 보기" 링크, 처리시간, 배치 명령 안내 표시