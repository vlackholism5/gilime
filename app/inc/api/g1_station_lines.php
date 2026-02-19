<?php
declare(strict_types=1);
/**
 * G1 station-lines API: E1 (by-name), E2 (by-code). SoT: docs/SOT/09_API_CONTRACTS_G1.md, sql/validate/v0.8-10_api_query_bindings_g1.sql.
 */

/** E1/E2 shared SELECT (v0.8-10). Bind :station_name (E1) or :station_cd (E2) only. */
const G1_SQL_SELECT = "
  s.station_name,
  s.station_cd,
  NULLIF(TRIM(s.line_code), '') AS master_line_code,
  COALESCE(
    NULLIF(TRIM(v.line_codes_json), ''),
    CASE WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN JSON_ARRAY(TRIM(s.line_code)) ELSE JSON_ARRAY() END
  ) AS line_codes,
  CASE WHEN v.station_name IS NOT NULL THEN 'view' WHEN s.line_code IS NOT NULL AND TRIM(s.line_code) != '' THEN 'master' ELSE 'none' END AS line_codes_source,
  COALESCE(v.degree_edges, 0) AS degree_edges,
  JSON_OBJECT(
    'line_code_source', JSON_UNQUOTE(JSON_EXTRACT(s.meta_json, '$.line_code_source')),
    'line_code_candidates', COALESCE(JSON_EXTRACT(s.meta_json, '$.line_code_candidates'), NULL)
  ) AS meta
FROM subway_stations_master s
LEFT JOIN vw_subway_station_lines_g1 v ON v.station_name = s.station_name
";

/**
 * Lookup one station by name (E1) or code (E2). Returns row (assoc with line_codes array and meta object) and query_ms.
 * @return array{row: array<string,mixed>|null, query_ms: float}
 */
function g1_station_lines_lookup(PDO $pdo, string $endpoint, string $bind_key, string $bind_value): array {
  $is_by_name = ($endpoint === 'by-name' && $bind_key === 'station_name');
  $sql = 'SELECT ' . G1_SQL_SELECT . ($is_by_name ? ' WHERE s.station_name = :station_name ORDER BY s.station_cd LIMIT 1' : ' WHERE s.station_cd = :station_cd LIMIT 1');
  $t0 = microtime(true);
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$is_by_name ? ':station_name' : ':station_cd' => $bind_value]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $query_ms = (microtime(true) - $t0) * 1000;

  if ($row === false || $row === null) {
    return ['row' => null, 'query_ms' => $query_ms];
  }

  $line_codes = $row['line_codes'] ?? '';
  $decoded = is_string($line_codes) ? json_decode($line_codes, true) : $line_codes;
  $row['line_codes'] = is_array($decoded) ? $decoded : [];

  $meta = $row['meta'] ?? null;
  $meta_decoded = is_string($meta) ? json_decode($meta, true) : $meta;
  $row['meta'] = is_array($meta_decoded) ? $meta_decoded : ['line_code_source' => null, 'line_code_candidates' => null];

  return ['row' => $row, 'query_ms' => $query_ms];
}
