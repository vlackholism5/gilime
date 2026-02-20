# SoT — 경로 스코어 시뮬레이션 모델 v1

## Purpose

Single source of truth for 스코어링 검증용 계수·테스트 세트. 구현 후 동일 입력→동일 출력 보장.

**Lock 조건:** α, β, γ 고정, Test Set A~D 정답표 확정.

---

## 고정 계수 (v1)

| 계수 | 값 | 설명 |
|------|-----|------|
| α | 5 | TransferPenalty 계수 |
| β | 8 | IssueImpact 계수 |
| γ | 3 | WalkCost 계수 |
| δ | 0 | ReliabilityPenalty (MVP 미사용) |

---

## 입력 스키마 (RouteCandidate)

- total_min
- transfers
- walk_m
- segments[]: type, duration_min, issue_exposed, issue_severity

---

## 테스트 세트 A~D

- **A~D:** 각 후보에 대해 기대 Score, IssueImpact(II), 정렬 결과(best / fastest / least_issue / least_transfer) 정답표 확정
- 검증: 구현 후 `verify_scoring_model_v1.php` 등으로 동일 입력 → 동일 출력
- API 응답에 score, issue_impact 필드 포함

---

## 정렬 모드

- best (최적경로)
- fastest (최소시간)
- least_issue (이슈 최소 영향)
- least_transfer (최소환승)

---

## 연계 문서

- [Route_Scoring_Model](Route_Scoring_Model.md)
- [11_API_V1_IMPL_PLAN](11_API_V1_IMPL_PLAN.md) — B6 RouteService
