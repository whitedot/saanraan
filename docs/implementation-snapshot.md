# 1.0 전 구현 스냅샷

이 문서는 GitHub Wiki 구현 명세를 1.0 배포 정리 때 맞추기 전까지 저장소 안에서 현재 구현의 핵심 표면을 빠르게 확인하기 위한 임시 스냅샷이다. 최종 DB 명세, 관리자 화면별 필드 가이드, 온보딩 문서는 Wiki를 우선한다.

## 런타임 기준

| 항목 | 현재 값 |
| --- | --- |
| 본체 버전 | `0.2.0` |
| 모듈 계약 버전 | `2.0` |
| PHP 기준 | `8.1` 이상 |
| DB 기준 | MySQL 또는 MySQL 호환 DB, `pdo_mysql` |
| 요청 흐름 | `index.php`가 활성 모듈의 `paths.php`를 읽고 action 파일을 명시적으로 include |
| 기본 DB prefix | `sr_` |

## 번들 모듈

현재 저장소에는 다음 16개 모듈 또는 플러그인이 포함되어 있다.

| 분류 | key | 성격 |
| --- | --- | --- |
| 시스템 | `admin` | 관리자 대시보드, 설정, 메뉴, 모듈, 업데이트, 권한 |
| 회원 | `member` | 계정, 인증, 프로필, 그룹, 탈퇴 |
| 회원 | `point` | 포인트 잔액과 거래 원장 |
| 회원 | `reward` | 적립금 잔액과 거래 원장 |
| 회원 | `deposit` | 예치금 잔액과 거래 원장 |
| 회원 | `coupon` | 쿠폰·이용권 정의, 발급, 사용 이력 |
| 사이트 | `site_menu` | 사이트 공통 메뉴 |
| 사이트 | `logo_manager` | 로고 자산과 기간별 대체 |
| 사이트 | `content` | 콘텐츠 작성, 공개 URL, 콘텐츠 그룹 |
| 사이트 | `banner` | 출력 슬롯용 배너 |
| 사이트 | `popup_layer` | 팝업레이어 |
| 사이트 | `seo` | robots, sitemap, SEO 보조 |
| 서비스 | `community` | 게시판, 댓글, 신고, 스크랩, 쪽지 |
| 운영 | `notification` | 사이트 알림, 이메일 delivery queue |
| 운영 | `privacy` | 개인정보 요청과 사본 제공 조정 |
| 플러그인 | `ckeditor` | textarea 에디터 강화 |

## 주요 요청 표면

요청 매핑은 각 모듈의 `paths.php`가 정본이다. 아래 표는 1.0 전 검증과 문서 탐색을 위한 요약이다.

| 영역 | 대표 경로 |
| --- | --- |
| 설치 | 미설치 상태의 모든 요청은 설치 흐름으로 진입 |
| 공개 홈 | `/`, `/ui-kit` |
| 회원 | `/login`, `/register`, `/account`, `/account/withdraw`, `/password/reset`, `/email/verify`, `/logout` |
| 관리자 공통 | `/admin`, `/admin/settings`, `/admin/homepage`, `/admin/menu`, `/admin/modules`, `/admin/updates`, `/admin/roles`, `/admin/audit-logs`, `/admin/retention` |
| 콘텐츠 | `/content/*`, `/content/group`, `/content/download`, `/content/action`, `/admin/content`, `/admin/content/settings`, `/admin/content-groups` |
| 커뮤니티 | `/community`, `/community/board`, `/community/post`, `/community/write`, `/community/edit`, `/community/comment`, `/community/report`, `/community/scraps`, `/community/messages` |
| 커뮤니티 관리자 | `/admin/community/settings`, `/admin/community/boards`, `/admin/community/board-groups`, `/admin/community/posts`, `/admin/community/comments`, `/admin/community/reports` |
| 회원 자산 | `/account/points`, `/account/rewards`, `/account/deposits`, `/account/coupons` |
| 회원 자산 관리자 | `/admin/points`, `/admin/rewards`, `/admin/deposits`, `/admin/coupons` |
| 사이트 운영 | `/admin/site-menus`, `/admin/logo-manager`, `/admin/banners`, `/admin/popup-layers`, `/admin/seo`, `/robots.txt`, `/sitemap.xml` |
| 알림/개인정보 | `/account/notifications`, `/admin/notifications`, `/admin/notification-deliveries`, `/account/privacy-requests`, `/admin/privacy-requests` |

