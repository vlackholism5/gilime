# OPS — API v1 검증 (Phase B)

## Purpose

API v1 엔드포인트 curl 시나리오 및 검증 체크리스트. SoT: docs/SOT/11_API_V1_IMPL_PLAN.md.

Base URL 예: `http://localhost/gilime_mvp_01/public/api/index.php` (path 쿼리로 라우팅).

---

## 사전 조건

- v0.8-03 migration 적용: `sql/migrations/v0.8-03_gilime_admin_core.sql`
- Admin API 호출 시 `X-ADMIN-TOKEN` 헤더 필요 (미제공 시 403)

---

## curl 시나리오

### v1/ping (liveness)

```bash
curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/ping"
```

기대: 200, `ok: true`, `data.pong: true`, `meta.trace_id`, `meta.server_time`

### v1/issues (User — 목록)

```bash
curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/issues"
```

기대: 200, `data.issues` (배열)

### v1/admin/issues (Admin — 목록, X-ADMIN-TOKEN 필요)

```bash
curl -s -H "X-ADMIN-TOKEN: your-token" "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/issues"
```

기대: 200, `data.issues`. 토큰 없으면 403.

### v1/admin/issues (Admin — 생성)

```bash
curl -s -X POST -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: your-token" \
  -d "{\"title\":\"Test issue\",\"severity\":\"medium\"}" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/issues"
```

기대: 201, `data.id`, `data.status: "draft"`

### v1/admin/issues/{id}/activate | deactivate

```bash
# 활성화 (title, start_at, end_at 있으면 200)
curl -s -X POST -H "X-ADMIN-TOKEN: your-token" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/issues/1/activate"

# 비활성화
curl -s -X POST -H "X-ADMIN-TOKEN: your-token" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/issues/1/deactivate"
```

### v1/admin/shuttles/routes (목록 / 생성)

```bash
# 목록
curl -s -H "X-ADMIN-TOKEN: your-token" "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/shuttles/routes"

# 생성
curl -s -X POST -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: your-token" \
  -d "{\"issue_id\":1,\"route_name\":\"Shuttle A\"}" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/shuttles/routes"
```

### v1/admin/shuttles/routes/{id}/stops (PUT — 정류장 순서)

```bash
curl -s -X PUT -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: your-token" \
  -d "{\"stops\":[{\"stop_id\":\"S1\",\"stop_name\":\"A\"},{\"stop_id\":\"S2\",\"stop_name\":\"B\"}]}" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/admin/shuttles/routes/1/stops"
```

기대: 200. 2미만 정류장 또는 중복 stop_id 시 400.

### v1/routes/search (스코어링 v1)

```bash
curl -s -X POST -H "Content-Type: application/json" \
  -d "{\"sort\":\"best\",\"candidates\":[{\"total_min\":30,\"transfers\":1,\"walk_m\":200,\"segments\":[{\"duration_min\":20,\"issue_exposed\":true,\"issue_severity\":\"medium\"}]}]}" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/routes/search"
```

기대: 200, `data.routes` 각 항목에 `score`, `issue_impact` 포함, `sort` 반영.

### v1/guidance/start, v1/subscriptions (stub)

```bash
curl -s -X POST -H "Content-Type: application/json" -d "{}" \
  "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/guidance/start"
curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=v1/subscriptions"
```

기대: 201 / 200, stub 응답.

---

## 스코어링 검증 스크립트

```bash
php scripts/php/verify_scoring_model_v1.php
```

동일 입력 → 동일 출력 확인. Test Set A~D 정답표는 docs/SOT/Route_Scoring_Simulation_Model_v1.md 확정 후 비교.

---

## 체크리스트

- [ ] v0.8-03 migration 적용 후 v0.8-11 검증 쿼리 0건
- [ ] v1/ping 200
- [ ] v1/issues GET 200
- [ ] v1/admin/* 미토큰 시 403
- [ ] v1/admin/issues CRUD + activate/deactivate 200/201/400
- [ ] v1/admin/shuttles/routes + stops PUT + activate/deactivate
- [ ] v1/routes/search 응답에 score, issue_impact 포함
- [ ] verify_scoring_model_v1.php 실행 성공
