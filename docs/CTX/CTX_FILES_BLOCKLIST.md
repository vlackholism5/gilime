# CTX_FILES_BLOCKLIST — GILIME v1.0
LLM이 절대 읽으면 안 되는 경로/파일 목록 (토큰 폭발 + 보안 위험)

## 1) 대용량/런타임/업로드(운영 데이터)
- data/external_big/**
- data/runtime/**
- public/uploads/**

## 2) 레거시 데이터 폴더(안전장치)
- data/osm/**
- data/public/**
- data/inbound/**
- data/derived/**
- data/generated/**

## 3) 시크릿/로컬 환경(절대 공유 금지)
- app/inc/config/config.local.php
- app/inc/config/*.local.php
- .env
- .env.local

## 4) 바이너리/패키지(불필요)
- vendor/**
- composer.phar
- *.zip
- *.pbf
- *.pdf
- *.png
- *.jpg
- *.exe