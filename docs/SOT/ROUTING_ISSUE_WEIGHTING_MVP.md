# SOT — ROUTING_ISSUE_WEIGHTING_MVP

## 목적
- 이슈 문맥(`issue_context_id`)이 있는 길찾기 요청에서 정책 기반 가중치를 적용한다.
- 정책 타입: `BLOCK`, `PENALTY`, `BOOST`.

## 데이터 모델
- Migration: `sql/migrations/v0.8-13_create_issue_targets.sql`
- 테이블: `issue_targets`
  - `issue_id`
  - `target_type`: `route|line|station`
  - `target_id`
  - `policy_type`: `block|penalty|boost`
  - `severity`: `low|medium|high|critical`

## 적용 규칙
- `BLOCK`: 후보 경로 제외
- `PENALTY`: 총 소요시간 가산
  - low=+3, medium=+6, high=+10, critical=+15 (분)
- `BOOST`: 총 소요시간 감산 비율
  - low=8%, medium=15%, high=20%, critical=25%

## API 반영
- 엔드포인트: `POST /api/index.php?path=v1/routes/search`
- 추가 입력: `issue_context_id` (nullable)
- 구현 파일: `app/inc/api/v1/RouteService.php`
  - `route_load_issue_policy_set()`
  - `route_apply_policy_to_candidate()`
  - 응답 필드:
    - `policy_penalty`
    - `policy_boost`
    - `issue_context_id`

## 검증
- Validation SQL: `sql/validate/v0.8-13_validate_issue_targets.sql`
- 스크립트: `scripts/php/verify_scoring_model_v1.php` (정책 스모크 출력 포함)
- 확인 포인트:
  - `issue_context_id` 없음: 기존 스코어 유지
  - `PENALTY` 적용 시 score 증가
  - `BOOST` 적용 시 score 감소
  - `BLOCK` 적용 시 후보 제외
