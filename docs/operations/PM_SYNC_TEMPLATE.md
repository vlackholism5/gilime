# PM Sync Template

PM용 GPT와의 중간 점검을 위한 고정 템플릿.

## 1) Milestone Brief (1페이지)

### Done
- 오늘 완료한 항목 3~5개

### Evidence
- 코드/문서 파일 경로
- 검증 결과(`php -l`, smoke, SQL 확인 결과)
- 화면 캡처 경로

### Risk
- 현재 리스크 1~3개
- 일정/품질 영향도

### Next (24h)
- 다음 24시간 실행 항목 2~4개
- 선행 조건/의존성

---

## 2) Gate Report (체크리스트)

### Gate ID
- 예: `Gate-04`

### Scope
- 이번 게이트의 대상 기능

### Checklist
- [ ] 요구사항 충족
- [ ] 회귀 영향 점검
- [ ] 예외 시나리오 점검
- [ ] 증거 파일 첨부

### Evidence Links
- 코드: `...`
- 문서: `...`
- 로그/쿼리: `...`
- 캡처: `...`

### Result
- PASS / HOLD / FAIL

### Decision Note
- 다음 단계 진입 조건
