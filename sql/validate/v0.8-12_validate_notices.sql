-- v0.8-12 validate: notices visibility and data quality
-- Expectation: all queries return 0 rows in healthy state.

SET NAMES utf8mb4;

-- 1) invalid category
SELECT id, category
FROM notices
WHERE category NOT IN ('notice', 'event')
LIMIT 1000;

-- 2) invalid status
SELECT id, status
FROM notices
WHERE status NOT IN ('draft', 'published', 'archived')
LIMIT 1000;

-- 3) published rows missing published_at
SELECT id, title, status, published_at
FROM notices
WHERE status = 'published' AND published_at IS NULL
LIMIT 1000;

-- 4) invalid visibility window
SELECT id, title, starts_at, ends_at
FROM notices
WHERE starts_at IS NOT NULL AND ends_at IS NOT NULL AND starts_at > ends_at
LIMIT 1000;
