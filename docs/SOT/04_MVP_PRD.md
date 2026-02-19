# SoT 04 — MVP PRD

## Purpose

Single source of truth for MVP scope split: MVP Core (by 2026-02-20) vs MVP Plus. Aligns planning and DoD.

## Definitions

- **MVP Core:** 2026-02-20까지 확정하는 최소 기능. "1개 이슈 완주 데모" 기준.
- **MVP Plus:** Core 이후 확장. 2~3 결과·추가 경로 옵션 등.
- **DoD:** Definition of Done. 성공 기준 체크리스트.

## MVP Core (by 2026-02-20)

- 관리자: PDF 1건 업로드 → source_doc 생성. 파싱/매칭 실행 → 후보 생성. 노선 검수(승인/거절/별칭) → 승격 실행.
- 사용자: 구독 노선 기준 경로 안내(정류장 순서). 알림 확인.
- **외부 라우팅 베이스 + 셔틀 구간 삽입:** 경로 안내 시 "외부 라우팅 베이스에 셔틀 구간을 삽입"하는 흐름을 MVP Core에 포함. (구체 연동 대상·API는 확인 필요.)
- 감사: 전달 이력/상태 조회.
- 서울 1개 이슈 기준. 문서: Gate/E2E/라이프사이클/재시도 정책/CRUD/UML 최소 세트.

## MVP Plus (post Core)

- **2~3 results:** 경로/결과를 2~3건 노출 등 확장. (상세 스펙 확인 필요.)
- 추가 경로 옵션·정렬·필터.
- OCR 자동화·워커/큐 비동기 파이프라인·실시간 혼잡 등은 비범위 또는 후속.

## Assumptions

- 경로 안내 MVP Core는 "구독 노선의 승격된 정류장 순서" 우선. 외부 길찾기 API 폴리라인은 후속.
- 대규모 재구축 금지. 기존 파이프라인 확장 우선.
- DB 변경은 문서화 후 승인 단위로만 실행.

## Open Questions (확인 필요)

- "외부 라우팅 베이스" 구체 시스템/API 및 셔틀 구간 삽입 규격.
- "2~3 results"의 정확한 정의(경로 수·대안 수·UI 위치).
- MVP Plus 목표 일정.
