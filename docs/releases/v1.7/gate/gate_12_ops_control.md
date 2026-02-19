# v1.7-12 Gate (Ops control)

## Gate 항목

| # | 항목 | Evidence |
|---|------|----------|
| G1 | A 섹션 | status counts, failed top 20 (retry_count, last_error), Run outbound stub CLI 안내. |
| G2 | B 섹션 | Run metrics ingest CLI, 최근 metrics 이벤트 10 테이블. |
| G3 | C 섹션 | Alert Ops / Alert Audit / Ops Summary 링크. |
| G4 | index 링크 | Admin index에 Ops Control 1줄. |
| G5 | validation | v1.7-12_validation: 상태별·retry 분포, metrics 10, content_hash 중복 0. |

## Non-goals

- UI 시각화 고도화, 권한/CSRF 완비(위험은 docs에 명시).