## 주요 DB 테이블

테이블명은 기본 prefix `sr_` 기준으로 적는다. 설치 prefix가 다르면 런타임 SQL 변환 기준을 따른다.

| 영역 | 테이블 |
| --- | --- |
| 코어 | `sr_site_settings`, `sr_modules`, `sr_module_settings`, `sr_sessions`, `sr_rate_limits`, `sr_schema_versions`, `sr_audit_logs` |
| 관리자 | `sr_admin_account_permissions`, `sr_admin_account_roles`, `sr_admin_menu_overrides` |
| 회원 | `sr_member_accounts`, `sr_member_profiles`, `sr_member_sessions`, `sr_member_auth_logs`, `sr_member_email_verifications`, `sr_member_password_resets`, `sr_member_consents`, `sr_member_groups`, `sr_member_group_memberships`, `sr_member_group_membership_logs`, `sr_member_group_rules` |
| 개인정보 | `sr_privacy_requests` |
| 콘텐츠 | `sr_content_items`, `sr_content_revisions`, `sr_content_groups`, `sr_content_group_settings`, `sr_content_setting_sources`, `sr_content_files`, `sr_content_asset_access_logs`, `sr_content_access_entitlements`, `sr_content_asset_action_logs` |
| 커뮤니티 | `sr_community_boards`, `sr_community_board_groups`, `sr_community_board_settings`, `sr_community_board_group_settings`, `sr_community_board_setting_sources`, `sr_community_posts`, `sr_community_comments`, `sr_community_attachments`, `sr_community_reports`, `sr_community_scraps`, `sr_community_messages`, `sr_community_member_nicknames`, `sr_community_levels`, `sr_community_account_levels`, `sr_community_level_logs`, `sr_community_asset_logs`, `sr_community_access_entitlements` |
| 회원 자산 | `sr_point_balances`, `sr_point_transactions`, `sr_reward_balances`, `sr_reward_transactions`, `sr_deposit_balances`, `sr_deposit_transactions`, `sr_coupon_definitions`, `sr_coupon_issues`, `sr_coupon_redemptions` |
| 사이트 운영 | `sr_site_menus`, `sr_site_menu_items`, `sr_logo_manager_assets`, `sr_logo_manager_assignments`, `sr_banners`, `sr_banner_targets`, `sr_banner_clicks`, `sr_popup_layers`, `sr_popup_layer_targets` |
| 알림 | `sr_notifications`, `sr_notification_reads`, `sr_notification_deliveries`, `sr_notification_event_templates` |

상세 컬럼과 인덱스는 설치 SQL과 Wiki DB 명세를 정본으로 본다. 이 문서는 1.0 전 구현 표면을 빠르게 확인하기 위한 보조 문서다.

현재 포인트, 적립금, 예치금은 각 모듈의 balance/transaction 테이블을 사용하고, 공통 원자적 갱신은 `core/helpers/ledger.php`의 helper로 줄인다. 쿠폰·이용권은 통합 금액 원장에 합치지 않고 `sr_coupon_issues`와 `sr_coupon_redemptions`로 발급 상태와 사용 이력을 따로 기록한다. 현재 런타임 연동은 콘텐츠/커뮤니티 유료 열람에서 쿠폰을 먼저 사용하고, 사용할 쿠폰이 없으면 포인트/적립금/예치금 차감으로 내려가는 구조다. 할인액, 정액 차감권, 주문 할인처럼 금액 계산이 필요한 쿠폰 유형은 쿠폰 모듈이 단독으로 먼저 일반화하지 않고, 실제 적용 도메인이 생길 때 해당 모듈과 함께 계약을 정의한다.

## 검증 기준

현재 정적 기준은 다음 명령으로 확인한다.

```sh
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n 1 php -l
php .tools/bin/check.php
```

HTTP와 브라우저 검증은 [스모크 테스트 기준](smoke-test.md)을 따른다. 1.0 릴리스 후보에서는 정적 점검 통과만으로 완료 판정하지 않고, 새 설치와 관리자 주요 흐름을 브라우저에서 확인한 기록을 남긴다.
