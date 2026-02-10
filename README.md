# Gilaime MVP (v0.6-4) - public/ 구조

## 폴더 구조(확정)
- /public/admin : 웹에서 접근하는 관리자 페이지(실제 URL은 /admin 로 유지)
- /app/inc      : PHP 공통 코드(config/db/auth)
- /storage      : 업로드/워처/로그(웹 직접 접근 차단)
- /tools        : 배치 스크립트(웹 직접 접근 차단)
- /sql          : 스키마/시드(웹 직접 접근 차단)

## XAMPP(htdocs)에서 실행
1) `C:\xampp\htdocs\gilime_mvp_01\` 에 이 폴더를 그대로 복사
2) Apache 재시작
3) 접속
   - http://localhost/gilime_mvp_01/admin/login.php

## 왜 /public/admin 인데 URL은 /admin 인가?
- XAMPP 기본은 프로젝트 루트가 webroot라서,
  `.htaccess`로 `/admin/*` 요청을 `/public/admin/*`로 rewrite 합니다.
- 운영 서버에서는 DocumentRoot를 `/public`으로 두는 방식이 더 흔합니다(추후 전환).
