# 검수 대기열 — LOW/NONE/리스크 라벨 의도

**대상:** `public/admin/review_queue.php`

- **LOW:** `match_method = 'like_prefix'` 인 후보. 접두어 유사 매칭만 되어 있어 검수 시 확인이 필요하다.
- **NONE:** `match_method` 가 NULL 또는 빈 값인 후보. 매칭이 되지 않았으므로 우선 검수 대상이다.
- **리스크 대기:** 위 LOW 대기 수와 NONE 대기 수의 합계. "리스크만 보기" 필터는 이 두 유형만 보여 주며, 검수 시 우선 처리할 항목이다.

집계·라벨 정의는 화면 상단 안내 문단과 `matchConfidenceLabel()` 함수와 동일하다.
