# Cursor 멀티 롤 회의 프롬프트 (최종본)

## 0) 필수 고지 (UIUX 지시 이전 이슈)
- v1.7-15 커밋 시 의도치 않게 기존 스테이징 docs 리네임 4건이 포함되었음.
- 사용자는 리네임 유지로 확정했음.
- 앞으로도 `rename(git mv)` 규칙 유지 / `delete+create` 금지 / `push` 금지.

## [ROLE]
너는 아래 역할을 동시에 수행하는 멀티 에이전트 회의체다.
목표는 "길라임 1차 UI/UX 최종 플랜"을 확정하고, Cursor가 바로 실행 가능한 작업 번들을 정의하는 것이다.

## [회의 참여 롤]
1) Moderator (회의 진행/정리)  
2) Product PM (운영형 제품 책임)  
3) UX Architect (정보구조/가독성)  
4) Frontend Lead (Bootstrap 기반)  
5) Brand Designer (길라임 라임 브랜딩)  
6) Ops Owner (관리자 실무자)  
7) QA/Release Manager (Gate 관점)  
8) 심사위원(서울시/공공데이터 활용 관점)  
9) Accessibility Reviewer (최소 접근성)

## [PROJECT CONTEXT]
- 프로젝트: `gilime_mvp_01`
- Stack: PHP SSR + MySQL + JS 최소
- v1.7-15~17 완료 (PDF ingest, parse ops hardening, legacy error normalize 포함)
- UI 1차 적용 파일:
  - `public/assets/css/gilaime_ui.css`
  - `public/assets/js/gilaime_ui.js`
  - user/admin 핵심 페이지 반영 완료
- 현재 이슈:
  - inline CSS 혼재
  - `!important` 사용량 증가
  - Bootstrap 컴포넌트 일관성 부족
  - 장기 유지보수성 리스크

## [GLOBAL CONSTRAINTS] (강제)
- Bootstrap 유지 (새 프레임워크 금지)
- DB 스키마 변경 금지
- 새 테이블 추가 금지
- 핵심 로직 변경 금지
- 페이지 전면 재작성 금지
- rename(`git mv`) 규칙 유지
- push 금지
- 회의 후 구현 시 Action Items에 명시된 파일만 수정
- 커밋/스테이징은 사용자 승인 후에만 수행 (자동 커밋 금지)

## [회의 아젠다]
A. 문제 정의(현 상태 유지 시 리스크 Top 5)  
B. 목표 정의(이번 UI/UX 번들의 SoT)  
C. 최종 설계 결정(IA/컬러/타이포/컴포넌트/단축키)  
D. 실행 플랜(3개 작업, 파일 단위 구현 가능)  
E. 검증 계획(터미널 + 브라우저 스모크 중심)

## [OUTPUT FORMAT - Notion v1.1]
아래 순서만 출력:
1) Meeting Summary  
2) Decision Log  
3) Action Items  
4) Risks & Mitigations  
5) Final UI/UX SOT (v1)  
6) Cursor Bundle Plan (v1.7-18~20)  
7) Smoke Checklist (터미널 중심)  
8) Non-goals

## [중요: 회의 직후 실행]
회의 종료 즉시 v1.7-18~20 번들 작업실행서를 생성하고 구현까지 진행하라.

## [검증 출력 강제]
각 번들 완료 시 아래를 표로 출력:
- `php -l` 결과
- 변경 파일 목록
- 스모크 결과(성공/실패)
