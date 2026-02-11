# Alert reference contract (v1.5)

`app_alert_events`의 ref_type / ref_id / route_label 사용 규칙. 스키마 변경 없이 데이터 규칙만 정리합니다.

---

## 1. Allowed ref_type values (string)

| ref_type | 의미 | ref_id | route_label |
|----------|------|--------|-------------|
| `'route'` | 특정 노선(문서+노선) | source_doc_id (NOT NULL) | 노선 라벨 (NOT NULL) |
| `'doc'`   | 문서 단위 | source_doc_id (NOT NULL) | NULL |
| NULL     | 참조 없음 | NULL 허용 | NULL 허용 |

---

## 2. Requirements per type

- **ref_type = 'route'**  
  - ref_id NOT NULL, route_label NOT NULL.  
  - 위반 행은 데이터 정합성 위반(검증 쿼리로 탐지).
- **ref_type = 'doc'**  
  - ref_id NOT NULL.  
  - route_label은 NULL.
- **ref_type = NULL (또는 빈 문자열 등)**  
  - ref_id, route_label NULL 허용.  
  - (확인 필요: 빈 문자열 저장 정책.)

---

## 3. Review link (/user/alerts.php)

- **ref_type = 'route'**  
  - `/admin/route_review.php?source_doc_id={ref_id}&route_label={route_label}&quick_mode=1&show_advanced=0`
- **ref_type = 'doc'**  
  - `/admin/doc.php?id={ref_id}`  
  - (admin doc.php는 쿼리 파라미터 `id` 사용. 확인 필요: 파라미터명이 id인지 source_doc_id인지.)
- **그 외 (ref_type NULL 등)**  
  - Review 링크 없음(— 표시).

---

## 4. Scripts that insert app_alert_events

- **run_alert_ingest_stub.php**  
  - ref_type='route', ref_id=1, route_label='R1' 로 고정(계약 준수).
- **run_alert_generate_from_metrics.php**  
  - doc/route 단위 생성 시 ref_type='route', ref_id=source_doc_id, route_label=route_label.
- **E2E / 기타 INSERT**  
  - 위 규칙에 맞춰 ref_type/ref_id/route_label 설정 권장.

---

## 5. Validation

- `sql/v1.5-02_validation.sql` (read-only):  
  - ref_type='route' 이면서 ref_id IS NULL OR route_label IS NULL 인 행  
  - ref_type='doc' 이면서 ref_id IS NULL 인 행  
  - 조회 후 수정은 주석으로 제안만, 기본은 UPDATE 없음.
