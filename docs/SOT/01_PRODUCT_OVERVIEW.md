# SoT 01 — Product Overview

## Purpose

Single source of truth for product scope, target users, and value proposition. Locks core scenario for MVP planning.

## Definitions

- **Gilime (길라임):** 서비스명. 노선·정류장·공지·알림을 한 곳에서 제공하는 대상.
- **MVP:** 2026-02-20 기준 "1개 이슈 완주 데모"로 확정하는 최소 기능 세트.
- **Scenario C:** Core scenario (locked). 외부 라우팅 베이스 + 셔틀 구간 삽입 등 경로 안내 시나리오 (확인 필요: 상세 정의 위치).

## Problem

- 통근자에게 **노선·정류장·공지·알림**을 한 곳에서 제공하는 수요가 있음.
- 현재는 관리자만 PDF 파싱·검수·승격 플로우를 사용하며, 일반 사용자용 화면과 구독/알림이 확장 중.

## Target users

- **Commuter user:** 내가 타는 노선·정류장·공지·알림 확인. 노선 검색, 정류장 보기, 알림 구독, 공지 피드 보기.
- **Admin operator:** PDF 파싱·후보 검수·승격·품질 관리. doc/route_review/review_queue/ops_dashboard 활용.

## Value proposition

- 사용자: 구독 노선 기준 경로 안내(정류장 순서) + 알림 피드.
- 관리자: 업로드 → 파싱/매칭 → 검수 → 승격 → 감사까지 일원화된 운영 파이프라인.

## Seoul-only constraint

- MVP 범위는 **서울 1개 이슈 기준** 운영형 데모. 대규모 지역 확장(서울 외)은 비범위.

## Core scenario LOCK

- **Scenario C (선택 확정):** 경로 안내 시 "외부 라우팅 베이스 + 셔틀 구간 삽입"을 MVP Core에 포함. (상세 플로우는 04_MVP_PRD·05_SYSTEM_ARCHITECTURE 참고.)

## Assumptions

- 기존 SoT·매칭 로직·promote 규칙은 변경하지 않는다.
- 사용자 인증은 MVP에서 쿠키/세션 기반 유지(프로덕션 교체 시점 확인 필요).
- 알림은 배치/인앱 피드 우선; 실시간 푸시는 비범위.

## Open Questions (확인 필요)

- Scenario A·B의 정확한 정의 및 문서 위치.
- 프로덕션 로그인/회원가입 도입 시점 및 범위.
- "외부 라우팅 베이스" 연동 대상(API/데이터 소스) 구체화.
