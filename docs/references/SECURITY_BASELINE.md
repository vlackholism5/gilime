# GILIME — Security Baseline (1 page)

**목적:** MVP1.5 기준 보안 정책 기준선. 구현 여부는 코드 검증 후 확정. 불확실한 항목은 "확인 필요"로 표시.

---

## Admin access policy

- **접근 대상:** `/admin/*` 는 **관리자(운영자) 전용**. 비인증 사용자는 `login.php`로 리다이렉트.
- **현재 구현:** `require_admin()` 가 `$_SESSION['user_id']` 존재 여부만 검사. 로그인 시 user_id 저장, 미존재 시 `/admin/login.php` 로 이동.
- **역할 구분:** PoC 단계에서는 role 체크 없음. 운영 시 관리자/운영자 역할 구분 추가 권장. (확인 필요: 역할 테이블·권한 매트릭스 도입 시점)

---

## Session handling baseline

- **타임아웃:** 서버 측 세션 만료 시간은 `php.ini`(session.gc_maxlifetime 등)에 의존. **코드 내 명시적 타임아웃 설정 여부 확인 필요.**
- **쿠키 플래그:** 로그아웃 시 `session_get_cookie_params()` 로 path/domain/secure/httponly 를 사용해 쿠키 삭제. **로그인 시 쿠키 설정(httponly, secure, SameSite) 확인 필요.**
- **세션 재생성:** 로그인 후 session_regenerate_id() 호출 여부 **확인 필요.** (세션 고정 방지)
- **기본 원칙:** 세션은 최소 권한·최소 유효시간. 민감 동작(승인/거절/승격) 전 재인증은 현재 범위 외.

---

## Minimal audit log scope

- **기록 대상(정책):** 다음 행위는 추후 감사 로그에 남기는 것을 권장.
  - 관리자 로그인/로그아웃
  - Approve / Reject (candidate_id, doc_id, route_label, user_id)
  - Promote (doc_id, route_label, user_id)
  - Alias 등록/수정
  - PARSE_MATCH job 실행
- **현재 구현:** 별도 감사 로그 테이블·파일 로깅은 **미구현.** (확인 필요: storage/ 로그에 일부 기록 여부)

---

## Out-of-scope (현재)

- **OAuth / 소셜 로그인:** 미적용. ID/비밀번호(또는 단일 계정) 방식만 가정.
- **2FA(MFA):** 미적용.
- **IP 화이트리스트 / 접근 제한:** 미정책.
- **비밀번호 정책(만료, 복잡도):** 애플리케이션 레벨에서 미적용. (서버/DB 정책은 별도.)

---

*문서 버전: v1.3-06 (docs-only). 구현 상태는 코드/배포 검증 후 갱신.*
