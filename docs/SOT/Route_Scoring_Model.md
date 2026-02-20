# SoT — 경로 스코어링 모델

## Purpose

Single source of truth for 경로 후보 점수 계산 공식. 이슈 영향·환승·도보·신뢰도 패널티 반영.

**Lock 조건:** α, β, γ (및 δ) 계수 고정, Test Set A~D로 검증(시뮬레이션 문서 참조).

---

## 그래프 모델

- **Node:** 정류장
- **Edge:** 이동(버스/지하철/셔틀/도보), Weight=시간
- **이슈 활성 시:** shuttle_edges를 그래프에 추가

---

## Score 공식

```
Score = T + α·TransferPenalty + β·IssueImpact + γ·WalkCost + δ·ReliabilityPenalty
```

- **T:** 총 소요 시간(분)
- **TransferPenalty:** (환승횟수)^1.2
- **IssueImpact:** Σ(edge.issue_severity_weight × edge.duration_ratio)  
  - severity weight: low=1, medium=3, high=6, critical=10
- **WalkCost:** total_walk_m / 400
- **ReliabilityPenalty:** MVP에서는 δ=0 (미사용)

---

## 재탐색 트리거

- 이슈 업데이트
- 지연 발생
- 사용자 요청

---

## 연계 문서

- [Route_Scoring_Simulation_Model_v1](Route_Scoring_Simulation_Model_v1.md) — 계수·Test Set A~D
- [11_API_V1_IMPL_PLAN](11_API_V1_IMPL_PLAN.md) — RouteService 구현
