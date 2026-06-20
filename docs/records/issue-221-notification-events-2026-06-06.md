# 이슈 221 알림 이벤트 검토 기록

이 문서는 모듈별 사이트 알림 이벤트 후보 검토와 1차 구현 범위를 기록한다.

## 현재 템플릿 기반 이벤트

| 모듈 | 이벤트 | 대상 | 기본 채널 | 예시 |
| --- | --- | --- | --- | --- |
| `point` | `transaction.grant`, `transaction.refund`, `transaction.use`, `transaction.expire`, `transaction.adjustment.increase`, `transaction.adjustment.decrease` | 회원 | `site` | 포인트 지급/복원/사용/만료/조정 알림 |
| `reward` | `transaction.grant`, `transaction.refund`, `transaction.use`, `transaction.expire`, `transaction.reclaim`, `transaction.adjustment.increase`, `transaction.adjustment.decrease` | 회원 | `site` | 적립금 지급/복원/사용/만료/회수/조정 알림 |
| `deposit` | `transaction.deposit`, `transaction.refund`, `transaction.use`, `transaction.withdraw`, `transaction.adjustment.increase`, `transaction.adjustment.decrease` | 회원 | `site` | 예치금 입금/복원/사용/출금/조정 알림 |
| `coupon` | `issue.created`, `issue.status_updated`, `redemption.redeemed`, `redemption.refunded` | 회원 | `site` | 쿠폰·이용권 발급/상태 변경/사용/환불 알림 |
| `content` | `comment.created`, `comment.mention` | 콘텐츠 작성자, 멘션 회원 | `site` | 콘텐츠 댓글 작성자 알림, 콘텐츠 댓글 멘션 알림 |
| `community` | `comment.created`, `comment.mention` | 게시글 작성자, 멘션 회원 | `site` | 게시글 댓글 작성자 알림, 커뮤니티 댓글 멘션 알림 |
| `quiz` | `comment.mention` | 멘션 회원 | `site` | 퀴즈 댓글 멘션 알림 |
| `survey` | `comment.mention` | 멘션 회원 | `site` | 설문 댓글 멘션 알림 |
| `notification` | `member_push_endpoint.connected`, `member_push_endpoint.disabled` | 회원 | `site` | 회원 Telegram 푸시 수신처 연결/해제 보안 알림 |

반복 이벤트는 `sr_notification_event_templates`의 DB 템플릿을 사용한다. 이벤트 알림은 `source_module_key`, `event_key`, `metadata_json`을 저장하고 제목은 표시/발송 시 템플릿에서 생성한다. `sr_notifications.title`은 관리자 수동 알림과 기존 데이터 호환 fallback으로 유지한다. 알림 모듈이 비활성화되었거나 템플릿이 누락되면 소비 모듈 작업은 실패하지 않고 no-op으로 처리한다.

## 아직 직접 생성하는 이벤트

| 모듈 | 이벤트 | 후속 기준 |
| --- | --- | --- |
| `notification` | 관리자 수동 알림 | 관리자가 문구를 직접 입력하므로 직접 생성 유지 |
| `community` | 쪽지 수신 | 후속 템플릿화 후보 |
| `community` | 신고 관리자 알림 | 관리자 운영 알림 scope와 권한 재검증 기준 확정 후 템플릿화 |
| `community` | 닉네임 강제 초기화 | 운영 조치 필수 알림 기준과 함께 후속 템플릿화 |

## 1차 구현 결정

- `module_key=content/community`, `event_key=comment.created/comment.mention` 기본 템플릿을 알림 모듈 설치 SQL과 `2026.06.002` 업데이트 SQL에 추가한다.
- `module_key=quiz/survey`, `event_key=comment.mention` 기본 템플릿은 퀴즈/설문 댓글 작성 기능과 함께 알림 모듈 설치 SQL과 `2026.06.004` 업데이트 SQL에 추가한다.
- 콘텐츠/커뮤니티 댓글 알림 생성은 `notification-events.php`의 `create_account_event_function` 계약으로 전환한다.
- 자기 알림은 제외한다. 멘션 알림은 댓글 작성자와 글/콘텐츠 작성자를 대상에서 제외한다. 퀴즈/설문 댓글은 작성자 자신을 제외하고, 비밀 댓글은 멘션 알림을 만들지 않는다.
- 댓글 작성자 알림과 멘션 알림은 알림 모듈 비활성화 또는 템플릿 누락 시 no-op으로 처리하고 댓글 저장을 실패시키지 않는다.

## 후속 분리

- `notification-catalog.php`는 1차 런타임 계약으로 도입하지 않는다. 도입 시 seed/default 후보와 설정 UI metadata 선언 전용으로 제한한다.
- 관리자 운영 알림 드롭다운은 개인 알림과 별도 scope로 구분하고, 권한 재검증은 `notification-permissions.php` 같은 좁은 계약에서 다룬다.
- 회원별 알림 설정, 관리자 알림 기본값, dedupe key, no-op 결과 기록, 필수 알림 사유와 개인정보 export/익명화 범위는 별도 스키마 이슈에서 확정한다.
