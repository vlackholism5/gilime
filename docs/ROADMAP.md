# 길라임(GILIME) 로드맵

## 1. 설계하고자 하는 서비스 전체 (길라임 로드맵)

길라임은 **대중교통 노선 정보를 문서(이미지·PDF 등)에서 추출해, 공식 정류장 마스터와 매칭·검증한 뒤, 노선별 정류장 데이터로 정리하는 서비스**를 지향합니다.

### 1.1 서비스 단계(개념)

| 단계 | 목적 | 상태 |
|------|------|------|
| **PoC** | “문서 → 파싱 → 정류장 매칭 → 검토” 흐름이 기술적으로 동작하는지 검증 | ✅ 완료 |
| **MVP** | 실제 운영자가 쓰기에 충분한 최소 기능(스냅샷·승인·품질·안전장치) | 🔄 진행 중 (v0.6-18) |
| **운영/확장** | 다수 문서·노선·재매칭·배치·다른 교통수단 등 | 미정 |

### 1.2 전체 파이프라인(목표 형태)

```
[문서 업로드] → [OCR] → [파싱/매칭 Job] → [노선별 검토 화면] → [승인·승격] → [노선별 정류장 스냅샷]
```

- 문서 단위로 Job 이력 관리, **최신 성공 스냅샷** 기준으로 후보 표시·승격.
- 운영자: 자동매칭 결과 검토, 승인/거절/동의어(alias) 보정 후 **노선별 정류장**으로 반영.

---

## 2. PoC 단계 — 무엇을 했는지

**목적:** “문서에서 뽑은 정류장명을 서울시 정류장 마스터와 매칭해서, 사람이 검토·승인할 수 있는가?” 검증.

### 2.1 PoC 범위(검증한 것)

- **문서·Job 관리:** 원본 문서 메타 저장, PARSE_MATCH / PROMOTE 등 Job 로그로 실행 이력 추적.
- **파싱·후보 생성:** 문서에서 노선별 정류장 후보(candidate) 추출.
- **자동매칭:** 서울시 버스 정류장 마스터(`seoul_bus_stop_master`) 기준  
  **정확일치(exact) → 공백 정규화(normalized) → prefix 매칭(like_prefix)** 순으로 `matched_stop_id` 등 추천.
- **검토·승인 UI:** 노선별 후보 목록에서 승인/거절, 승인된 것만 “노선별 정류장(route_stop)”으로 승격.

### 2.2 PoC에 해당하는 구현(버전)

- 문서 업로드·목록·상세, PARSE_MATCH 실행, **route_review** 진입, Promote로 route_stop 반영.
- **v0.6-8 ~ v0.6-11** 구간: 스냅샷·job_log 정리, 서울 정류장 마스터 연동, 자동매칭 규칙 및 match_method/score 저장까지를 PoC 완료로 볼 수 있음.

### 2.3 PoC 결론

- 문서 → 파싱 → 자동매칭 → 검토 → 승격 흐름이 **동작함**을 확인.  
- 이어서 “운영에 쓸 수 있는 최소 범위(MVP)”를 정의하고 구체화.

---

## 3. 현재 MVP 범위 — 지금 하고 있는 것

**목적:** PoC 위에 **운영 가능한 최소 기능**을 얹어, 실제로 문서를 돌려가며 노선별 정류장을 관리할 수 있게 하는 것.

### 3.1 MVP에서 추가·고정한 것

- **SoT(Source of Truth):**  
  - 항상 **latest 성공 PARSE_MATCH** 스냅샷만 후보로 표시·집계.  
  - 이전 스냅샷(stale) 후보는 승인/거절 불가.  
  - Promote는 latest 스냅샷만 허용.  
  - route_stop은 삭제 없이 **스냅샷 누적**(기존 active → `is_active=0`, 신규만 `is_active=1`).
- **동의어(alias):** 정류장명 변형 → 정식 명칭 매핑 저장. alias 등록 시 **해당 후보 1건 즉시 재매칭**(live rematch).
- **품질·안전:**  
  - 매칭 방식/점수 표시, like_prefix는 2글자 이하일 때 미적용.  
  - 매칭 실패만 보기, 추천 canonical(only_unmatched에서만), **매칭 신뢰도(HIGH/MED/LOW/NONE)** 및 summary 집계로 promote 전 점검 가능.

### 3.2 MVP 버전 범위 및 내용(v0.6-12 ~ v0.6-18)

| 버전 | 구분 | 요약 |
|------|------|------|
| v0.6-12 | MVP | 정규화 + **동의어(alias)** 사전, alias_exact/alias_normalized |
| v0.6-13 | MVP | **alias 등록 즉시 재매칭**, stale 후보는 rematch 생략 |
| v0.6-14 | MVP | 매칭 품질·안전: match_method/score 표시, like_prefix 2글자 이하 미적용 |
| v0.6-15 | MVP | **Stop Master Quick Search**, raw_stop_name 복사 편의 |
| v0.6-16 | MVP | **매칭 실패만 보기**, **추천 canonical** 컬럼, placeholder 반영 |
| v0.6-17 | MVP | 추천 canonical only_unmatched에서만 + 요청 단위 캐시 (SoT 고정) |
| v0.6-18 | MVP | **매칭 신뢰도** 표시 + summary 집계(low_confidence/alias/none_matched 등) + 검증 쿼리 |

### 3.3 MVP 현재 상태

- **v0.6-18** 반영 완료.
- 운영자가 **모호매칭(like_prefix) 비중**을 summary에서 보고, promote 전에 점검할 수 있는 상태.
- **제약:** 새 테이블/페이지는 지시 없이 추가하지 않음. 풀스캔·`LIKE '%...%'` 금지. SoT·stale 차단·promote 규칙 훼손 금지.

### 3.4 SoT 상세(변경 금지)

1. latest PARSE_MATCH 스냅샷 기준 후보 표시·집계.
2. stale 후보 승인/거절 차단(UI+서버).
3. Promote는 latest PARSE_MATCH 스냅샷만 허용.
4. route_stop 스냅샷 누적(기존 active → is_active=0, 신규만 is_active=1).
5. alias 등록 시 latest 후보 1건만 즉시 live rematch.
6. 추천 canonical은 only_unmatched=1일 때만 계산(캐시 포함).

---

## 4. 정리

- **전체 로드맵:** “문서 → 파싱·매칭 → 검토·승인 → 노선별 정류장” 서비스를 단계적으로 구축.
- **PoC:** 위 흐름이 **동작하는지** 검증 완료 (문서·Job·자동매칭·검토·승격).
- **현재 MVP:** PoC 위에 **스냅샷·alias·품질·안전장치**를 얹어 **운영 가능한 최소 범위**를 채우는 중이며, v0.6-18이 그 범위의 현재 스냅샷임.
