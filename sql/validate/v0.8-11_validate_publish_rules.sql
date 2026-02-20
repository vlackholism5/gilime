-- v0.8-11: Validate publish rules for issues & shuttle_routes. Run after v0.8-03 migration.
-- SoT: docs/SOT/Gilime_Admin_ERD_MVP_v1.md. Expected: 0 rows for each query when data is valid.

-- -----------------------------------------------------------------------------
-- 1) shuttle_stops: stop_order must be consecutive (1, 2, 3, ...) per route
--    Returns rows where stop_order has a gap.
-- -----------------------------------------------------------------------------
SELECT s.shuttle_route_id, s.stop_order, s.stop_id,
       (SELECT MIN(ss.stop_order) FROM shuttle_stops ss WHERE ss.shuttle_route_id = s.shuttle_route_id AND ss.stop_order > s.stop_order) AS next_order,
       (SELECT MIN(ss.stop_order) FROM shuttle_stops ss WHERE ss.shuttle_route_id = s.shuttle_route_id AND ss.stop_order > s.stop_order) - s.stop_order AS gap
FROM shuttle_stops s
WHERE (SELECT MIN(ss.stop_order) FROM shuttle_stops ss WHERE ss.shuttle_route_id = s.shuttle_route_id AND ss.stop_order > s.stop_order) IS NOT NULL
  AND (SELECT MIN(ss.stop_order) FROM shuttle_stops ss WHERE ss.shuttle_route_id = s.shuttle_route_id AND ss.stop_order > s.stop_order) - s.stop_order > 1;

-- -----------------------------------------------------------------------------
-- 2) shuttle_stops: duplicate stop_id within same route (UNIQUE prevents insert; this lists any anomaly)
--    Returns routes that have duplicate stop_id (should be 0 due to uk_shuttle_stops_route_stop).
-- -----------------------------------------------------------------------------
SELECT shuttle_route_id, stop_id, COUNT(*) AS cnt
FROM shuttle_stops
GROUP BY shuttle_route_id, stop_id
HAVING COUNT(*) > 1;

-- -----------------------------------------------------------------------------
-- 3) issues: active issues must have required fields (title, start_at, end_at)
--    Returns active issues with missing required data.
-- -----------------------------------------------------------------------------
SELECT id, title, status, start_at, end_at
FROM issues
WHERE status = 'active'
  AND (TRIM(COALESCE(title, '')) = '' OR start_at IS NULL OR end_at IS NULL);

-- -----------------------------------------------------------------------------
-- 4) shuttle_routes: active routes must have at least 2 stops
--    Returns active shuttle_routes with fewer than 2 stops.
-- -----------------------------------------------------------------------------
SELECT r.id, r.issue_id, r.route_name, r.status, COUNT(s.id) AS stop_count
FROM shuttle_routes r
LEFT JOIN shuttle_stops s ON s.shuttle_route_id = r.id
WHERE r.status = 'active'
GROUP BY r.id, r.issue_id, r.route_name, r.status
HAVING COUNT(s.id) < 2;
