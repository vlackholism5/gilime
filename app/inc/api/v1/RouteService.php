<?php
declare(strict_types=1);
/**
 * API v1 — Route search with scoring v1. SoT: docs/SOT/Route_Scoring_Model.md, Route_Scoring_Simulation_Model_v1.md
 */

const ROUTE_SCORE_ALPHA = 5;
const ROUTE_SCORE_BETA  = 8;
const ROUTE_SCORE_GAMMA = 3;

/** severity weight: low=1, medium=3, high=6, critical=10 */
function route_severity_weight(string $s): float {
  return match (strtolower($s)) {
    'low' => 1.0,
    'medium' => 3.0,
    'high' => 6.0,
    'critical' => 10.0,
    default => 3.0,
  };
}

/**
 * Compute IssueImpact for one candidate: Σ(severity_weight × duration_ratio) per segment.
 * @param array<int, array{duration_min?: float, issue_exposed?: bool, issue_severity?: string}> $segments
 */
function route_compute_issue_impact(array $segments, float $total_min): float {
  if ($total_min <= 0) return 0.0;
  $sum = 0.0;
  foreach ($segments as $seg) {
    $dur = (float)($seg['duration_min'] ?? 0);
    if (($seg['issue_exposed'] ?? false) && $dur > 0) {
      $ratio = $dur / $total_min;
      $sum += route_severity_weight((string)($seg['issue_severity'] ?? 'medium')) * $ratio;
    }
  }
  return $sum;
}

/**
 * Score = T + α·TransferPenalty + β·IssueImpact + γ·WalkCost
 * TransferPenalty = (transfers)^1.2, WalkCost = walk_m/400
 */
function route_compute_score(float $total_min, int $transfers, float $walk_m, float $issue_impact): float {
  $tp = $transfers > 0 ? pow((float)$transfers, 1.2) : 0.0;
  $wc = $walk_m / 400.0;
  return $total_min + ROUTE_SCORE_ALPHA * $tp + ROUTE_SCORE_BETA * $issue_impact + ROUTE_SCORE_GAMMA * $wc;
}

function route_penalty_minutes(string $severity): float {
  return match (strtolower($severity)) {
    'critical' => 15.0,
    'high' => 10.0,
    'medium' => 6.0,
    'low' => 3.0,
    default => 6.0,
  };
}

function route_boost_ratio(string $severity): float {
  return match (strtolower($severity)) {
    'critical' => 0.25,
    'high' => 0.20,
    'medium' => 0.15,
    'low' => 0.08,
    default => 0.10,
  };
}

/**
 * @return array{
 *   blocked: array<string, array<int, string>>,
 *   penalty: array<string, array<string, float>>,
 *   boost: array<string, array<string, float>>
 * }
 */
