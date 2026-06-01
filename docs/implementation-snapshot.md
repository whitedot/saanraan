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

현재 저장소에는 다음 17개 모듈 또는 플러그인이 포함되어 있다.

| 분류 | key | 성격 |
| --- | --- | --- |
| 시스템 | `admin` | 관리자 대시보드, 설정, 메뉴, 모듈, 업데이트, 권한 |
| 회원 | `member` | 계정, 인증, 프로필, 그룹, 탈퇴 |
| 회원 | `point` | 포인트 잔액과 거래 원장 |
| 회원 | `reward` | 적립금 잔액과 거래 원장 |
| 회원 | `deposit` | 예치금 잔액과 거래 원장 |
| 회원 | `asset_exchange` | 포인트/금액 항목 간 환전 정책과 실행 로그 |
| 회원 | `coupon` | 쿠폰·이용권 종류, 지급, 사용 이력 |
| 사이트 | `site_menu` | 사이트 공통 메뉴 |
| 사이트 | `logo_manager` | 기본 로고와 이벤트 로고 |
| 사이트 | `banner` | 출력 슬롯용 배너 |
| 사이트 | `popup_layer` | 팝업레이어 |
| 사이트 | `seo` | robots, sitemap, SEO 보조 |
| 서비스 | `content` | 콘텐츠 작성, 공개 URL, 콘텐츠 그룹 |
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
| 콘텐츠 | `/content/*`, `/content/group`, `/content/download`, `/content/action`, `/content/comment`, `/admin/content`, `/admin/content/series`, `/admin/content/settings`, `/admin/content-groups` |
| 커뮤니티 | `/community`, `/community/board`, `/community/post`, `/community/write`, `/community/edit`, `/community/series`, `/community/comment`, `/community/report`, `/community/scraps`, `/community/messages` |
| 커뮤니티 관리자 | `/admin/community/settings`, `/admin/community/boards`, `/admin/community/board-groups`, `/admin/community/series`, `/admin/community/posts`, `/admin/community/comments`, `/admin/community/reports` |
| 회원 자산 | `/account/points`, `/account/rewards`, `/account/deposits`, `/account/asset-exchange`, `/account/coupons` |
| 회원 자산 관리자 | `/admin/points`, `/admin/rewards`, `/admin/deposits`, `/admin/asset-exchange`, `/admin/asset-exchange/settings`, `/admin/asset-exchange/logs`, `/admin/coupons`, `/admin/coupons/issues`, `/admin/coupons/redemptions` |
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
| 콘텐츠 | `sr_content_items`, `sr_content_revisions`, `sr_content_groups`, `sr_content_group_settings`, `sr_content_setting_sources`, `sr_content_series`, `sr_content_series_items`, `sr_content_comments`, `sr_content_link_refs`, `sr_content_asset_policy_sets`, `sr_content_files`, `sr_content_file_links`, `sr_content_file_download_logs`, `sr_content_asset_access_logs`, `sr_content_access_entitlements`, `sr_content_asset_action_logs` |
| 커뮤니티 | `sr_community_boards`, `sr_community_board_groups`, `sr_community_board_settings`, `sr_community_board_group_settings`, `sr_community_board_setting_sources`, `sr_community_asset_policy_sets`, `sr_community_categories`, `sr_community_series`, `sr_community_series_items`, `sr_community_series_scraps`, `sr_community_posts`, `sr_community_comments`, `sr_community_link_refs`, `sr_community_attachments`, `sr_community_reports`, `sr_community_scraps`, `sr_community_messages`, `sr_community_member_nicknames`, `sr_community_levels`, `sr_community_account_levels`, `sr_community_level_logs`, `sr_community_asset_logs`, `sr_community_access_entitlements` |
| 회원 자산 | `sr_point_balances`, `sr_point_transactions`, `sr_reward_balances`, `sr_reward_transactions`, `sr_deposit_balances`, `sr_deposit_transactions`, `sr_asset_exchange_policies`, `sr_asset_exchange_logs`, `sr_coupon_definitions`, `sr_coupon_issues`, `sr_coupon_redemptions` |
| 사이트 운영 | `sr_site_menus`, `sr_site_menu_items`, `sr_logo_manager_assets`, `sr_logo_manager_assignments`, `sr_banners`, `sr_banner_targets`, `sr_banner_clicks`, `sr_popup_layers`, `sr_popup_layer_targets` |
| 알림 | `sr_notifications`, `sr_notification_reads`, `sr_notification_deliveries`, `sr_notification_event_templates` |

