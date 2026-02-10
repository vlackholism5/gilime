# Known Issues / Backlog

## Resolved

- **단축키 오작동 방지 (INPUT 포커스 시 무시)** — v1.1-07. INPUT/TEXTAREA/SELECT 포커스 중에는 a/r/n/t/j/k 무시. 연타 방지(800ms 락) 포함. 해결됨.

## alias_text<=2 기존 3건 (v0.6-21 이전 데이터)

- **내용:** v0.6-21에서 alias_text 길이 <=2 저장 차단을 도입했으나, 그 이전에 등록된 alias 중 길이 2 이하인 행이 3건 잔존한다.
- **원인:** 과거 정책으로 저장된 데이터. 신규 등록은 모두 차단됨.
- **처리 방향:** (1) 정리: 필요 시 수동 UPDATE/삭제 또는 "legacy" 플래그 등 후속 버전에서 정리. (2) 당장 동작에는 영향 없음(매칭/승인 게이트와 무관).
- **후속:** v1.x에서 데이터 정리 스크립트 또는 마이그레이션 검토 가능.

## 운영 원칙 (v1.2)

- **새 페이지는 read-only.** Review Queue / Alias Audit / Ops Dashboard는 조회·링크만 제공. Promote·Approve·Reject는 **route_review에서만** 수행.

## 운영 중 확인 항목

- **특정 doc/route에서 LOW(like_prefix) 증가:** doc.php PARSE_MATCH Metrics 경고("LOW 후보가 직전 job 대비 +N 증가") 확인 후 route_review에서 재검토.
- **NONE(미매칭) 증가:** 동일하게 delta 경고 확인 후 alias 등록·수동 매칭 검토.
- **Promote 전 pending 잔존:** route_review에서 pending 후보 0이어야 Promote 가능. 메시지 "pending 후보가 남아있어 승격할 수 없습니다." 확인.
