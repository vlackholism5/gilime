# Gilaime MVP - public/ 구조

## v0.6-11 자동매칭 규칙

PARSE_MATCH(job) 실행 시 후보(candidates) 생성하면서 **서울시 정류장마스터(seoul_bus_stop_master)** 기반으로  
`matched_stop_id`, `matched_stop_name`, `match_score`, `match_method`를 **추천**만 채움. status는 계속 `pending`.

- **우선순위:** 정확일치(exact, 1.0) → 공백 정규화(normalized, 0.7) → prefix LIKE(like_prefix, 0.7)
- **인덱스:** `seoul_bus_stop_master.stop_name` 사용, 풀스캔 없음
- **UI:** route_review Candidates에서 자동매칭된 값이 입력란에 미리 채워진 상태로 표시. 실패 시 수동 입력 후 Approve

## v0.6-12 정규화 + 동의어(alias) 사전

- **정규화:** raw_stop_name → normalized_stop_name (trim + 공백 축약). route_review Candidates 테이블에 `normalized_name` 표시.
- **매칭 순서:** exact(1.0) → normalized(0.7) → **alias 적용** → canonical으로 exact/normalized 재시도(0.85, alias_exact/alias_normalized) → like_prefix(0.7).
- **동의어 사전:** `shuttle_stop_alias` (alias_text → canonical_text). route_review에서 pending 행에 **alias 등록** 버튼: raw(정규화)를 alias_text, 입력한 정식 명칭을 canonical_text로 저장. 다음 PARSE_MATCH부터 해당 alias로 자동매칭 시도.
- **테이블:** shuttle_stop_alias(필수), shuttle_stop_normalize_rule(선택). 새 페이지 없음, route_review 내부만 확장.

## v0.6-13 alias 등록 즉시 재매칭

- route_review에서 **alias 등록** 시, 해당 candidate 1건을 **재파싱 없이** 즉시 seoul_bus_stop_master로 재조회해 `matched_stop_id`/`matched_stop_name`/`match_score`(0.95)/`match_method`(=`alias_live_rematch`)를 UPDATE.
- canonical이 stop_master에 없으면 alias만 저장하고 flash: "alias saved but canonical not found in master". latest 스냅샷이 아닌(stale) 후보는 alias만 저장, rematch 생략.
- DDL 변경 없음.

## v0.6-14 매칭 품질·안전장치

- **Candidates 테이블:** match_method, match_score 컬럼 표시 추가(운영자가 매칭 원인/실패 즉시 확인).
- **like_prefix 보수화:** normalized 길이 2글자 이하일 때 like_prefix 미적용(짧은 단어 과매칭 방지).
- **alias 등록 가이드:** "stop_master에 존재하는 정식 정류장명으로 입력하세요." 문구 추가. DDL/신규 테이블 없음.

## v0.6-15 Stop Master Quick Search

- **route_review** 상단 meta 아래에 "Stop Master Quick Search" 카드 추가: stop_name 입력 → exact → normalized → like_prefix(2글자 초과 시만) 순으로 최대 10건 표시. alias canonical 입력 전 존재 여부 확인용.
- **Candidates** raw_stop_name을 readonly input으로 표시해 선택·복사 편의 제공. 새 페이지/테이블 없음.

## v0.6-16 매칭 실패 1번에 정리

- **매칭 실패만 보기:** GET only_unmatched=1 시 matched_stop_id NULL/'' 인 후보만 표시(latest 스냅샷 기준). 토글 링크로 전체/실패만 전환.
- **추천 canonical:** Candidates에 "추천 canonical" 컬럼 추가. raw_stop_name으로 Quick Search와 동일 규칙(exact→normalized→like_prefix) 1순위 stop_name 표시. alias 등록 시 참고용.
- **alias 폼:** canonical_text input의 placeholder에 추천값 반영(있을 때). DDL 없음(필요 시 shuttle_stop_alias 인덱스만 추가 허용).

## v0.6-17 추천 canonical 계산 최적화

- **only_unmatched에서만 계산:** 전체 보기(only_unmatched=0)에서는 추천 canonical 컬럼·placeholder 모두 "—"/"정식 명칭" 고정. 매칭 실패만 보기일 때만 stop_master 조회.
- **요청 단위 캐시:** 동일 raw_stop_name(정규화 키)당 DB 조회 1회. hits/misses는 meta에 "추천 canonical 계산: ON/OFF, cache hits=X, misses=Y"로 표시(전체 보기 시 OFF, 0/0). SoT·approve/reject/promote/alias_live_rematch 로직 불변.

## v0.6-18 매칭 신뢰도 표시 + summary 집계

- **매칭 신뢰도 컬럼:** route_review Candidates에 표시 전용. exact/alias_live_rematch/alias_exact → HIGH, normalized/alias_normalized → MED, like_prefix → LOW, 그 외/NULL → NONE (텍스트만, 신규 CSS 없음).
- **summary 4개 카운트:** latest 스냅샷 기준 auto_matched_cnt, low_confidence_cnt(like_prefix), none_matched_cnt, alias_used_cnt. promote 전 모호매칭 비중 파악용. only_unmatched=1일 때도 동일 latest 기준으로 표시.
- **검증:** sql/v0.6-18_validation.sql 에 검증 쿼리 7개(주석 블록). 매칭 로직/SoT 변경 없음.