상세 컬럼과 인덱스는 설치 SQL과 Wiki DB 명세를 정본으로 본다. 이 문서는 1.0 전 구현 표면을 빠르게 확인하기 위한 보조 문서다.

현재 포인트, 적립금, 예치금은 각 모듈의 balance/transaction 테이블을 사용하고, 공통 원자적 갱신은 `core/helpers/ledger.php`의 helper로 줄인다. 관리자 수동 조정은 1,000,000 초과 시 별도 승인자 확인을 요구하고, 자산별 1회 10,000,000과 관리자별 일일 10,000,000 절대금액 상한을 서버에서 검증한다. 쿠폰·이용권은 통합 금액 원장에 합치지 않고 `sr_coupon_issues`와 `sr_coupon_redemptions`로 발급 상태와 사용 이력을 따로 기록한다. 현재 런타임 연동은 콘텐츠/커뮤니티 유료 열람에서 쿠폰을 먼저 사용하고, 사용할 쿠폰이 없으면 포인트/적립금/예치금 차감으로 내려가는 구조다. 환전 모듈은 무수수료 양방향 정책 저장 시 소액 순환 가치 증가를 차단하고, 독립 실행 환전에서 deadlock/lock wait timeout 계열 오류를 짧게 재시도하며, 완료 환전 묶음은 `sr_asset_exchange_correct_completed_group()` helper로 반대 원장 거래를 남겨 정정할 수 있다. 콘텐츠/커뮤니티 복합 자산 처리도 상위 작업 단위에서 retryable DB 잠금 오류를 재시도한다. 할인액, 정액 차감권, 주문 할인처럼 금액 계산이 필요한 쿠폰 유형은 쿠폰 모듈이 단독으로 먼저 일반화하지 않고, 실제 적용 도메인이 생길 때 해당 모듈과 함께 계약을 정의한다.

모듈 간 링크 카드는 `{{sr_link_card module="community" entity_type="post" entity_id="1" variant="compact" label="선택 제목" slot="body"}}` 토큰으로 저장한다. 코어 helper는 토큰 파싱, 허용 대상 검증, resolver 배치 호출, 깨진 카드 렌더링만 제공하고 참조 행은 배치 모듈이 소유한다. 콘텐츠는 `sr_content_link_refs`로 커뮤니티 게시글 참조를 저장하고, 커뮤니티는 `sr_community_link_refs`로 콘텐츠 참조를 저장한다. 공개 출력은 대상 resolver가 반환한 제목, 요약, URL로 카드 HTML을 만들며 삭제/비공개/권한 제한/모듈 비활성/커머스 resolver 부재 상태는 자동 삭제하지 않고 깨진 링크 카드로 표시한다. 저장 시 hidden 참조 목록이나 CKEditor widget HTML은 신뢰하지 않고 최종 본문에서 토큰을 재추출한다.

커뮤니티 게시판은 `sr_community_categories`와 `sr_community_posts.category_id`로 게시판 내부 카테고리를 선택적으로 지원한다. 카테고리 key는 게시판 안에서 유일하며 공개 URL `category` 파라미터로 사용된다. 상태는 `enabled`/`disabled`만 사용하고, 비활성 카테고리는 신규 선택과 공개 필터 노출에서 제외하되 기존 게시글 표시는 텍스트로 보존한다. 게시판 설정 `category_required`가 켜진 경우 공개 작성/수정 POST에서 서버가 카테고리 선택을 강제한다.

콘텐츠와 커뮤니티 시리즈는 각 모듈이 소유한다. 콘텐츠는 `sr_content_series`와 `sr_content_series_items`, 커뮤니티는 `sr_community_series`와 `sr_community_series_items`를 사용한다. 시리즈 상태는 운영 대기/노출/숨김/보관/삭제를 구분하되 공개 렌더링은 `active` 상태와 `active` 항목만 사용한다. 공개 범위는 `public`, `member`, `private`이며, 커뮤니티 private 시리즈는 소유자만 볼 수 있고 콘텐츠 private 시리즈는 공개 출력에서 제외한다. 콘텐츠 시리즈는 `/admin/content/series`에서 만들고 콘텐츠 편집 화면에서 회차로 연결한다. 콘텐츠 그룹은 목록 페이지, 초기화면 후보, 새 콘텐츠 기본값, 그룹/전체 복사 범위를 위한 운영 묶음이고, 콘텐츠 시리즈는 회차 표시와 이전/다음 이동을 위한 읽기 흐름이다. 한 콘텐츠는 콘텐츠 그룹에 속하면서 동시에 하나의 시리즈 회차로 연결될 수 있으며 두 정렬 기준은 서로 영향을 주지 않는다. 커뮤니티 시리즈는 회원이 `/community/series`에서 만들거나 글 작성/수정 중 새로 만들 수 있고, 관리자는 `/admin/community/series`에서 상태와 공개 범위를 조정한다. 공개 글/콘텐츠는 본문 다음에 시리즈 내비게이션을 렌더링하고, 이후 기존 출력 슬롯과 댓글/액션 흐름이 이어진다. 커뮤니티 시리즈 스크랩은 `sr_community_series_scraps`에 게시글 스크랩과 분리해 저장하며, `/community/scraps`에서 게시글 스크랩과 별도 섹션으로 표시한다. hidden, archived, deleted 상태가 되거나 게시판 읽기 권한이 사라진 시리즈는 목록에서 열람 불가 항목으로 남기되 해제할 수 있다.

