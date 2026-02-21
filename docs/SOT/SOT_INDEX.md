# SOT_INDEX — GILIME v1.0
Single Source of Truth Index (Token-Safe Entry)

## 목적
- SOT 문서를 한 번에 전부 읽지 않도록 방지
- 변경/확장 시 "어떤 문서를 읽어야 하는지"를 먼저 결정하게 함

---

## 1. Product & Concept

| 문서 | 목적 | 언제 읽는가 |
|------|------|------------|
| 01_PRODUCT_OVERVIEW.md | 서비스 정의 | 기획 변경 시 |
| 03A_UX_PRINCIPLES.md | UX 원칙 | UI 변경 전 |
| 03B_DESIGN_SYSTEM_V0_1.md | 디자인 시스템 | 컴포넌트 추가 시 |
| 03C_COMPONENTS_SPEC.md | 컴포넌트 명세 | UI 수정 시 |

---

## 2. System Architecture

| 문서 | 목적 | 언제 읽는가 |
|------|------|------------|
| 05_SYSTEM_ARCHITECTURE.md | 전체 구조 | 파이프라인 수정 시 |
| 06_DATA_PATHS_AND_FILES.md | 데이터 흐름 | ingest/업로드 변경 시 |

---

## 3. Admin / Data / ERD

| 문서 | 목적 | 언제 읽는가 |
|------|------|------------|
| Gilime_Admin_ERD_MVP_v1.md | DB 구조 | 테이블 수정 전 |
| OPS_DB_MIGRATIONS.md | 마이그레이션 | 스키마 변경 시 |

---

## LLM 작업 규칙

1) 먼저 SOT_INDEX를 읽는다.
2) 필요한 문서를 1~2개만 선택한다.
3) 선택 이유를 명확히 설명한다.
4) 전체 docs 폴더를 읽지 않는다.