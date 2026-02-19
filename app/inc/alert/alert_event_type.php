<?php
declare(strict_types=1);
/**
 * v1.8: app_alert_events.event_type 매핑 — 이슈 필터·표시
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 * @see docs/ux/ALERT_ISSUE_MAPPING_v1_8.md
 *
 * DB event_type: strike, event, update (실제 스크립트/Ingest 기준)
 * 이슈 필터(UI): 긴급, 운행중단, 행사, 공지
 */

/** 이슈 필터 옵션 → DB event_type 배열 */
const ALERT_FILTER_TO_TYPES = [
  '긴급' => ['strike'],
  '운행중단' => ['strike'],
  '행사' => ['event'],
  '공지' => ['update'],
];

/** DB event_type → 이슈 영향도(High/Medium/Low) */
const ALERT_TYPE_TO_IMPACT = [
  'strike' => 'High',
  'event' => 'Medium',
  'update' => 'Medium',
];

/** DB event_type → 이슈 필터 표시명 */
const ALERT_TYPE_TO_FILTER_LABEL = [
  'strike' => '긴급',
  'event' => '행사',
  'update' => '공지',
];

/**
 * 이슈 필터(UI) → DB event_type 배열
 * @return string[] event_type 목록. 빈 배열이면 전체(필터 없음)
 */
function filter_to_event_types(string $filter): array
{
  return ALERT_FILTER_TO_TYPES[$filter] ?? [];
}

/**
 * DB event_type → 이슈 영향도
 */
function event_type_to_impact(string $eventType): string
{
  return ALERT_TYPE_TO_IMPACT[$eventType] ?? 'Medium';
}

/**
 * DB event_type → 이슈 필터 표시명
 */
function event_type_to_filter_label(string $eventType): string
{
  return ALERT_TYPE_TO_FILTER_LABEL[$eventType] ?? $eventType;
}

/**
 * DB event_type → 이슈 필터 표시
 * @see event_type_to_filter_label()
 */
function event_type_to_filter(string $eventType): string
{
  return event_type_to_filter_label($eventType);
}
