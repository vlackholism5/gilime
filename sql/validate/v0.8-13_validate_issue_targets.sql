-- v0.8-13 validate: issue_targets policy constraints
-- Expect 0 rows for each query.

SET NAMES utf8mb4;

SELECT id, issue_id, target_type
FROM issue_targets
WHERE target_type NOT IN ('route', 'line', 'station')
LIMIT 1000;

SELECT id, issue_id, policy_type
FROM issue_targets
WHERE policy_type NOT IN ('block', 'penalty', 'boost')
LIMIT 1000;

SELECT id, issue_id, severity
FROM issue_targets
WHERE severity NOT IN ('low', 'medium', 'high', 'critical')
LIMIT 1000;

SELECT t.id, t.issue_id
FROM issue_targets t
LEFT JOIN issues i ON i.id = t.issue_id
WHERE i.id IS NULL
LIMIT 1000;
