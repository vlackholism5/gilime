# Observability baseline (v1.5) — no new tables

MVP2.5에서 추적하는 최소 ops 신호와, **기존 테이블만** 사용하는 증거 저장 방식을 정리합니다. 전용 ops_events 테이블은 v1.5에서 도입하지 않습니다.

---

## 1. What we track now (MVP2.5)

| 신호 | 설명 |
|------|------|
| **(a) subscribe toggle** | 사용자가 routes.php에서 Subscribe/Unsubscribe 클릭 |
| **(b) alert page render → delivery write** | /user/alerts.php 로드 시, 실제로 렌더된 이벤트에 대해 app_alert_deliveries 기록 시도 |
| **(c) alert review click** | (선택) 알림 행의 Review 링크 클릭. 현재 별도 추적 없음(확인 필요). |

---

## 2. Where we store evidence NOW (no new tables)

- **Primary evidence:** `app_alert_deliveries` 행  
  - channel='web', status='shown', sent_at=NOW()  
  - “이 사용자가 이 이벤트를 웹에서 노출했다”는 증거.
- **Subscribe toggle:**  
  - DB: `app_subscriptions`의 is_active·updated_at 변경으로 간접 확인 가능.  
  - 추가로 PHP `error_log`에 한 줄 기록(예: `OPS subscribe_toggle user_id=... target_id=... is_active=...`).  
  - 기존 admin audit 로그 존재 여부는 **확인 필요**. 없으면 error_log만 사용.

---

## 3. Metrics definitions (approximate)

| 지표 | 정의 | 한계 |
|------|------|------|
| **delivery_write_rate** | (기록 시도된 delivery 수) / (렌더된 알림 수). INSERT 시도 vs 실제 INSERT는 UNIQUE로 인해 재방문 시 UPDATE만 될 수 있음. | “시도 vs 삽입” 구분 없이, “렌더된 건당 1회 기록 시도”로 근사. 정확한 rate는 별도 집계 없음. |
| **active_subscriptions** | `SELECT COUNT(*) FROM app_subscriptions WHERE is_active = 1` | 정확. |

---

## 4. Limitations

- **v1.5에서는 전용 ops_events 테이블을 두지 않음.**  
  증거는 `app_alert_deliveries` + (선택) `error_log` + `app_subscriptions` 변경으로만 확보.
- error_log는 환경별로 보존/로테이션 정책이 다름. 장기 메트릭 저장소로 사용 불가(확인 필요).
- Review 링크 클릭 수 등 세부 이벤트는 수집하지 않음(확인 필요 / out of scope).