function route_load_issue_policy_set(int $issue_id): array {
  $empty = [
    'blocked' => ['route' => [], 'line' => [], 'station' => []],
    'penalty' => ['route' => [], 'line' => [], 'station' => []],
    'boost' => ['route' => [], 'line' => [], 'station' => []],
  ];
  if ($issue_id <= 0) return $empty;
  $pdo = pdo();
  $stmt = $pdo->prepare("
    SELECT target_type, target_id, policy_type, severity
    FROM issue_targets
    WHERE issue_id = ?
  ");
  try {
    $stmt->execute([$issue_id]);
  } catch (Throwable $e) {
    return $empty;
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $type = strtolower(trim((string)($r['target_type'] ?? '')));
    $targetId = trim((string)($r['target_id'] ?? ''));
    $policy = strtolower(trim((string)($r['policy_type'] ?? '')));
    $severity = trim((string)($r['severity'] ?? 'medium'));
    if (!in_array($type, ['route', 'line', 'station'], true) || $targetId === '') continue;
    if ($policy === 'block') {
      $empty['blocked'][$type][] = $targetId;
    } elseif ($policy === 'penalty') {
      $empty['penalty'][$type][$targetId] = route_penalty_minutes($severity);
    } elseif ($policy === 'boost') {
      $empty['boost'][$type][$targetId] = route_boost_ratio($severity);
    }
  }
  return $empty;
}

/**
 * @param array<string,mixed> $candidate
 */
function route_collect_candidate_keys(array $candidate): array {
  $routeIds = [];
  $lineCodes = [];
  $stationIds = [];
  if (!empty($candidate['route_id'])) $routeIds[] = (string)$candidate['route_id'];
  if (!empty($candidate['line_code'])) $lineCodes[] = (string)$candidate['line_code'];
  if (!empty($candidate['station_id'])) $stationIds[] = (string)$candidate['station_id'];
  $segments = isset($candidate['segments']) && is_array($candidate['segments']) ? $candidate['segments'] : [];
  foreach ($segments as $seg) {
    if (!is_array($seg)) continue;
    if (!empty($seg['route_id'])) $routeIds[] = (string)$seg['route_id'];
    if (!empty($seg['line_code'])) $lineCodes[] = (string)$seg['line_code'];
    if (!empty($seg['from_station_cd'])) $stationIds[] = (string)$seg['from_station_cd'];
    if (!empty($seg['to_station_cd'])) $stationIds[] = (string)$seg['to_station_cd'];
    if (!empty($seg['station_id'])) $stationIds[] = (string)$seg['station_id'];
  }
  return [
    'route' => array_values(array_unique($routeIds)),
    'line' => array_values(array_unique($lineCodes)),
    'station' => array_values(array_unique($stationIds)),
  ];
}

/**
 * @param array<string,mixed> $candidate
 * @param array<string,mixed> $policySet
 * @return array{blocked: bool, penalty: float, boost: float}
 */
function route_apply_policy_to_candidate(array $candidate, array $policySet): array {
  $keys = route_collect_candidate_keys($candidate);
  foreach (['route', 'line', 'station'] as $type) {
    foreach ($keys[$type] as $id) {
      if (in_array($id, $policySet['blocked'][$type] ?? [], true)) {
        return ['blocked' => true, 'penalty' => 0.0, 'boost' => 0.0];
      }
    }
  }
  $penalty = 0.0;
  $boost = 0.0;
  foreach (['route', 'line', 'station'] as $type) {
    foreach ($keys[$type] as $id) {
      if (isset($policySet['penalty'][$type][$id])) {
        $penalty += (float)$policySet['penalty'][$type][$id];
      }
      if (isset($policySet['boost'][$type][$id])) {
        $boost = max($boost, (float)$policySet['boost'][$type][$id]);
      }
    }
  }
  return ['blocked' => false, 'penalty' => $penalty, 'boost' => $boost];
}

function api_v1_routes_search_dispatch(string $trace_id): void {
  $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(api_v1_error('METHOD_NOT_ALLOWED', 'POST required', $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  $raw = file_get_contents('php://input');
  $body = json_decode($raw ?: '{}', true) ?: [];
  $candidates = isset($body['candidates']) && is_array($body['candidates']) ? $body['candidates'] : [];
  $sort = trim((string)($body['sort'] ?? 'best'));
  $issue_context_id = (int)($body['issue_context_id'] ?? 0);
  if (!in_array($sort, ['best', 'fastest', 'least_issue', 'least_transfer'], true)) $sort = 'best';
  $policySet = $issue_context_id > 0 ? route_load_issue_policy_set($issue_context_id) : null;

  $routes = [];
  foreach ($candidates as $c) {
    $total_min = (float)($c['total_min'] ?? 0);
    $transfers = (int)($c['transfers'] ?? 0);
    $walk_m = (float)($c['walk_m'] ?? 0);
    $segments = isset($c['segments']) && is_array($c['segments']) ? $c['segments'] : [];
    $policyPenalty = 0.0;
    $policyBoost = 0.0;
    if (is_array($policySet)) {
      $policyEval = route_apply_policy_to_candidate(is_array($c) ? $c : [], $policySet);
      if ($policyEval['blocked']) continue;
      $policyPenalty = (float)$policyEval['penalty'];
      $policyBoost = (float)$policyEval['boost'];
      $total_min += $policyPenalty;
      if ($policyBoost > 0) {
        $total_min = max(1.0, $total_min * (1.0 - $policyBoost));
      }
    }
    $issue_impact = route_compute_issue_impact($segments, $total_min);
    $score = route_compute_score($total_min, $transfers, $walk_m, $issue_impact);
    $routes[] = [
      'issue_context_id' => $issue_context_id > 0 ? $issue_context_id : null,
      'total_min' => $total_min,
      'transfers' => $transfers,
      'walk_m' => $walk_m,
      'segments' => $segments,
      'score' => round($score, 4),
      'issue_impact' => round($issue_impact, 4),
      'policy_penalty' => round($policyPenalty, 4),
      'policy_boost' => round($policyBoost, 4),
    ];
  }

  usort($routes, function ($a, $b) use ($sort) {
    switch ($sort) {
      case 'fastest': return $a['total_min'] <=> $b['total_min'];
      case 'least_issue': return $a['issue_impact'] <=> $b['issue_impact'];
      case 'least_transfer': return $a['transfers'] <=> $b['transfers'];
      default: return $a['score'] <=> $b['score'];
    }
  });

  http_response_code(200);
  echo json_encode(api_v1_ok(['routes' => $routes, 'sort' => $sort], $trace_id), JSON_UNESCAPED_UNICODE);
}
