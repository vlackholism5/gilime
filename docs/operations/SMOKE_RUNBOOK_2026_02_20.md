# SMOKE RUNBOOK 2026-02-20

## 목적
- 2/20 MVP 완주 시나리오를 실제 화면에서 빠르게 점검한다.
- 점검 직후 PM 공유 문서에 결과를 그대로 붙여넣는다.

## 사전 준비
- 로컬 서버 기동: Apache/MySQL (XAMPP)
- 관리자 계정 로그인 가능
- 사용자 계정 로그인 가능
- 테스트 PDF 1건 준비

## 자동 사전 점검 (Cursor/터미널)
- 명령:
  - `php scripts/php/db_smoke_check.php`
- 현재 기준 결과:
  - `source_doc=1`
  - `stop_candidate=45`
  - `route_stop_active=0`
  - `published_events=15`
  - `delivery_rows=20`
  - `delivery_duplicates=0`
- 해석:
  - `route_stop_active=0`이면 A4(승격) 수행 후 A5(사용자 경로안내)를 확인해야 함.

## A. 완주 시나리오 (운영 성공 흐름)

### Step A1. PDF 업로드
- URL: `http://localhost/gilime_mvp_01/public/admin/upload_pdf.php`
- 액션: PDF 업로드 실행
- 기대: `source_doc_id` 생성 및 문서 상세 이동 버튼 노출

### Step A2. 파싱/매칭 실행
- URL: 문서 상세(`admin/doc.php?id={source_doc_id}`)
- 액션: `파싱/매칭 실행` 클릭
- 기대: 성공 플래시 표시, 최신 파싱 Job ID 갱신

### Step A3. 노선 검수
- URL: `admin/route_review.php?source_doc_id={id}&route_label={label}`
- 액션: 후보 승인/거절/별칭 등록 일부 수행
- 기대: 후보 상태 변경 반영, stale 차단 정상 동작

### Step A4. 승격
- URL: 동일 route_review 페이지
- 액션: `승인 후보를 Route Stops로 승격` 실행
- 기대: Route Stops 테이블에 반영, PROMOTE 이력 증가

### Step A5. 사용자 경로안내 확인
- URL: `http://localhost/gilime_mvp_01/public/user/journey.php`
- 액션: 구독 노선 선택 후 정류장 순서 확인
- 기대: 순서/정류장ID/정류장명 표시
- 대안: 마이노선 → [경로 안내] 버튼으로 journey 진입

### Step A6. 이슈 기반 길찾기 (v1.8)
- URL: `http://localhost/gilime_mvp_01/public/user/home.php`
- 액션: 긴급 이슈 Top3 → [이슈 기반 길찾기] 클릭
- 기대: route_finder에 이슈 컨텍스트 배너 표시, 임시 셔틀 포함 기본 체크
- 액션: 출발/도착 입력 후 경로 찾기
- 기대: 경로 결과에 "임시 셔틀 구간" 뱃지 표시

## B. 실패/복구 시나리오

### Step B1. 실패 유도
- 액션: 경로 문제 있는 문서 또는 비정상 파일로 파싱 실행
- 기대: 문서 상세에 실패 경고 박스 + 오류 코드 표시

### Step B2. 복구
- 액션: 정상 파일 기준 재실행
- 기대: 성공 플래시 표시, 실패 상태 해소

## C. 감사/운영 확인
- URL: `admin/alert_event_audit.php`
- 기대: 필터 적용/전달 이력 조회 정상

## D. 점검 기록(복붙용)

```text
[A1] PDF 업로드: PASS/FAIL - 메모:
[A2] 파싱/매칭: PASS/FAIL - 메모:
[A3] 노선 검수: PASS/FAIL - 메모:
[A4] 승격: PASS/FAIL - 메모:
[A5] 사용자 경로안내: PASS/FAIL - 메모:
[A6] 이슈 기반 길찾기: PASS/FAIL - 메모:
[B1] 실패 유도: PASS/FAIL - 메모:
[B2] 복구: PASS/FAIL - 메모:
[C] 감사 화면: PASS/FAIL - 메모:
```

## E. 최종 판정 기준
- A1~A6 모두 PASS + B1/B2 PASS + C PASS이면 최종 PASS.

## F. 검증 쿼리 (docs/releases/v1.7/RELEASE_GATE.md)

```sql
-- 최근 이벤트 10건
SELECT id, event_type, published_at, created_at
FROM app_alert_events
ORDER BY id DESC
LIMIT 10;

-- 이벤트별 전달 건수
SELECT alert_event_id, channel, status, COUNT(*) AS cnt
FROM app_alert_deliveries
GROUP BY alert_event_id, channel, status
ORDER BY alert_event_id DESC, channel, status;

-- 중복 체크 (0건이어야 정상)
SELECT alert_event_id, user_id, channel, COUNT(*) AS dup_cnt
FROM app_alert_deliveries
GROUP BY alert_event_id, user_id, channel
HAVING COUNT(*) > 1;
```
