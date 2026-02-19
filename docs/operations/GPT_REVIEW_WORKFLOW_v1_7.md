# GPT 검수 워크플로우 (v1.7-19)

## 개요
수동 버튼 검수 대신 **CSV/JSON 내보내기 → GPT API 검수 → 일괄 반영** 흐름으로 검수를 진행합니다.

**Python 파이프라인:** `scripts/python/gpt_review_pipeline.py` + `docs/operations/GPT_REVIEW_PIPELINE_v1_7.md` 참고.

## 관련 구현 (v1.7-19)
- **정규화/매칭:** 정류장명 1개 단위 필요. 노선 정보는 route_stop_master 매칭용.
- **PDF 파싱:** "정류장명 하나" 단위 분할("성동세무서 (04212)-송정동아이파크 (05235)-..."), "3km 5대 40회" 등 노선 메타 제외.
- **공공데이터 자동 매칭:** route_label → route_id 매핑 후 route_stop_master의 seq_in_route로 stop_id 자동 매칭.

## 워크플로우

### 1. 후보 내보내기
- URL: `admin/route_review.php?source_doc_id={id}&route_label={label}`
- **CSV 다운로드** 또는 **JSON 다운로드** 클릭
- 다운로드 파일 컬럼:
  - `candidate_id` — 후보 ID
  - `seq_in_route` — 노선 내 순서
  - `raw_stop_name` — 원본 정류장명
  - `normalized_name` — 정규화된 정류장명
  - `matched_stop_id` — 현재 자동 매칭 정류장 ID
  - `matched_stop_name` — 현재 매칭 정류장명
  - `match_method` — 매칭 방식 (exact, like_prefix 등)
  - `status` — 현재 상태 (pending, approved, rejected)
  - `suggested_stop_id` — 공공데이터 기준 제안 정류장 ID (route_label 매칭 시)
  - `suggested_stop_name` — 제안 정류장명

### 2. GPT 검수
- 내보낸 CSV/JSON을 GPT API에 전달
- 각 행에 대해:
  - `action`: `approve` 또는 `reject`
  - `matched_stop_id`: approve 시 필수 (seoul_bus_stop_master의 stop_id)
  - `matched_stop_name`: approve 시 선택 (없으면 raw_stop_name 사용)

### 3. 일괄 반영
- 반영 파일 형식: `candidate_id`, `action`, `matched_stop_id`, `matched_stop_name` (선택)
- CSV 예시:
  ```csv
  candidate_id,action,matched_stop_id,matched_stop_name
  101,approve,12345,성동세무서
  102,reject,,
  103,approve,12346,송정동아이파크
  ```
- JSON 예시:
  ```json
  {
    "candidates": [
      {"candidate_id": 101, "action": "approve", "matched_stop_id": "12345", "matched_stop_name": "성동세무서"},
      {"candidate_id": 102, "action": "reject"},
      {"candidate_id": 103, "action": "approve", "matched_stop_id": "12346"}
    ]
  }
  ```
- route_review 페이지에서 **검수 결과 일괄 반영** 폼으로 파일 업로드

### 4. 승격
- pending 후보가 0이 되고 approved 후보의 matched_stop_id가 모두 채워지면
- **승인 후보를 Route Stops로 승격** 버튼으로 승격 실행

## 참고
- `suggested_stop_id`는 route_label이 seoul_bus_route_master에 매칭될 때 seq_in_route 기준으로 공공데이터 정류장을 제안합니다.
- GPT가 approve 시 suggested_stop_id를 그대로 사용하거나, stop_master 검색 결과로 대체할 수 있습니다.