## v0.6-19 LOW(like_prefix) 필터 + Promote 경고

- **only_low 필터:** GET only_low=1 시 latest 스냅샷 후보 중 `match_method='like_prefix'` 인 후보만 표시. only_unmatched=1과 동시 사용 가능(like_prefix 이면서 unmatched만).
- **토글 링크:** Candidates 상단에 "LOW만 보기" / "LOW 해제" 링크 추가. only_unmatched와 조합 유지.
- **Promote 경고:** Promote 버튼 위에 경고 문구 표시. 조건: low_confidence_cnt > 0 AND (low_confidence_cnt / auto_matched_cnt) >= 0.30. "주의: like_prefix(LOW) 비중이 높습니다. Promote 전 후보를 재검토하세요."
- **SQL 없음:** 화면 필터/표시만 추가. 매칭 로직/SoT 불변.

## v0.6-20 seoul_bus_stop_master 실데이터 import

- **Import 스크립트:** `scripts/import_seoul_bus_stop_master_full.php` (euc-kr CSV → UTF-8 변환, UPSERT, idempotent)
- **입력 파일:** `data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv` (Git 커밋 금지, 로컬 전용)
- **실행:** `php scripts/import_seoul_bus_stop_master_full.php` (Cursor 터미널)
- **검증:** `sql/v0.6-20_validation.sql` (9개 쿼리: 건수, 인덱스, EXPLAIN, match_method 분포)
- **인덱스:** `sql/v0.6-20_stop_master_indexes.sql` (stop_name 인덱스 확인, 추가 인덱스는 v0.6-21로 미룸)

## v0.6-21 운영 안전장치 강화 (LOW 승인 + alias 검증)

- **LOW 승인 게이트:** match_method='like_prefix'인 pending 후보는 **체크박스 "LOW(like_prefix) 확인함"** 체크 후에만 Approve 가능. 미체크 시 서버에서 차단, 에러: "LOW... 확인 체크 후 승인할 수 있습니다." (DB UPDATE 없음)
- **alias 등록 검증 강화:** (a) canonical_text가 stop_master에 **존재**해야만 저장. 없으면 차단, 에러: "alias blocked: canonical not found". (b) alias_text(정규화 후) **길이 <=2** 이면 저장 차단, 에러: "alias blocked: alias_text too short". 검증 통과 시에만 alias 저장 + live rematch.
- **검증:** `sql/v0.6-21_validation.sql` (8개 쿼리: LOW pending/approved, alias canonical 존재, alias_text 길이 분포, 회귀 확인)
- **매칭 로직/SoT 불변:** 승인/등록 단계 게이트만 강화.

## v0.6-22 PARSE_MATCH 품질 지표 저장

- **테이블 1개 추가:** `shuttle_parse_metrics` (job_id + route_label 단위로 매칭 품질 수치 저장). 인덱스: UNIQUE(parse_job_id, route_label), INDEX(source_doc_id, route_label).
- **저장 컬럼:** cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt. 분류는 v0.6-18과 동일(HIGH/MED/LOW/NONE).
- **run_job.php:** PARSE_MATCH 성공 후 DB 집계 쿼리로 metrics 계산 → UPSERT 저장 (PHP 루프 금지). 저장 실패 시 PARSE_MATCH는 성공 유지(비치명적).
- **doc.php:** "PARSE_MATCH Metrics (latest job)" 테이블 추가. latest_parse_job_id 기준 route별 품질 지표 표시. route_review는 기존 그대로 유지.
- **검증:** `sql/v0.6-22_validation.sql` (8개 쿼리: 테이블 존재, metrics row count, candidate 집계와 일치, UPSERT idempotent 확인).
- **SQL 실행:** Cursor PC 앱(Workbench)에서만. 매칭 로직/SoT 불변.

## 폴더 구조(확정)
- /public/admin : 웹에서 접근하는 관리자 페이지(실제 URL은 /admin 로 유지)
- /app/inc      : PHP 공통 코드(config/db/auth)
- /storage      : 업로드/워처/로그(웹 직접 접근 차단)
- /tools        : 배치 스크립트(웹 직접 접근 차단)
- /sql          : 스키마/시드(웹 직접 접근 차단)

## 로컬 설정 (DB 비밀값)

- DB 비밀값은 코드에 넣지 않음. `app/inc/config.local.php.example` 를 복사해 `config.local.php` 로 만들고, `DB_HOST`/`DB_USER`/`DB_PASS` 등 실제 값 입력. (`config.local.php` 는 .gitignore 대상.)

## XAMPP(htdocs)에서 실행
1) `C:\xampp\htdocs\gilime_mvp_01\` 에 이 폴더를 그대로 복사
2) 위 로컬 설정으로 `config.local.php` 생성
3) Apache 재시작
4) 접속
   - http://localhost/gilime_mvp_01/admin/login.php

## 왜 /public/admin 인데 URL은 /admin 인가?
- XAMPP 기본은 프로젝트 루트가 webroot라서,
  `.htaccess`로 `/admin/*` 요청을 `/public/admin/*`로 rewrite 합니다.
- 운영 서버에서는 DocumentRoot를 `/public`으로 두는 방식이 더 흔합니다(추후 전환).
