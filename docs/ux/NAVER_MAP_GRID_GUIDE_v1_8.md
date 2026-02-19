# 네이버 지도 그리드·스페이싱 가이드 (v1.8)

## 1. 개요

네이버 지도 Figma 디자인 시스템([Figma Community](https://www.figma.com/community/file/1479179315202286460/naver-map-design-system-library-ver-0-1)) 및 8-point grid 산업 표준을 참조해 길라임 MVP에 적용하는 그리드·스페이싱 규격을 정리한다.

---

## 2. 8-point 그리드 표준

| 토큰 | px | rem | 용도 |
|------|-----|-----|------|
| nano | 4 | 0.25rem | 아이콘 내부, 세밀한 간격 |
| micro | 8 | 0.5rem | 기본 단위, 컴포넌트 내부 패딩 |
| small | 16 | 1rem | 섹션 간격, 버튼 패딩 |
| medium | 24 | 1.5rem | 카드 내부, 블록 간격 |
| large | 32 | 2rem | 페이지 섹션 간격 |
| xlarge | 40 | 2.5rem | 대형 블록, 터치 영역 확보 |
| xxlarge | 56 | 3.5rem | 전체 섹션 분리 |

---

## 3. 길라임 SOT 매핑

| 길라임 토큰 | 값 | 네이버/8pt 대응 | 비고 |
|-------------|-----|-----------------|------|
| `--g-space-0` | 4px (0.25rem) | nano | 확장 (선택) |
| `--g-space-1` | 8px (0.5rem) | micro | 기존 |
| `--g-space-2` | 16px (1rem) | small | 기존 |
| `--g-space-3` | 24px (1.5rem) | medium | 기존 |
| `--g-space-4` | 32px (2rem) | large | 기존 |
| `--g-space-5` | 40px (2.5rem) | xlarge | 확장 (선택) |

---

## 4. 컴포넌트별 권장 패딩/마진

### 4.1 검색바 (U-JNY-01)

| 요소 | 패딩 | 마진 |
|------|------|------|
| 검색바 컨테이너 | `--g-space-2` (16px) | 상하 `--g-space-3` |
| 입력 필드 내부 | `--g-space-1` ~ `--g-space-2` | — |
| 아이콘(뒤로, 음성, X) | `--g-space-1` | — |
| 터치 영역 최소 | 44px | — |

### 4.2 탭 (장소 \| 버스정류장, 경로 필터)

| 요소 | 패딩 | 마진 |
|------|------|------|
| 탭 컨테이너 | `--g-space-1` | 하단 `--g-space-2` |
| 탭 버튼 | `--g-space-1` 좌우, `--g-space-1` 상하 | 탭 간 `--g-space-1` |
| 탭 그룹 간격 | — | `--g-space-2` |

### 4.3 결과 리스트 (검색 결과, 경로 카드)

| 요소 | 패딩 | 마진 |
|------|------|------|
| 리스트 컨테이너 | `--g-space-2` | — |
| 리스트 항목 | `--g-space-2` | 항목 간 `--g-space-2` |
| [선택] 버튼 | `--g-space-1` 좌우 | — |
| 경로 카드 | `--g-space-3` | 카드 간 `--g-space-2` |

### 4.4 경로 타임라인 세그먼트

| 요소 | 패딩 | 비고 |
|------|------|------|
| 타임라인 컨테이너 | `--g-space-2` 상하 | `g-route-timeline` |
| 세그먼트 높이 | 8px | `g-route-segment` |
| 세그먼트 간격 | 2px | 시각적 구분 |

---

## 5. 레이아웃 구조 (네이버 지도 참조)

```
[상단] 네비게이션 + 제목
  margin-bottom: --g-space-3

[검색 영역] 출발/도착
  padding: --g-space-2
  margin-bottom: --g-space-3

[탭] 장소 | 버스정류장 (또는 경로 필터)
  padding: --g-space-1
  margin-bottom: --g-space-2

[리스트/카드 영역]
  padding: --g-space-2
  항목 간격: --g-space-2
```

---

## 6. Figma 참조 방법

- **Figma Community:** Naver Map Design System library (VER.0.1) 열기
- **추출 대상:** 컴포넌트 패딩, 아이콘 크기, 탭·카드 레이아웃 수치
- **길라임 적용:** 색상·폰트는 SOT 유지, 레이아웃·스페이싱만 참조

---

## 7. 참조 문서

- [NAVER_MAP_UI_ADOPTION_v1_8.md](./NAVER_MAP_UI_ADOPTION_v1_8.md)
- [SOT_GILAIME_UI_SYSTEM.md](../ui/SOT_GILAIME_UI_SYSTEM.md)
- [WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md](./WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md)

---

*문서 버전: v1.8. 2026-02 기준.*
