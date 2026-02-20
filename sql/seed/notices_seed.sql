-- Seed data for notices (MVP smoke test)
SET NAMES utf8mb4;

INSERT INTO notices (category, label, status, is_pinned, title, body_md, starts_at, ends_at, published_at)
VALUES
('notice', '공지', 'published', 1, '길라임 홈 지도 UI가 개선되었습니다.', '홈 화면 아이콘/탭/필터칩이 공통 규격으로 갱신되었습니다.', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('notice', '안내', 'published', 0, '임시셔틀 경로 우선 반영 안내', '이슈 기반 경로 계산 시 임시셔틀 옵션이 기본 적용될 수 있습니다.', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('event', '이벤트', 'published', 0, '출퇴근 구독 설정 이벤트', '구독을 1개 이상 설정하면 이후 업데이트를 우선 노출합니다.', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), NOW()),
('event', '이벤트', 'archived', 0, '지난 이벤트 예시', '종료된 이벤트는 이벤트 탭에서 종료 상태로 표시됩니다.', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY));