회원 콘텐츠 소유권은 해시가 아니라 내부 계정 ID로 저장한다. 커뮤니티 게시글/댓글 작성자와 커뮤니티 시리즈 소유자는 편집, 삭제, private 시리즈 열람 같은 권한 판정에 직접 쓰이므로 탈퇴 cleanup에서 null로 바꾸지 않고 회원 계정 행 자체를 익명화한다. 콘텐츠/커뮤니티 시리즈의 `created_by`, `updated_by`, `moderated_by`, 시리즈 항목 `created_by`처럼 nullable 운영 메타데이터는 privacy cleanup에서 null 처리한다.

콘텐츠 예약 발행은 `status = scheduled`와 미래 `published_at`으로 표현한다. 공개/관리자 조회 시점에 예약 시각이 지난 항목은 `published`로 전환되고 `content.scheduled_published` 감사 로그를 남긴다. 예약 설정과 예약 해제는 관리자 저장 시 각각 `content.scheduled`, `content.schedule_cleared` 감사 로그로 추적한다. 관리자 미리보기 권한이 있으면 `draft`와 `scheduled` 콘텐츠 공개 URL을 열람할 수 있지만, 유료 열람 차감, 다운로드, 완료 버튼 처리는 실행하지 않는다. 콘텐츠 댓글은 `sr_content_comments`가 소유하고 공개 콘텐츠 하단에서 작성/표시된다. 커뮤니티 댓글은 `@닉네임`, 콘텐츠 댓글은 `@표시명` 멘션을 해석해 알림 모듈 활성화 시 사이트 알림을 만든다. 댓글 알림 처리는 알림 모듈 비활성화 시 no-op이다. 댓글 작성 감사 로그 metadata에는 작성자 알림 생성 여부를 남기고, 댓글 작성/수정 감사 로그에는 멘션 후보 수, 실제 멘션 알림 생성 수, 멘션 대상 공개 해시 목록을 남긴다. 콘텐츠 시리즈는 유료 열람 회차가 있으면 공개 시리즈 내비게이션에 완독 예상 금액을 자산별로 표시하며, 로그인 회원에게 회원 그룹 정책 적용 후 금액이 달라지면 원가와 회원가를 함께 표시한다. 로그인 회원이 이미 접근권을 보유한 회차는 남은 금액 합계에서 제외한다.

감사 로그 조회 화면 `/admin/audit-logs`는 이벤트 유형, 대상 유형, 대상 식별값, 처리자 계정 ID, 처리자 유형, IP, 처리 결과, 날짜 범위 필터를 제공한다. metadata JSON은 민감 키와 민감 문자열을 마스킹한 뒤 상세 모달에 표시하고, 모달에는 원본 이벤트/대상 식별자, 처리자, 결과, IP, user agent를 함께 보여 운영 조사 맥락을 확인할 수 있게 한다. 별도 로그 export는 아직 제공하지 않으며, 보존/삭제는 `/admin/retention`의 감사 로그 보관일 정책을 따른다.

게시글 리액션은 마일스톤 8 기준으로 DB와 UI를 새로 추가하지 않는다. 현재 사용자 반응 표면은 커뮤니티 게시글/시리즈 스크랩과 콘텐츠 완료 버튼으로 유지하며, 새 리액션 도입은 중복 집계 정책, 개인정보 보존 기간, 신고/운영 정책이 확정될 때 별도 마일스톤에서 다룬다.

## 검증 기준

현재 정적 기준은 다음 명령으로 확인한다.

```sh
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n 1 php -l
php .tools/bin/check.php
```

HTTP와 브라우저 검증은 [스모크 테스트 기준](smoke-test.md)을 따른다. 1.0 릴리스 후보에서는 정적 점검 통과만으로 완료 판정하지 않고, 새 설치와 관리자 주요 흐름을 브라우저에서 확인한 기록을 남긴다.
