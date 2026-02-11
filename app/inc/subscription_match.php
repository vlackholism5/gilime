<?php
declare(strict_types=1);
/**
 * v1.7-08: Subscription alert_type matching helper.
 * CSV(예: 'strike,event,update')에서 event_type이 정확히 포함되는지 판별.
 * SQL은 FIND_IN_SET 사용 권장; 이 헬퍼는 PHP 측 필터/테스트용.
 */

function subscription_allows_event_type(string $csv, string $eventType): bool {
  $eventType = strtolower(trim($eventType));
  if ($eventType === '') {
    return false;
  }
  $tokens = array_map('trim', explode(',', $csv));
  $tokens = array_map('strtolower', $tokens);
  return in_array($eventType, $tokens, true);
}
