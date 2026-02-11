# v1.5 RELEASE GATE (MVP2.5 종료 조건)

## Gate Checklist (G1~G5)

| Gate | 항목 | PASS 기준 | 증거(파일/SQL/스크린샷) | 결과(OK/FAIL/확인 필요) |
|------|------|-----------|--------------------------|--------------------------|
| G1 | Git 원격 동기화 | main에 v1.5 커밋 3개가 push 되어 있음 | `git log --oneline -n 10` | |
| G2 | Alert Ref Contract 위반 0 | v1.5-02_validation.sql 3쿼리 모두 0 rows | `sql/v1.5-02_validation.sql` 실행 결과 | |
| G3 | Delivery semantics | page=1 최초 접속 시 deliveries 증가, 새로고침 시 추가 증가 없음 | Workbench SELECT 결과 + 브라우저 확인 | |
| G4 | Pagination | page=1 → Next → Previous 동작, 페이지별 deliveries 기록이 렌더된 항목만 반영 | 브라우저 확인 + deliveries COUNT | |
| G5 | Observability 최소선 | routes subscribe_toggle / alerts delivery_written error_log 존재 | 코드 확인 + (가능 시) error_log | |

## Evidence Notes

- **v1.5 커밋 3개:**
  - f8b1a58 docs(v1.5-01): observability baseline
  - 6cb30c2 feat(v1.5-02): alert ref contract + validation
  - bc0e399 feat(v1.5-03): delivery semantics + pagination + STATUS/README

- **v1.5-02_validation.sql 결과 (SoT: 대화 기준)**  
  - ref_type='route' 위반 0 rows  
  - ref_type='doc' 위반 0 rows  
  - deliveries 중복 0 rows  

## v1.6(one-shot 확장) 시작 조건

- **G1~G4가 OK**이면 v1.6로 진행한다.
- **G5**는 환경(서버 로그 접근)에 따라 "확인 필요"여도 v1.6 진행 가능.
- v1.6는 새 페이지/새 테이블 포함 가능한 **대규모 확장(one-shot)**이며,  
  첫 산출물은 `docs/v1.6_작업실행서.md` 이다.
