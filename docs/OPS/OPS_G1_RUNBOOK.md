# OPS — G1 API 운영 Runbook (최소)

G1 역·노선 API(E1/E2) 및 관련 뷰에 대한 **롤백/비활성화** 경로 1개 이상 명시. (스키마 변경 없는 범위 우선.)

---

## 1) 뷰 롤백 (vw_subway_station_lines_g1)

G1 API가 `vw_subway_station_lines_g1` 뷰에 의존하므로, 뷰를 제거하면 E1/E2 쿼리가 실패하고 API는 500 또는 빈 결과로 동작할 수 있음. 뷰를 되돌리려면:

```sql
DROP VIEW IF EXISTS vw_subway_station_lines_g1;
```

- **적용:** MySQL Workbench 또는 CLI에서 실행. 스키마 변경 없이 뷰만 제거.
- **복구:** 뷰 재생성 시 `sql/views/v0.8-07_view_station_lines_g1.sql` 실행.
- **참고:** [docs/OPS/OPS_DB_MIGRATIONS.md](OPS_DB_MIGRATIONS.md) File reference § v0.8-07에도 Rollback 문 구문 있음.

---

## 2) G1 API 비활성화 (임시, 가설)

장애 시 E1/E2 엔드포인트만 즉시 끄고 싶을 때, **스키마 변경 없이** 적용할 수 있는 옵션을 문서로만 정리. (현재 구현에 환경변수 분기 없음 — 추후 구현 시 참고용.)

- **가설:** 환경변수 예: `G1_API_DISABLED=1` 이 설정된 경우, `path === 'g1/station-lines/by-name'` 또는 `path === 'g1/station-lines/by-code'` 요청에 대해 503 Service Unavailable 또는 404 Not Found 반환.
- **구현 여부:** 별도 결정. 이 문서는 “운영자가 즉시 할 수 있는 조치” 후보로만 명시.
- **즉시 가능한 조치:** 뷰 롤백(위 1)으로 G1 쿼리 실패 유도 후, API는 500/에러 응답으로 동작하게 됨. 필요 시 웹서버/라우팅에서 해당 path 차단도 가능.

---

## 요약

| 조치 | 즉시 가능 여부 | 비고 |
|------|----------------|------|
| 뷰 롤백 (DROP VIEW) | 가능 | sql/views/v0.8-07 참고, 복구 시 동일 파일 재실행 |
| G1 path 비활성화(환경변수) | 가설, 미구현 | 필요 시 구현 후 이 문서 업데이트 |
