# GPT 검수 파이프라인 (Python + OpenAPI) v1.7-20

## 개요

후보 목록을 **Python + OpenAPI(GPT)** 로 자동 검수한 뒤, 결과를 시스템에 일괄 반영하는 파이프라인입니다.

```
[route_review] CSV/JSON 내보내기
       ↓
[candidates.json]
       ↓
[gpt_review_pipeline.py] Python + OpenAPI 호출
       ↓
[review_results.json]
       ↓
[route_review] 검수 결과 일괄 반영 업로드
       ↓
[승격] Route Stops 반영
```

## 사전 준비

### 1. Python 패키지

```powershell
cd c:\xampp\htdocs\gilime_mvp_01\scripts\python
pip install -r requirements-gpt.txt
```

### 2. OpenAPI 설정

환경변수로 API 키 등을 설정합니다.

**방법 A: .env 파일 (권장)**

```powershell
# gpt_review_config.example.env 를 참고하여 .env 생성
copy gpt_review_config.example.env .env
# .env 편집 후 OPENAI_API_KEY 등 입력
```

**방법 B: 환경변수 직접 설정**

```powershell
$env:OPENAI_API_KEY = "sk-your-api-key-here"
$env:OPENAI_MODEL = "gpt-4o-mini"
```

**지원 변수**

| 변수 | 필수 | 설명 |
|------|------|------|
| `OPENAI_API_KEY` | ○ | API 키 |
| `OPENAI_BASE_URL` |  | 커스텀 엔드포인트 (예: Azure OpenAI) |
| `OPENAI_MODEL` |  | 모델명 (기본: gpt-4o-mini) |

## 실행

### 1. 후보 내보내기

route_review 페이지에서 **JSON 다운로드** 클릭  
→ `candidates_9_운행구간.json` 저장

### 2. GPT 검수 실행

```powershell
python gpt_review_pipeline.py --input candidates_9_운행구간.json --output review_results.json --route-label "운행구간"
```

### 3. 결과 업로드

route_review 페이지에서 **검수 결과 일괄 반영** 폼으로 `review_results.json` 업로드

### 4. 승격

모든 후보 처리 후 **승인 후보를 Route Stops로 승격** 클릭

## 옵션

| 옵션 | 설명 |
|------|------|
| `--input`, `-i` | 후보 JSON/CSV 경로 |
| `--output`, `-o` | 검수 결과 JSON 경로 |
| `--route-label` | 노선 라벨 (GPT 컨텍스트) |
| `--batch-size` | 요청당 후보 수 (기본: 50) |
| `--dry-run` | API 호출 없이 입력만 검증 |

## .env 로드 (선택)

pip install python-dotenv 후:

```python
# gpt_review_pipeline.py 상단에 추가
from dotenv import load_dotenv
load_dotenv()
```

또는 실행 전:

```powershell
Get-Content .env | ForEach-Object { if ($_ -match '^([^#=]+)=(.*)$') { [Environment]::SetEnvironmentVariable($matches[1], $matches[2].Trim(), 'Process') } }
python gpt_review_pipeline.py -i x.json -o y.json
```

## 출력 형식

`review_results.json` 예시:

```json
{
  "candidates": [
    {"candidate_id": 101, "action": "approve", "matched_stop_id": "12345", "matched_stop_name": "성동세무서"},
    {"candidate_id": 102, "action": "reject"}
  ],
  "meta": {"route_label": "운행구간", "count": 2}
}
```

이 형식은 `import_candidate_review.php` 업로드와 호환됩니다.
