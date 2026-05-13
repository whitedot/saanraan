# Sample Module

이 예시는 산란 모듈이 어떤 파일 계약으로 구성되는지 보여주는 최소 샘플입니다.

실제 설치 대상으로 쓰려면 이 디렉터리를 `modules/sample_notice`로 복사한 뒤 필요한 action/view/table을 프로젝트 목적에 맞게 수정하세요.

이 샘플은 산란 최소 `0.2.0`, 모듈 계약 `2.0` 기준으로 점검합니다.

포함된 파일:

- `module.php`: 모듈 메타데이터와 의존성 선언
- `paths.php`: 요청 method/path와 action 파일 매핑
- `admin-menu.php`: 관리자 메뉴 항목
- `output-slots.php`: 출력 슬롯 renderer 예시
- `install.sql`: 모듈 소유 테이블 예시

## 보안 기본값

모듈 action 파일은 직접 경로가 알려져도 안전해야 합니다.

- 관리자 화면은 action 시작 부분에서 `sr_member_require_login()`과 `sr_admin_require_role()`을 호출합니다.
- 상태를 바꾸는 `POST` 요청은 `sr_require_csrf()`를 먼저 통과해야 합니다.
- 응답 종료는 `sr_redirect()`, `sr_render_error()`, `sr_finish_response()`를 통과하고 action에서 `exit`, `die`, `header('Location: ...')`를 직접 호출하지 않습니다.
- 관리자 상태 변경은 `sr_audit_log()`로 actor, target, result를 남깁니다.
- 화면에 출력하는 변수는 `sr_e()`로 escape합니다.
- 모듈 소유 테이블은 `sr_` prefix를 사용하고, 사용자 입력은 `PDO::prepare()`로 바인딩합니다.
