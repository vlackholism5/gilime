# Docs Index

실무형 정리 1단계(비파괴): 기존 flat 문서는 유지하고, 신규 문서부터 `docs/releases/` 구조를 사용.

## 핵심 문서

- 상태 요약: `docs/STATUS_FOR_GPT.md`
- SQL 실행 인덱스: `sql/INDEX.md`

## 운영 문서

- 디버그/관측: `docs/operations/DEBUG_OBSERVABILITY.md`
- 검증 자동화: `docs/operations/VERIFY_RUNNER.md`

## 참조 문서 (SoT)

- v1.7 로드맵: `docs/references/ROADMAP_v1_7.md`
- DDL 참조: `docs/references/DDL_REFERENCE_app_tables_v1_4.md`
- 보안 기준: `docs/references/SECURITY_BASELINE.md`
- 에러 정책: `docs/references/ERROR_POLICY.md`
- 라우팅 구조: `docs/references/ROUTING_STRUCTURE_v1_4.md`

## 신규 권장 구조

- `docs/releases/v1.7/specs/` : 기능 설명(정책/비목표 포함)
- `docs/releases/v1.7/smoke/` : 스모크 절차
- `docs/releases/v1.7/gate/` : 게이트 판정/증거
- `docs/operations/` : 운영 런북, 장애대응, 배치 운영
- `docs/references/` : DDL/규칙/보안 기준
- `docs/archive/` : 장기 보관

## 파일명 규칙 (신규)

- spec: `spec_XX_<topic>.md`
- smoke: `smoke_XX_<topic>.md`
- gate: `gate_XX_<topic>.md`

## 현재 버전 참조

- 현재 버전과 최근 변경은 `docs/STATUS_FOR_GPT.md`를 기준 SoT로 유지.
- v1.7 최근 문서:
  - spec: `docs/releases/v1.7/specs/`
  - smoke: `docs/releases/v1.7/smoke/`
  - gate: `docs/releases/v1.7/gate/`
- v1.7-13 (PDF Parsing): `docs/releases/v1.7/specs/spec_13_pdf_parsing.md`
  - 사용 가이드: `README_PDF_PARSING.md` (루트 디렉토리)
- v1.7-14 (PARSE_MATCH 운영형 고도화): `docs/releases/v1.7/specs/spec_14_parse_ops_hardening.md`
- v1.7-15 (Legacy error normalize): `docs/releases/v1.7/specs/spec_15_legacy_error_normalize.md`
- v1.7-16 (parse_status policy): `docs/releases/v1.7/specs/spec_16_parse_status_policy.md`
- v1.7-17 (ingest one pdf): `docs/releases/v1.7/specs/spec_17_ingest_one_pdf.md`

## 마이그레이션 정책

- 1단계(완료): 구조/목차 추가, v1.7 전체 이동, 참조/운영 문서 정리
- 2단계(보류): v0.6, v1.1~v1.6 구버전 정리
  - 핵심 참조만 references, 나머지 archive
  - 또는 v1.7처럼 전체 구조화(releases/<버전>/)
  - 필요 시 `docs/INDEX.md` 이 메모 참고해서 재개
