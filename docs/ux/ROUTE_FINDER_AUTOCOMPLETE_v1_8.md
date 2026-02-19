# 길라임 추천검색어(자동완성) 설계 — v1.8

## 1. 개요

길찾기 출발/도착 입력 시 "문래" 입력 → "문래역", "문래역1번출구" 등 추천검색어(자동완성)를 제공하여 정류장 선택 UX를 개선한다.

---

## 2. 요구사항

| 기능 | 설명 | 우선순위 |
|------|------|----------|
| **정류장 자동완성** | "문래" → 문래역, 문래역1번출구 등 N건 제안 | 필수 |
| **다중 후보 선택** | 리스트에서 클릭 시 해당 정류장으로 설정 | 필수 |
| **최근 검색어** | localStorage 저장, 포커스 시 노출 | 선택(Phase 2) |
| **초성 검색** | ㄱㄴ → 강남 등 | 선택(Phase 2) |

---

## 3. 구현 사양

### 3.1 백엔드

**함수:** `route_finder_suggest_stops(PDO $pdo, string $input, int $limit = 10): array`

- **파일:** `app/inc/route/route_finder.php`
- **조건:** `stop_name LIKE '%{input}%'` (포함 검색)
- **정렬:** prefix 일치 우선 (`stop_name LIKE '{input}%'` → 0, 그 외 → 1), 그 다음 `stop_name` 오름차순
- **반환:** `[['stop_id' => int, 'stop_name' => string], ...]` 최대 10건

### 3.2 API

**엔드포인트:** `GET /api/route/suggest_stops?q={검색어}`

- **파일:** `public/api/route/suggest_stops.php`
- **응답:** `{ "items": [{ "stop_id": 123, "stop_name": "문래역1번출구" }, ...] }`
- **에러:** `q` 비어 있으면 `{ "items": [] }`

### 3.3 프론트엔드

- **페이지:** `public/user/route_finder.php`, `public/user/home.php`
- **스크립트:** `public/assets/js/route_autocomplete.js`
- **구성:** 출발/도착 input 각각 `g-autocomplete-wrap` + `g-autocomplete-dropdown`
- **동작:**
  - `input` 이벤트 debounce 200ms
  - `q` 1글자 이상 시 API 호출
  - 결과를 드롭다운에 버튼으로 표시
  - 클릭 시 input value 설정 후 드롭다운 닫기
  - blur 시 150ms 후 드롭다운 닫기
  - Escape 키로 드롭다운 닫기

### 3.4 CSS

- **클래스:** `.g-autocomplete-wrap`, `.g-autocomplete-dropdown`, `.g-autocomplete-item`
- **파일:** `public/assets/css/gilaime_ui.css`

---

## 4. 데이터 흐름

```
[사용자 입력 "문래"]
    ↓
input 이벤트 → debounce 200ms
    ↓
GET /api/route/suggest_stops?q=문래
    ↓
route_finder_suggest_stops() → seoul_bus_stop_master 쿼리
    ↓
JSON { items: [{stop_id, stop_name}, ...] }
    ↓
드롭다운에 버튼 목록 렌더
    ↓
[사용자 클릭 "문래역1번출구"]
    ↓
input.value = "문래역1번출구", 드롭다운 닫기
```

---

## 5. 참조 문서

- [ROUTE_FINDER_DIAGNOSIS_v1_8.md](./ROUTE_FINDER_DIAGNOSIS_v1_8.md) — 경로 없음 원인·멀티롤 회의
- [NAVER_MAP_UI_ADOPTION_v1_8.md](./NAVER_MAP_UI_ADOPTION_v1_8.md) — 검색 UI 패턴
- [WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md](./WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md) — U-JNY-01~04

---

*문서 버전: v1.8. 2026-02 기준.*
