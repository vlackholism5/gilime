# v1.7-16: parse_status / 배치 대상 정책 통일

## 목적
- `parse_status`와 배치 선별 규칙을 고정해 재처리 동작을 예측 가능하게 만든다.

## 고정 정책
- `run_job.php`는 실행 중 `running`, 종료 시 `success/failed`만 사용한다.
- `run_parse_match_batch.php`:
  - `only_failed=1`:
    - `parse_status='failed'` 포함
    - 또는 `PARSE_MATCH failed` job 이력이 있는 레거시 실패 문서 포함
  - `only_failed=0` 기본:
    - `parse_status='success'`만 포함
- `source_doc_id` 지정 시 상태와 무관하게 override 선별한다.

## 구현 내용
- 파일: `scripts/php/run_parse_match_batch.php`
  - 선별 규칙을 정책대로 재구성
  - 출력 필드 추가:
    - `selection_policy`
    - `selected_by_status`
    - `selected_reason_top`
  - `dry_run=1` 시 3줄 요약 출력:
    - selection policy
    - selected_by_status
    - selected_reason_top

## Non-goals
- `parse_status` 컬럼 타입/enum 변경 없음
- 기존 데이터 일괄 마이그레이션 없음
- 새 테이블 추가 없음

