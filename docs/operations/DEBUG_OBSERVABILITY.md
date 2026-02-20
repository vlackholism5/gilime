# Trace + Debug Endpoint (Observability 최소구현)

## PHASE 0 — 탐색 결과 보고서

### 1) 구조 탐색 결과

| 항목 | 탐색 결과 |
|------|-----------|
| **API 라우팅 진입점** | 현재: `public/api/index.php`에서 `path=` 로 debug/ping, echo-trace, subscription/toggle, **g1/station-lines/by-name, by-code(E1/E2)** 디스패치. (과거: 진입점 없음 → v1.0에서 신규 추가.) |
| **라우트 정의** | `.htaccess`로 경로 매핑: `/admin/*` → `public/admin/*`, `/user/*` → `public/user/*`. 별도 `routes.php` 없음. |
| **핸들러/컨트롤러** | `public/admin/*.php`, `public/user/*.php` 각 파일이 페이지이자 핸들러(풀페이지 렌더 또는 폼 POST 후 리다이렉트). |
| **DB 연결** | `app/inc/auth/db.php` — `pdo()` 함수. `app/inc/config/config.php`에서 DB_* 상수/환경변수 로드. |
| **공통 유틸 추가 위치** | **`app/inc/lib/observability.php` (신규).** `api/lib/` 디렉터리는 없으므로 기존 `app/inc/lib/`에 두어 모든 진입점에서 require 가능하게 함. |

### 2) trace_id 공통 처리 레이어 결론

- **신규 API 진입점** `public/api/index.php`를 두고, 여기서 `path` 파라미터로 `debug/ping`, `debug/echo-trace` 디스패치.
- **trace_id 확보·로그·응답 첨부**는 `public/api/index.php` 진입 시 한 번만 수행. (기존 admin/user 페이지는 풀페이지/폼 방식이라 이번 v1.0에서는 API에만 적용.)
- **공통 유틸:** `app/inc/lib/observability.php` — `is_debug_enabled()`, `get_trace_id()`, `attach_trace_id_to_response()`, `safe_log()`. API뿐 아니라 향후 다른 PHP에서도 require 해서 사용 가능.

---

이하: GILIME_DEBUG 설정, trace 흐름, 검증 절차, 보안 주의.

## 1) GILIME_DEBUG=1 설정 방법

- **환경변수:** `GILIME_DEBUG=1` (또는 `true`, `yes`이면 debug ON).
- **.env 예시 (실제 값은 예시임):**
  ```env
  GILIME_DEBUG=1
  ```
- 운영 배포 시에는 반드시 `GILIME_DEBUG=0` 또는 미설정으로 OFF.

## 2) trace_id 흐름 (Front → API → Response)

- **요청:** 클라이언트가 `X-Trace-Id` 헤더 또는 JSON body `trace_id`로 전달. 없으면 서버에서 생성 (`trc_YYYYMMDD_HHMMSS_<6hex>`).
- **처리:** `public/api/index.php` 진입 시 trace_id 확보 → debug ON이면 `route_enter` 로그 → 핸들러 실행 → JSON 응답에 `trace_id` 필드 추가.
- **응답:** 모든 API JSON 응답에 `trace_id` 포함 (기존 키 유지, 호환).

## 3) 검증 절차 (캡쳐 없이)

1. **(debug on)** 브라우저/터미널에서 `GET /api/debug/ping` 확인:
   - `curl -i http://<host>/gilime_mvp_01/api/debug/ping` (GILIME_DEBUG=1 필요)
   - 응답: `{"ok":true,"ts":"...","db_ok":true|false,"trace_id":"..."}`
2. **echo-trace:** `curl -i -H "X-Trace-Id: trc_test_001" http://<host>/gilime_mvp_01/api/debug/echo-trace`
   - 응답: `{"ok":true,"trace_id":"trc_test_001"}`
3. 실제 기능 API 호출 시(해당 API가 추가된 경우) 응답 JSON의 `trace_id` 확인.
4. 서버 PHP error_log에서 `[TRACE <id>]` 검색 (debug on일 때만 로그 출력).

## 5) 프론트 계측 (가능한 범위)

- **trace-helper.js:** `public/admin/trace-helper.js` — API 호출 시 trace_id 부여용.
  - `GilimeTrace.createId()` — trace_id 생성
  - `GilimeTrace.fetch(url, options, existingTraceId)` — fetch에 X-Trace-Id 헤더 + body.trace_id 추가, `window.__GILIME_DEBUG__` 일 때 console.log
  - 서버 렌더 페이지에서 debug 플래그 전달: PHP에서 `window.__GILIME_DEBUG__ = <?= is_debug_enabled() ? 'true' : 'false' ?>;` 등으로 주입 가능 (필요 시 해당 페이지에만 require observability 후 출력).

---

## 6) 기능 1개 검증 절차 (Subscribe/Unsubscribe)

### 사전 조건
- `GILIME_DEBUG=1` 설정
- 브라우저에서 `/user/routes.php` 접속 가능
- route 1개 이상 존재

### 검증 절차 (텍스트 기반, 캡쳐 불필요)

1. **브라우저 콘솔 확인 (F12 → Console)**
   - `/user/routes.php` 페이지 로드 후 "Subscribe" 또는 "Unsubscribe" 버튼 클릭.
   - 콘솔에서 다음 로그 확인:
     ```
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] click {action: "subscribe", doc_id: 1, route_label: "R1"}
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] request {"url":"..."}
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] response {"status":200}
     ```
   - trace_id는 모두 동일해야 함.

2. **서버 error_log 확인**
   - PHP error_log(XAMPP: `C:\xampp\apache\logs\error.log` 또는 설정된 경로)에서 같은 trace_id로 검색:
     ```
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] route_enter {"method":"POST","path":"subscription/toggle"}
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] handler_enter {"user_id":1,"action":"subscribe","doc_id":1,"route_label":"R1"}
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] before_db {"target_id":"1_R1","is_active":1}
     [TRACE trc_YYYYMMDD_HHMMSS_xxxxxx] after_db_ok {"affected_rows":1}
     ```

3. **DB 트랜잭션 결과 확인 (Workbench 또는 터미널)**
   - trace_id로 검증한 후, DB에서 실제 구독 상태 확인:
     ```sql
     SELECT user_id, target_id, is_active, updated_at
     FROM app_subscriptions
     WHERE user_id = <your_user_id> AND target_id = '<doc_id>_<route_label>'
     ORDER BY updated_at DESC
     LIMIT 1;
     ```
   - `is_active`가 subscribe 시 1, unsubscribe 시 0인지 확인.
   - `updated_at`이 클릭 시각과 일치하는지 확인.

### 결론
위 3단계(콘솔 → 서버 로그 → DB)에서 같은 trace_id로 추적 가능하면, **캡쳐 없이도 클릭→API→DB 증거 체인이 완성**되었음을 확인할 수 있습니다.

## 4) 보안 주의

- **운영에서는 GILIME_DEBUG=0 유지.** debug endpoint는 OFF 시 404/403.
- **민감정보 로깅 금지:** 비밀번호/키/토큰/Authorization/개인정보는 safe_log에서 마스킹·요약만 하거나 기록하지 않음.
