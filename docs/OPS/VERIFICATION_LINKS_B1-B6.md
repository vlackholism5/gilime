# B1~B6 검증용 링크 및 확인 방법

Build 목록(B1→B6) 반영 여부를 **스크린샷으로 확인**할 때 사용하는 링크와 체크 포인트입니다.  
로컬 기준 Base URL: `http://localhost/gilime_mvp_01/`

---

## B1: ADMIN_QA_GAP_LIST.md

| 항목 | 내용 |
|------|------|
| **확인 방법** | 파일 존재 + 표 7행(P0/P1/P2 갭) 존재 |
| **링크(파일)** | 프로젝트 내 `docs/OPS/ADMIN_QA_GAP_LIST.md` (브라우저가 아닌 에디터/탐색기에서 열기) |
| **캡처** | 해당 파일을 연 상태에서 **GAP LIST 표**(Priority / Gap / Minimal Fix / Verification / Owner)가 회의록 §4 7행과 대응하는지 보이도록 스크린샷 |
| **기대** | P0 2행, P1 3행, P2 2행 등 회의록과 동일 구성 |

---

## B2: doc.php — 파싱 실패 시 error_code 표시

| 항목 | 내용 |
|------|------|
| **확인 방법** | `parse_status=failed`인 문서 상세에서 **최근 파싱 오류코드**가 "-"가 아닌 값으로 표시 |
| **링크** | `http://localhost/gilime_mvp_01/admin/doc.php?id=8` (문서 #8이 failed가 아니면 DB에서 parse_status=failed인 다른 문서 ID로 변경) |
| **캡처** | 문서 상단 메타 영역에서 **최근 파싱 오류코드(last_parse_error_code)** 항목이 보이도록 스크린샷. 값이 `PARSE_XXX`, `UNKNOWN` 등 실제 코드로 나오면 반영된 것. |
| **기대** | "—" 또는 "-"가 아닌, 오류 코드 문자열 표시 |

---

## B3: alert_ops.php — h() 및 입력 검증

| 항목 | 내용 |
|------|------|
| **확인 방법** | title 또는 본문에 `<script>alert(1)</script>` 입력 후 저장 → 목록/상세에서 **이스케이프되어 텍스트로만** 보이는지 |
| **링크(작성)** | `http://localhost/gilime_mvp_01/admin/alert_ops.php` |
| **절차** | 1) 새 알림 작성 폼에서 title에 `<script>alert(1)</script>` 입력 후 생성. 2) 알림 목록(같은 페이지 하단 또는 필터 "전체")에서 해당 알림 행 캡처. |
| **캡처** | 목록 테이블에서 해당 **title** 컬럼이 스크립트가 실행되지 않고 **그대로 문자열**로 보이는지 스크린샷. (alert 팝업이 뜨지 않아야 함) |
| **기대** | HTML/스크립트가 이스케이프되어 일반 텍스트로 표시 |

---

## B4: ops_control.php — 실패 상위 20건 empty state

| 항목 | 내용 |
|------|------|
| **확인 방법** | 실패 배달 0건일 때 테이블에 **"실패한 배달이 없습니다"** 문구 표시 |
| **링크** | `http://localhost/gilime_mvp_01/admin/ops_control.php` |
| **캡처** | **A. 전달(Deliveries) 재시도/백오프 현황** → **실패 상위 20건** 테이블이 비어 있을 때 나오는 문구가 "실패한 배달이 없습니다"인지 스크린샷. |
| **기대** | "데이터가 없습니다"가 아닌 **"실패한 배달이 없습니다"** |

---

## B5: ops_dashboard.php — 리스크 대기 정의 문장

| 항목 | 내용 |
|------|------|
| **확인 방법** | "검수가 필요한 문서" 테이블 위/아래에 **리스크 대기 정의** 1문장 존재 |
| **링크** | `http://localhost/gilime_mvp_01/admin/ops_dashboard.php` |
| **캡처** | **검수가 필요한 문서** 섹션에서, 테이블 위쪽 안내 문단이 보이도록 스크린샷. "리스크 대기 = pending 후보 중 match_method가 like_prefix 또는 NULL인 건수" 문장 포함 여부 확인. |
| **기대** | 위 정의 문장이 화면에 노출됨 |

---

## B6: CTO_ADMIN_BUILD_LIST.md

| 항목 | 내용 |
|------|------|
| **확인 방법** | 표에 B1~B6 행이 있는지 (파일 수정 없이 유지 확인) |
| **링크(파일)** | 프로젝트 내 `docs/OPS/CTO_ADMIN_BUILD_LIST.md` (에디터/탐색기에서 열기) |
| **캡처** | BUILD 목록 **표**가 보이도록 스크린샷. ID B1, B2, B3, B4, B5, B6 행이 모두 있는지 확인. |
| **기대** | B1~B6 행이 표에 존재 |

---

## 한눈에 보는 URL 정리 (로컬)

| Build | 확인할 화면 | URL |
|-------|-------------|-----|
| B1 | (파일) ADMIN_QA_GAP_LIST.md | `docs/OPS/ADMIN_QA_GAP_LIST.md` |
| B2 | 문서 상세(파싱 실패 문서) | `http://localhost/gilime_mvp_01/admin/doc.php?id=8` |
| B3 | 알림 운영 | `http://localhost/gilime_mvp_01/admin/alert_ops.php` |
| B4 | 운영 제어 | `http://localhost/gilime_mvp_01/admin/ops_control.php` |
| B5 | 운영 대시보드 | `http://localhost/gilime_mvp_01/admin/ops_dashboard.php` |
| B6 | (파일) CTO_ADMIN_BUILD_LIST.md | `docs/OPS/CTO_ADMIN_BUILD_LIST.md` |

---

**참고:** 문서 #8이 `parse_status=failed`가 아닌 경우, 관리자 문서 목록(`/admin/index.php` 또는 문서 허브)에서 파싱 상태가 "failed"인 문서를 골라 해당 문서의 `doc.php?id=NN`으로 B2 검증을 진행하면 됩니다.
