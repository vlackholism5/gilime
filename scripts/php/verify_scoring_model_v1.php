<?php
declare(strict_types=1);
/**
 * Verify route scoring model v1 — same formula as RouteService. SoT: docs/SOT/Route_Scoring_Simulation_Model_v1.md
 * Run: php scripts/php/verify_scoring_model_v1.php
 * Expected: output matches Test Set A~D when expected_* are filled in.
 */

const ALPHA = 5;
const BETA  = 8;
const GAMMA = 3;

function severity_weight(string $s): float {
  return match (strtolower($s)) {
    'low' => 1.0, 'medium' => 3.0, 'high' => 6.0, 'critical' => 10.0,
    default => 3.0,
  };
}

function compute_issue_impact(array $segments, float $total_min): float {
  if ($total_min <= 0) return 0.0;
  $sum = 0.0;
  foreach ($segments as $seg) {
    $dur = (float)($seg['duration_min'] ?? 0);
    if (($seg['issue_exposed'] ?? false) && $dur > 0) {
      $ratio = $dur / $total_min;
      $sum += severity_weight((string)($seg['issue_severity'] ?? 'medium')) * $ratio;
    }
  }
  return $sum;
}

function compute_score(float $total_min, int $transfers, float $walk_m, float $issue_impact): float {
  $tp = $transfers > 0 ? pow((float)$transfers, 1.2) : 0.0;
  $wc = $walk_m / 400.0;
  return $total_min + ALPHA * $tp + BETA * $issue_impact + GAMMA * $wc;
}

function penalty_minutes(string $severity): float {
  return match (strtolower($severity)) {
    'critical' => 15.0, 'high' => 10.0, 'medium' => 6.0, 'low' => 3.0,
    default => 6.0,
  };
}

function boost_ratio(string $severity): float {
  return match (strtolower($severity)) {
    'critical' => 0.25, 'high' => 0.20, 'medium' => 0.15, 'low' => 0.08,
    default => 0.10,
  };
}

// Test Set A~D: each row = [ total_min, transfers, walk_m, segments ]
$test_sets = [
  'A' => [
    'total_min' => 30,
    'transfers' => 1,
    'walk_m' => 200,
    'segments' => [
      ['duration_min' => 5, 'issue_exposed' => false],
      ['duration_min' => 20, 'issue_exposed' => true, 'issue_severity' => 'medium'],
      ['duration_min' => 5, 'issue_exposed' => false],
    ],
  ],
  'B' => [
    'total_min' => 35,
    'transfers' => 2,
    'walk_m' => 400,
    'segments' => [
      ['duration_min' => 10, 'issue_exposed' => true, 'issue_severity' => 'low'],
      ['duration_min' => 15, 'issue_exposed' => false],
      ['duration_min' => 10, 'issue_exposed' => true, 'issue_severity' => 'high'],
    ],
  ],
  'C' => [
    'total_min' => 25,
    'transfers' => 0,
    'walk_m' => 800,
    'segments' => [
      ['duration_min' => 25, 'issue_exposed' => false],
    ],
  ],
  'D' => [
    'total_min' => 40,
    'transfers' => 1,
    'walk_m' => 0,
    'segments' => [
      ['duration_min' => 20, 'issue_exposed' => true, 'issue_severity' => 'critical'],
      ['duration_min' => 20, 'issue_exposed' => false],
    ],
  ],
];

echo "Route Scoring Model v1 — verification\n";
echo "α=" . ALPHA . " β=" . BETA . " γ=" . GAMMA . "\n\n";

$results = [];
foreach ($test_sets as $label => $c) {
  $ii = compute_issue_impact($c['segments'], (float)$c['total_min']);
  $score = compute_score((float)$c['total_min'], (int)$c['transfers'], (float)$c['walk_m'], $ii);
  $results[$label] = ['score' => round($score, 4), 'issue_impact' => round($ii, 4)];
  echo "  $label: score=" . $results[$label]['score'] . " issue_impact=" . $results[$label]['issue_impact'] . "\n";
}

echo "\n-- Issue policy smoke (BLOCK/PENALTY/BOOST) --\n";
$base = ['total_min' => 34.0, 'transfers' => 1, 'walk_m' => 300.0, 'segments' => [['duration_min' => 34, 'issue_exposed' => false]]];
$baseII = compute_issue_impact($base['segments'], $base['total_min']);
$baseScore = compute_score($base['total_min'], $base['transfers'], $base['walk_m'], $baseII);
echo "base score=" . round($baseScore, 4) . "\n";

$penaltyTotal = $base['total_min'] + penalty_minutes('high');
$penaltyScore = compute_score($penaltyTotal, $base['transfers'], $base['walk_m'], compute_issue_impact($base['segments'], $penaltyTotal));
echo "penalty(high) score=" . round($penaltyScore, 4) . "\n";

$boostTotal = max(1.0, $base['total_min'] * (1 - boost_ratio('high')));
$boostScore = compute_score($boostTotal, $base['transfers'], $base['walk_m'], compute_issue_impact($base['segments'], $boostTotal));
echo "boost(high) score=" . round($boostScore, 4) . "\n";

echo "block(high) => candidate excluded\n";
echo "\nDone. Compare above to docs/SOT/Route_Scoring_Simulation_Model_v1.md and docs/SOT/ROUTING_ISSUE_WEIGHTING_MVP.md.\n";
