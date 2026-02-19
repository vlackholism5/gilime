# app/inc — 공통 PHP 인클루드

역할별 서브폴더 구조.

| 폴더 | 용도 | 주요 파일 |
|------|------|-----------|
| **config/** | 설정·환경 | config.php, config.local.php, config.local.php.example, config.safe.php |
| **auth/** | 인증·DB·세션 | auth.php, db.php, user_session.php |
| **alert/** | 알림 도메인 | alert_event_type.php, alert_delivery.php, subscription_match.php |
| **parse/** | PDF/셔틀 파싱 | pdf_parser.php, temp_shuttle_parser.php |
| **route/** | 경로 검색 | route_finder.php |
| **admin/** | 관리자 UI | admin_header.php |
| **lib/** | 공통 유틸 | error_normalize.php, observability.php |

루트: `test_db_connection.php` (DB 접속 테스트, 배포 시 삭제 권장).
