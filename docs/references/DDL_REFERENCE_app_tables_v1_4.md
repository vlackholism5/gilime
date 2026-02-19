# DDL Reference: app_* tables (v1.4)

GPT/구현 시 **테이블·컬럼명을 이 문서와 100% 동일하게** 사용하세요.  
실제 적용 DDL: `sql/releases/v1.4/schema/v1.4-02_schema.sql` + `sql/releases/v1.4/schema/v1.4-06_delivery_unique.sql` + `sql/releases/v1.4/schema/v1.4-07_route_label.sql`.

---

## 1. app_users

| 컬럼명 | 타입 | NULL | 기본값 | 비고 |
|--------|------|------|--------|------|
| id | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | PK |
| email | VARCHAR(255) | DEFAULT NULL | NULL | |
| password_hash | VARCHAR(255) | DEFAULT NULL | NULL | |
| display_name | VARCHAR(255) | DEFAULT NULL | NULL | |
| created_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| updated_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | |

- **PRIMARY KEY:** id  
- **INDEX:** idx_app_users_updated (updated_at)  
- **ENGINE:** InnoDB, CHARSET utf8mb4, COLLATE utf8mb4_unicode_ci  

---

## 2. app_user_sessions

| 컬럼명 | 타입 | NULL | 기본값 | 비고 |
|--------|------|------|--------|------|
| id | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | PK |
| user_id | BIGINT UNSIGNED | NOT NULL | — | FK → app_users(id) |
| session_token | VARCHAR(64) | NOT NULL | — | |
| expires_at | DATETIME | NOT NULL | — | |
| created_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |

- **PRIMARY KEY:** id  
- **UNIQUE:** uk_app_user_sessions_token (session_token)  
- **INDEX:** idx_app_user_sessions_user_id (user_id), idx_app_user_sessions_expires (expires_at)  
- **FK:** user_id → app_users(id) ON DELETE CASCADE  

---

## 3. app_subscriptions

| 컬럼명 | 타입 | NULL | 기본값 | 비고 |
|--------|------|------|--------|------|
| id | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | PK |
| user_id | BIGINT UNSIGNED | NOT NULL | — | FK → app_users(id) |
| target_type | VARCHAR(32) | NOT NULL | — | 예: 'route' |
| target_id | VARCHAR(64) | NOT NULL | — | 예: '1_R1' (doc_id_route_label) |
| alert_type | VARCHAR(32) | NOT NULL | 'strike,event,update' | |
| is_active | TINYINT | NOT NULL | 1 | 0=해제, 1=활성 |
| created_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |
| updated_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP ON UPDATE | |

- **PRIMARY KEY:** id  
- **UNIQUE:** uk_app_subscriptions_user_target (user_id, target_type, target_id)  
- **INDEX:** idx_app_subscriptions_user_id (user_id), idx_app_subscriptions_is_active (is_active)  
- **FK:** user_id → app_users(id) ON DELETE CASCADE  

---

## 4. app_alert_events

| 컬럼명 | 타입 | NULL | 기본값 | 비고 |
|--------|------|------|--------|------|
| id | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | PK |
| event_type | VARCHAR(32) | NOT NULL | — | strike / event / update 등 |
| title | VARCHAR(255) | NOT NULL | — | |
| body | TEXT | YES | NULL | |
| ref_type | VARCHAR(32) | DEFAULT NULL | NULL | 예: 'route' |
| ref_id | BIGINT UNSIGNED | DEFAULT NULL | NULL | 예: source_doc_id |
| route_label | VARCHAR(64) | DEFAULT NULL | NULL | v1.4-07 DDL로 추가 |
| content_hash | VARCHAR(64) | DEFAULT NULL | NULL | idempotent 삽입용 |
| published_at | DATETIME | NOT NULL | — | |
| created_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |

- **PRIMARY KEY:** id  
- **UNIQUE:** uk_app_alert_events_content_hash (content_hash)  
- **INDEX:** idx_app_alert_events_created_at (created_at), idx_app_alert_events_event_type (event_type), idx_app_alert_events_published (published_at), idx_app_alert_events_route_label (route_label)  

---

## 5. app_alert_deliveries

| 컬럼명 | 타입 | NULL | 기본값 | 비고 |
|--------|------|------|--------|------|
| id | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | PK |
| alert_event_id | BIGINT UNSIGNED | NOT NULL | — | FK → app_alert_events(id) |
| user_id | BIGINT UNSIGNED | NOT NULL | — | FK → app_users(id) |
| channel | VARCHAR(32) | NOT NULL | 'inapp' | 예: 'web' |
| status | VARCHAR(32) | NOT NULL | 'pending' | 예: 'shown' |
| sent_at | DATETIME | DEFAULT NULL | NULL | 노출/발송 시각 |
| created_at | DATETIME | NOT NULL | CURRENT_TIMESTAMP | |

- **PRIMARY KEY:** id  
- **UNIQUE:** uq_delivery_user_event_channel (user_id, alert_event_id, channel) — v1.4-06 DDL  
- **INDEX:** idx_app_alert_deliveries_event_id (alert_event_id), idx_app_alert_deliveries_user_id (user_id), idx_app_alert_deliveries_created_at (created_at)  
- **FK:** alert_event_id → app_alert_events(id), user_id → app_users(id) ON DELETE CASCADE  

---

## 지시 시 사용 예시 (GPT에 전달용)

- "app_alert_events에 INSERT할 때 컬럼은 id를 제외하고 event_type, title, body, ref_type, ref_id, route_label, content_hash, published_at, created_at 만 사용하세요. 테이블/컬럼명은 docs/DDL_REFERENCE_app_tables_v1_4.md 와 동일하게."
- "app_alert_deliveries에는 alert_event_id, user_id, channel, status, sent_at, created_at 를 넣으세요. PK는 id 뿐이고, UNIQUE는 (user_id, alert_event_id, channel) 입니다."

---

*문서 버전: v1.4-10 기준. DDL 변경 시 이 문서를 함께 수정하세요.*
