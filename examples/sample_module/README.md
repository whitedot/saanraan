# Sample Module

이 예시는 Toycore 모듈이 어떤 파일 계약으로 구성되는지 보여주는 최소 샘플입니다.

실제 설치 대상으로 쓰려면 이 디렉터리를 `modules/sample_notice`로 복사한 뒤 필요한 action/view/table을 프로젝트 목적에 맞게 수정하세요.

이 샘플은 Toycore `0.1.1`과 모듈 계약 `1.0` 기준으로 점검합니다.

포함된 파일:

- `module.php`: 모듈 메타데이터와 의존성 선언
- `paths.php`: 요청 method/path와 action 파일 매핑
- `admin-menu.php`: 관리자 메뉴 항목
- `output-slots.php`: 출력 슬롯 renderer 예시
- `install.sql`: 모듈 소유 테이블 예시

## 보안 기본값

모듈 action 파일은 직접 경로가 알려져도 안전해야 합니다.

- 관리자 화면은 action 시작 부분에서 `toy_member_require_login()`과 `toy_admin_require_role()`을 호출합니다.
- 상태를 바꾸는 `POST` 요청은 `toy_require_csrf()`를 먼저 통과해야 합니다.
- 관리자 상태 변경은 `toy_audit_log()`로 actor, target, result를 남깁니다.
- 화면에 출력하는 변수는 `toy_e()`로 escape합니다.
- 모듈 소유 테이블은 `toy_` prefix를 사용하고, 사용자 입력은 `PDO::prepare()`로 바인딩합니다.
