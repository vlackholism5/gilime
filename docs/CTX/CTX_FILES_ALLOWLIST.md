# CTX_FILES_ALLOWLIST — GILIME v1.0
LLM이 읽어도 되는 "작은 텍스트 파일" 목록. (작업 시 여기서 최대 3개만 선택)

## 1) 엔트리/인덱스
- docs/CTX/CTX_ENTRY.md
- docs/SOT/SOT_INDEX.md (없으면 추후 생성)
- docs/OPS/OPS_00_RUNBOOK.md (없으면 추후 생성)
- sql/SQL_INDEX.md (없으면 추후 생성)

## 2) 핵심 앱 코드(요청 시에만, 필요한 파일만)
- public/user/home.php
- public/user/route_finder.php
- public/assets/js/home_map.js
- public/assets/js/route_autocomplete.js
- public/assets/js/bottom_sheet_detents.js
- public/assets/css/gilaime_ui.css

## 3) 설정/예시 파일(시크릿 없는 예시만)
- app/inc/config/config.local.php.example
- scripts/python/gpt_review_config.example.env

## 4) 문서(필요한 페이지만)
- docs/SOT/*
- docs/OPS/*
- docs/ui/*
- docs/operations/*