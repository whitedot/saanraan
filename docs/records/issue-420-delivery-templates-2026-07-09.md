# Issue #420 Delivery Template Decisions

이 기록은 발송 템플릿과 회원 알림 이벤트 템플릿 관리 기능의 구현 기준이다.

## 결정

- 기본 문구의 canonical 소유자는 각 모듈의 `delivery-templates.php` 계약이다. 새 transactional email 기본값은 SQL seed에 두지 않는다.
- 관리자 override는 코어 테이블 `sr_delivery_template_overrides`의 명시 row로 저장한다. 기본값 복원은 row 삭제다.
- transactional email override는 `/admin/delivery-templates`에서 저장하고 복원한다. 회원 인증 메일(`member.email_verification`, `member.password_reset`, `member.login_mfa_email_code`)은 회원 보안 알림/메일 관리 화면인 `/admin/member-notification-templates`에서도 같은 override 저장/복원 흐름으로 관리한다.
- 회원 알림 이벤트 템플릿은 중앙 편집 화면이 아니라 해당 이벤트를 만드는 모듈의 알림/메일 관리 화면에서 관리한다. 제목/본문은 `sr_notification_event_templates`에 저장하고, 포인트/적립금/예치금/쿠폰처럼 기존 케이스별 사용 여부와 채널 설정을 가진 모듈은 같은 화면에서 모듈 설정도 함께 저장한다.
- 환전 출금/입금/수수료 알림은 환전 모듈이 아니라 포인트/적립금/예치금 모듈의 알림/메일 관리 화면에서 관리한다.
- `member.email_verification`은 가입, 재발송, 로그인 유발 재발송, OAuth 가입 완료에서 공유하는 단일 key다.
- 회원 보안 완료 이벤트는 `member.security.*` 알림 이벤트로 분리하고 `/admin/member-notification-templates`에서 관리한다.
- 정책 문서 변경 안내는 1차 범위에서 subject만 계약으로 이동하고 body builder는 유지한다.
- transactional email은 override가 없거나, 필수 placeholder가 없거나, 렌더 결과 subject/body 필수 위치가 비거나, 렌더 중 오류가 나면 계약 default로 fallback한다.
- notification event의 inactive는 기존 no-op 의미를 유지하고, transactional email의 inactive/깨진 override는 default fallback 의미로 처리한다.
- 테스트 발송은 edit 권한을 요구하고, 수신자는 현재 관리자 계정 이메일로 고정하며, `sr_rate_limits` 기반 rate limit을 적용한다. notification pipeline 템플릿은 notification 모듈 이메일 설정을 임시 런타임 mail config로 사용한다.
- notification event의 `link_template`은 내부 클릭 대상용 metadata로 유지하되, 관리자 화면에서는 별도 입력항목으로 노출하지 않고 본문에 합쳐 편집한다. 이메일과 외부 채널 발송 조립은 본문에 같은 상대/절대 URL이 이미 있으면 링크를 추가로 붙이지 않는다.

## Issue #221 대체

Issue #221은 계정 이벤트 제목을 표시/발송 시점의 현재 템플릿과 metadata로 재렌더하는 설계를 기록했다. #420에서는 운영자 자유 편집 UI가 열리면서 이 trade-off가 바뀌었다. 생성 후 템플릿 수정이 과거 알림 제목을 소급 변경하고, 본문 snapshot과 제목 live render가 의미 불일치를 만들 수 있기 때문이다.

따라서 계정 이벤트 제목은 생성 시점의 `sr_notifications.title` snapshot을 정본으로 한다. 화면 표시, delivery 발송, privacy export는 저장된 제목을 사용하고 현재 템플릿으로 제목을 다시 만들지 않는다. `source_module_key`, `event_key`, `metadata_json`은 감사, 호환, 향후 재처리용 metadata로 남긴다.
