# 1.0 전 구현 스냅샷

이 문서는 GitHub Wiki 구현 명세를 1.0 배포 정리 때 맞추기 전까지 저장소 안에서 현재 구현의 핵심 표면을 빠르게 확인하기 위한 임시 스냅샷이다. 최종 DB 명세, 관리자 화면별 필드 가이드, 온보딩 문서는 Wiki를 우선한다. 기능과 특장점 요약은 [산란 특장점 소개](operator-feature-list.md)를 기준으로 하고, 이 문서는 요청 경로, DB 테이블, 모듈 표면을 대조하는 기술 보조 자료로 사용한다.

## 런타임 기준

| 항목 | 현재 값 |
| --- | --- |
| 본체 버전 | `0.2.0` |
| 모듈 계약 버전 | `2.0` |
| PHP 기준 | `8.1` 이상 |
| DB 기준 | MySQL 또는 MySQL 호환 DB, `pdo_mysql` |
| 요청 흐름 | `index.php`가 `core/request-bootstrap.php`의 요청 준비 함수를 순서대로 호출한 뒤 활성 모듈의 `paths.php`를 읽고 action 파일을 명시적으로 include |
| 기본 DB prefix | `sr_` |

## 번들 모듈

현재 저장소에는 다음 주요 모듈 또는 플러그인이 포함되어 있다.

| 분류 | key | 성격 |
| --- | --- | --- |
| 시스템 | `admin` | 관리자 대시보드, 설정, 메뉴, 모듈, 업데이트, 권한 |
| 시스템 | `asset_ledger` | 숨김 기반 잔액 처리 primitive, 자산 모듈 자동 준비 |
| 회원 | `member` | 계정, 인증, 프로필, 그룹, 탈퇴 |
| 회원 | `member_oauth` | OAuth/OIDC provider state, generic provider adapter, mock provider, 계정 연결/해제, completion 기반 |
| 회원 | `point` | 포인트 잔액과 거래 원장 |
| 회원 | `reward` | 적립금 잔액과 거래 원장 |
| 회원 | `deposit` | 예치금 잔액과 거래 원장 |
| 회원 | `asset_exchange` | 포인트/금액 항목 간 환전 정책과 실행 로그 |
| 회원 | `coupon` | 쿠폰·이용권 종류, 지급, 사용 이력 |
| 사이트 | `site_menu` | 사이트 공통 메뉴 |
| 사이트 | `logo_manager` | 용도별 로고 배치 |
| 사이트 | `banner` | 출력 슬롯용 배너 |
| 사이트 | `popup_layer` | 팝업레이어 |
| 사이트 | `seo` | robots, sitemap, SEO 보조 |
| 서비스 | `content` | 콘텐츠 작성, 공개 URL, 콘텐츠 그룹 |
| 서비스 | `community` | 게시판, 댓글, 신고, 스크랩, 쪽지 |
| 서비스 | `quiz` | 퀴즈 응시, 채점, 콘텐츠/커뮤니티 연계 보상 기반 |
| 서비스 | `survey` | 설문 작성, 응답, 통계, CSV, 보상 기반 |
| 서비스 | `embed_manager` | 본문 임베드 참조 점검과 marker/refs 동기화 |
| 서비스 | `reaction` | 콘텐츠, 커뮤니티, 퀴즈, 설문 공통 리액션 정의와 원장 |
| 운영 | `notification` | 사이트 알림, 이메일 delivery queue, 공유호스팅 runner |
| 운영 | `policy_documents` | 약관, 방침, 동의 문서와 published version 참조 계약 |
| 운영 | `privacy` | 관리자 전용 개인정보 대응 기록과 사본 제공 보조 |
| 플러그인 | `ckeditor` | textarea 에디터 강화 |

## 주요 요청 표면

요청 매핑은 각 모듈의 `paths.php`가 정본이다. 아래 표는 1.0 전 검증과 문서 탐색을 위한 요약이다.

| 영역 | 대표 경로 |
| --- | --- |
| 설치 | 미설치 상태의 모든 요청은 설치 흐름으로 진입 |
| 공개 홈 | `/`, `/ui-kit` |
| 회원 | `/login`, `/register`, `/account`, `/account/withdraw`, `/password/reset`, `/email/verify`, `/logout`, `/oauth/start`, `/oauth/callback`, `/oauth/complete`, `/account/oauth/unlink` |
| 관리자 공통 | `/admin`, `/admin/settings`, `/admin/homepage`, `/admin/menu`, `/admin/modules`, `/admin/updates`, `/admin/roles`, `/admin/audit-logs`, `/admin/retention` |
| 콘텐츠 | `/content/*`, `/content/group`, `/content/download`, `/content/action`, `/content/comment`, `/admin/content`, `/admin/content/series`, `/admin/content/settings`, `/admin/content-groups` |
| 커뮤니티 | `/community`, `/community/board`, `/community/post`, `/community/write`, `/community/edit`, `/community/series`, `/community/comment`, `/community/report`, `/community/scraps`, `/community/messages` |
| 커뮤니티 관리자 | `/admin/community/settings`, `/admin/community/boards`, `/admin/community/board-copy-jobs`, `/admin/community/board-groups`, `/admin/community/series`, `/admin/community/posts`, `/admin/community/comments`, `/admin/community/reports` |
| 퀴즈 | `/quiz`, `GET/POST /quiz/*`, `/quiz/comment`, `/quiz/comment/edit`, `/quiz/comment/delete`, `GET/POST /admin/quiz`, `/admin/quiz/settings`, `/admin/quiz/manual`, `GET/POST /admin/quiz/attempts`, `/admin/quiz/comments` |
| 설문 | `/survey`, `GET/POST /survey/*`, `/survey/comment`, `/survey/comment/edit`, `/survey/comment/delete`, `/survey/ui-kit`, `/admin/surveys`, `/admin/surveys/comments`, `/admin/surveys/responses`, `/admin/surveys/statistics`, `/admin/surveys/export`, `/admin/surveys/settings`, `/admin/surveys/manual` |
| 회원 자산 | `/account/points`, `/account/rewards`, `/account/deposits`, `/account/asset-exchange`, `/account/coupons` |
| 회원 자산 관리자 | `/admin/points`, `/admin/rewards`, `/admin/rewards/settings`, `/admin/deposits`, `/admin/deposits/settings`, `/admin/asset-exchange`, `/admin/asset-exchange/settings`, `/admin/asset-exchange/logs`, `/admin/coupons`, `/admin/coupons/issues`, `/admin/coupons/redemptions` |
| 사이트 운영 | `/admin/site-menus`, `/admin/logo-manager`, `/admin/banners`, `/admin/popup-layers`, `/admin/seo`, `/robots.txt`, `/sitemap.xml` |
| 임베드 | `/admin/embed-manager` |
| 알림/개인정보 | `/account/notifications`, `/admin/admin-notifications`, `/admin/notifications`, `/admin/notification-deliveries`, `/account/privacy-requests` 안내, `/admin/privacy-requests` |
| PWA | `/manifest.webmanifest`, `/service-worker.js` |

## 주요 DB 테이블

테이블명은 기본 prefix `sr_` 기준으로 적는다. 설치 prefix가 다르면 런타임 SQL 변환 기준을 따른다.

| 영역 | 테이블 |
| --- | --- |
| 코어 | `sr_site_settings`, `sr_modules`, `sr_module_settings`, `sr_sessions`, `sr_rate_limits`, `sr_schema_versions`, `sr_audit_logs` |
| 관리자 | `sr_admin_account_permissions`, `sr_admin_account_roles`, `sr_admin_menu_overrides` |
| 회원 | `sr_member_accounts`, `sr_member_profiles`, `sr_member_nicknames`, `sr_member_sessions`, `sr_member_auth_logs`, `sr_member_email_verifications`, `sr_member_password_resets`, `sr_member_consents`, `sr_member_groups`, `sr_member_group_memberships`, `sr_member_group_membership_logs`, `sr_member_group_rules` |
| 회원 OAuth | `sr_member_oauth_accounts`, `sr_member_oauth_states` |
| 정책 문서 | `sr_policy_documents`, `sr_policy_document_versions`, `sr_policy_document_mail_jobs`, `sr_policy_document_mail_deliveries` |
| 개인정보 | `sr_privacy_requests` |
| 콘텐츠 | `sr_content_items`, `sr_content_revisions`, `sr_content_groups`, `sr_content_group_settings`, `sr_content_setting_sources`, `sr_content_series`, `sr_content_series_items`, `sr_content_comments`, `sr_content_link_refs`, `sr_content_asset_policy_sets`, `sr_content_files`, `sr_content_file_links`, `sr_content_file_download_logs`, `sr_content_asset_access_logs`, `sr_content_access_entitlements`, `sr_content_asset_action_logs`, `sr_content_author_applications`, `sr_content_author_permissions`, `sr_content_submissions`, `sr_content_author_reward_logs`, `sr_content_storage_cleanup_failures` |
| 커뮤니티 | `sr_community_boards`, `sr_community_board_groups`, `sr_community_board_settings`, `sr_community_board_group_settings`, `sr_community_board_setting_sources`, `sr_community_board_field_definitions`, `sr_community_post_field_values`, `sr_community_board_copy_jobs`, `sr_community_board_copy_job_maps`, `sr_community_board_managers`, `sr_community_asset_policy_sets`, `sr_community_categories`, `sr_community_series`, `sr_community_series_items`, `sr_community_series_scraps`, `sr_community_posts`, `sr_community_comments`, `sr_community_link_refs`, `sr_community_attachments`, `sr_community_reports`, `sr_community_submission_consents`, `sr_community_scraps`, `sr_community_messages`, `sr_community_levels`, `sr_community_account_levels`, `sr_community_level_logs`, `sr_community_asset_logs`, `sr_community_publisher_reward_logs`, `sr_community_access_entitlements`, `sr_community_storage_cleanup_failures` |
| 퀴즈 | `sr_quiz_sets`, `sr_quiz_comments`, `sr_quiz_sources`, `sr_quiz_questions`, `sr_quiz_choices`, `sr_quiz_results`, `sr_quiz_result_rules`, `sr_quiz_reward_policies`, `sr_quiz_attempts`, `sr_quiz_attempt_answers`, `sr_quiz_attempt_result_scores`, `sr_quiz_reward_grants` |
| 설문 | `sr_survey_forms`, `sr_survey_comments`, `sr_survey_questions`, `sr_survey_choices`, `sr_survey_responses`, `sr_survey_response_answers`, `sr_survey_reward_policies`, `sr_survey_reward_grants` |
| 회원 자산 | `sr_point_balances`, `sr_point_transactions`, `sr_point_expiration_consumptions`, `sr_reward_balances`, `sr_reward_transactions`, `sr_reward_withdrawal_requests`, `sr_deposit_balances`, `sr_deposit_transactions`, `sr_deposit_refund_requests`, `sr_asset_exchange_policies`, `sr_asset_exchange_logs`, `sr_coupon_definitions`, `sr_coupon_issues`, `sr_coupon_redemptions` |
| 사이트 운영 | `sr_site_menus`, `sr_site_menu_items`, `sr_logo_manager_logos`, `sr_logo_manager_icon_variants`, `sr_banners`, `sr_banner_targets`, `sr_banner_clicks`, `sr_popup_layers`, `sr_popup_layer_targets` |
| 임베드 | `sr_embed_manager_refs` |
| 알림 | `sr_notifications`, `sr_notification_reads`, `sr_notification_deliveries`, `sr_notification_event_templates`, `sr_admin_notifications`, `sr_admin_notification_reads` |
| 리액션 | `sr_reaction_definitions`, `sr_reaction_presets`, `sr_reaction_preset_items`, `sr_reaction_records` |

상세 컬럼과 인덱스는 설치 SQL과 Wiki DB 명세를 정본으로 본다. 이 문서는 1.0 전 구현 표면을 빠르게 확인하기 위한 보조 문서다. 회원가입 동의 증적은 `sr_member_consents`에 policy document key/version/title/body hash snapshot을 함께 저장한다. 정책 문서 version을 published 상태로 만들면 `sr_policy_document_mail_jobs`와 활성 회원 기준 delivery가 생성되며, 공유호스팅 친화적으로 관리자 화면에서 batch 단위로 발송한다. `member_oauth`는 state/nonce/PKCE 원문을 DB에 저장하지 않고 hash로 보관하며, callback까지 필요한 PKCE verifier는 세션 임시 저장소에서 1회만 회수한다. provider 계약의 authorization/token/userinfo endpoint와 profile claim 설정을 바탕으로 관리자 저장 client id/secret/scope override를 적용해 SDK 없는 generic OAuth2/OIDC callback을 처리한다. secret은 저장 후 화면에 원문을 표시하지 않고, mock provider callback, 기존 계정 로그인, 계정 연결/해제, OAuth 신규 가입 completion 동의 gate를 모듈 내부에서 처리한다.

현재 포인트, 적립금, 예치금은 각 모듈의 balance/transaction 테이블을 사용하고, 공통 원자적 갱신은 숨김 기반 공식 선택 모듈 `asset_ledger`의 helper로 줄인다. 새 설치에서는 `point`, `reward`, `deposit` 선택 시 설치기가 `asset_ledger`를 필요한 기반 모듈로 자동 포함하고 함께 설치됨을 안내한다. 기존 설치에서 세 모듈 중 하나를 설치 또는 활성화할 때도 `asset_ledger`는 자동 설치/활성화되며, 활성 자산 모듈이 있는 동안 비활성화할 수 없다. 포인트는 `default_expiration_days` 설정이 1 이상이면 새 `grant` 거래에 `expires_at`과 `expires_remaining`을 기록하고, 사용/차감 시 먼저 만료되는 지급분부터 소진하며 `sr_point_expiration_consumptions`에 소비 거래와 원 지급 거래의 연결을 남긴다. 포인트 환불은 환불 건마다 참조 원거래 유효기간을 우선할지, 환불 시점부터 기본 유효기간을 다시 계산할지 선택할 수 있고, 사용 거래 환불의 기본값은 소비 매핑의 원 지급 유효기간을 우선한다. 포인트/적립금/예치금 원장 환불은 참조된 차감 원거래 복원에만 사용하며, 참조 없는 환불, 양수 원거래 환불, 원거래 잔여 환불 가능액 초과 환불은 서버에서 거부한다. 기한이 지난 지급분은 다음 포인트 거래 전 또는 `.tools/bin/expire-points.php` 운영 스크립트 실행 시 `transaction_type=expire` 음수 거래로 차감된다. 관리자 수동 조정은 1,000,000 초과 시 별도 승인자 확인을 요구하고, 자산별 1회 10,000,000과 관리자별 일일 10,000,000 절대금액 상한을 서버에서 검증한다. 적립금은 운영자 직접 회수를 `transaction_type=reclaim` 음수 거래로 별도 기록하며, 회수 대상 원거래를 `reference_type=reclaim`, `reference_id=reward_transaction:{id}`로 연결하고 같은 대상의 누적 회수액이 원거래 금액을 넘지 못하게 검증한다. 회수는 거래 내역 화면의 대상 내역 기준으로만 처리하고, 회수 거래 자체는 환불 대상으로 삼지 않는다. 쿠폰·이용권은 통합 금액 원장에 합치지 않고 `sr_coupon_issues`와 `sr_coupon_redemptions`로 발급 상태와 사용 이력을 따로 기록한다. 현재 런타임 연동은 콘텐츠/커뮤니티 유료 열람에서 쿠폰을 먼저 사용하고, 사용할 쿠폰이 없으면 포인트/적립금/예치금 차감으로 내려가는 구조다. 환전 모듈은 무수수료 양방향 정책 저장 시 소액 순환 가치 증가를 차단하고, 독립 실행 환전에서 deadlock/lock wait timeout 계열 오류를 짧게 재시도하며, 완료 환전 묶음은 `/admin/asset-exchange/logs`의 관리자 정정 action이 `sr_asset_exchange_correct_completed_group()` helper를 호출해 반대 원장 거래와 감사 로그를 남긴다. 콘텐츠/커뮤니티 복합 자산 처리도 상위 작업 단위에서 retryable DB 잠금 오류를 재시도한다. 할인액, 정액 차감권, 주문 할인처럼 금액 계산이 필요한 쿠폰 유형은 쿠폰 모듈이 단독으로 먼저 일반화하지 않고, 실제 적용 도메인이 생길 때 해당 모듈과 함께 계약을 정의한다.

읽기 참조 계약은 `core/helpers/read-references.php`가 공통 로드/정규화를 맡고, 대상별 파일 `coupon-references.php`, `banner-references.php`, `popup-layer-references.php`, `member-group-references.php`, `site-setting-references.php`로 분리한다. 관리자 화면은 공통 참조 현황 버튼/모달로 쿠폰 정의, 배너, 팝업레이어, 회원 그룹, 사이트명 참조 row와 관리자 이동 URL을 표시한다. 쿠폰 정의 비활성화, 배너/팝업레이어 삭제와 enabled 상태 해제, 회원 그룹 enabled 상태 해제, 사이트명 변경처럼 운영 영향이 큰 POST는 최신 참조 row와 계약 오류를 다시 확인하고, 참조나 계약 오류가 있으면 소비 모듈 정책을 자동 수정하지 않고 저장을 중단한다.

모듈 간 본문 연결은 `embed_manager`의 제한된 marker와 `sr_embed_manager_refs`를 사용한다. 콘텐츠 관리 폼은 커뮤니티 게시글, 퀴즈, 설문을 검색하고, 커뮤니티 작성/수정 폼은 콘텐츠, 퀴즈, 설문을 검색해 CKEditor 본문에 `<span class="sr-embed-manager-marker" data-sr-embed-manager-ref="...">` 기반 marker를 포함한 허용 HTML을 삽입한다. 검색/저장 검증/상태 갱신은 활성 모듈의 `embed-manager-targets.php` 계약을 읽어 처리하며, 현재 `content/content`, `community/post`, `quiz/quiz_set`, `survey/survey_form`이 계약을 제공한다. 저장 action은 클라이언트 marker를 그대로 신뢰하지 않고 최종 정화 HTML에 남은 marker의 ref_key, target module/type/id, variant, label을 다시 검증한 뒤 콘텐츠/게시글 저장 transaction 안에서 `sr_embed_manager_refs`를 동기화한다. 공개 콘텐츠/커뮤니티 HTML 렌더링은 marker가 든 fallback blockquote를 카드/CTA로 치환하고, 퀴즈·설문 참여 URL에는 내부 상대 `return_to`를 붙인다. 퀴즈는 해당 owner가 기존 `sr_quiz_sources`에 연결되어 있을 때만 source 파라미터를 함께 전달하고, 설문은 응답 스키마를 넓히지 않고 완료 redirect에서 `return_to`를 보존한다. refs는 임베드 렌더링과 관리자 점검용 명시적 참조이며, 대상 삭제나 비활성화를 기본 차단하지 않고 `active`, `private`, `deleted`, `broken`, `removed` 상태 표시와 점검으로 처리한다. 콘텐츠 복사와 커뮤니티 게시판 복사는 marker ref_key를 새로 발급해 본문을 rewrite하고 refs도 새 소유 대상으로 복사한다. legacy `{{sr_link_card ...}}` 토큰은 신규 저장에서 계속 거부하고, 기존 `sr_content_link_refs`, `sr_community_link_refs` 원장은 저장 시 비우며 삭제 차단 판단에 쓰지 않는다. 콘텐츠 CKEditor 본문 이미지는 다운로드 파일 모델과 DB 참조 테이블 없이 콘텐츠 모듈의 로컬 저장 경로 `storage/content/body/{content_id}`가 소유하며, 저장된 HTML에서 살아남은 콘텐츠 프록시 URL만 서버가 보존 대상으로 인정한다.

커뮤니티 게시판은 `sr_community_categories`와 `sr_community_posts.category_id`로 게시판 내부 카테고리를 선택적으로 지원한다. 카테고리 key는 게시판 안에서 유일하며 공개 URL `category` 파라미터로 사용된다. 상태는 `enabled`/`disabled`만 사용하고, 비활성 카테고리는 신규 선택과 공개 필터 노출에서 제외하되 기존 게시글 표시는 텍스트로 보존한다. 게시판 설정 `category_enabled`가 꺼진 경우 공개 목록 필터와 작성/수정 카테고리 선택을 표시하지 않고 POST 값도 저장하지 않는다. `category_required`가 켜진 경우 `category_enabled`도 자동으로 켜지며 공개 작성/수정 POST에서 서버가 카테고리 선택을 강제한다. 관리자 게시글/댓글 목록의 게시글 확인 링크는 `preview=admin`으로 열려 조회수 증가와 유료 열람 처리를 건너뛴다. 게시글, 댓글, 첨부 업로드 개인정보 수집 및 이용동의는 게시판/그룹/전역 설정의 `privacy_consent_document_key`가 가리키는 policy document published version을 서버에서 다시 조회하고, `sr_community_submission_consents`에 policy document key/version/title/body hash snapshot을 저장한다.

콘텐츠와 커뮤니티 시리즈는 각 모듈이 소유한다. 콘텐츠는 `sr_content_series`와 `sr_content_series_items`, 커뮤니티는 `sr_community_series`와 `sr_community_series_items`를 사용한다. 시리즈 상태는 운영 대기/노출/숨김/보관/삭제를 구분하되 공개 렌더링은 `active` 상태와 `active` 항목만 사용한다. 공개 범위는 `public`, `member`, `private`이며, 커뮤니티 private 시리즈는 소유자만 볼 수 있고 콘텐츠 private 시리즈는 공개 출력에서 제외한다. 콘텐츠 시리즈는 `/admin/content/series`에서 만들고 콘텐츠 편집 화면에서 회차로 연결한다. 콘텐츠 그룹은 목록 페이지와 메뉴 후보를 위한 운영 묶음이고, 콘텐츠 시리즈는 회차 표시와 이전/다음 이동을 위한 읽기 흐름이다. 한 콘텐츠는 콘텐츠 그룹에 속하면서 동시에 하나의 시리즈 회차로 연결될 수 있으며 두 정렬 기준은 서로 영향을 주지 않는다. 커뮤니티 시리즈는 회원이 `/community/series`에서 만들거나 글 작성/수정 중 새로 만들 수 있고, 관리자는 `/admin/community/series`에서 상태와 공개 범위를 조정한다. 공개 글/콘텐츠는 본문 다음에 시리즈 내비게이션을 렌더링하고, 이후 기존 출력 슬롯과 댓글/액션 흐름이 이어진다. 커뮤니티 시리즈 스크랩은 `sr_community_series_scraps`에 게시글 스크랩과 분리해 저장하며, `/community/scraps`에서 게시글 스크랩과 별도 섹션으로 표시한다. hidden, archived, deleted 상태가 되거나 게시판 읽기 권한이 사라진 시리즈는 목록에서 열람 불가 항목으로 남기되 해제할 수 있다.

운영 항목 복사는 콘텐츠, 커뮤니티 게시판, 배너, 팝업레이어 모듈이 각자 소유한다. 콘텐츠와 게시판 복사는 기본적으로 시리즈 연결을 복사하지 않지만, 시리즈 복사 옵션을 선택하면 원본 시리즈에 사본 항목을 섞지 않고 새 시리즈 사본과 새 항목을 만든다. 커뮤니티 게시판 전체 복사가 동기 상한을 넘으면 `sr_community_board_copy_jobs`와 `sr_community_board_copy_job_maps`를 사용하는 `/admin/community/board-copy-jobs` 배치 화면에서 prepare, board, posts, comments, attachments, verify 단계를 버튼 반복으로 처리할 수 있다. 배치 복사 대상 게시판은 완료 후에도 `disabled`로 남는다.

회원 콘텐츠 소유권은 해시가 아니라 내부 계정 ID로 저장한다. 커뮤니티 게시글/댓글 작성자와 커뮤니티 시리즈 소유자는 편집, 삭제, private 시리즈 열람 같은 권한 판정에 직접 쓰이므로 탈퇴 cleanup에서 null로 바꾸지 않고 회원 계정 행 자체를 익명화한다. 콘텐츠/커뮤니티 시리즈의 `created_by`, `updated_by`, `moderated_by`, 시리즈 항목 `created_by`처럼 nullable 운영 메타데이터는 privacy cleanup에서 null 처리한다.

콘텐츠 예약 발행은 `status = scheduled`와 미래 `published_at`으로 표현한다. 공개/관리자 조회 시점에 예약 시각이 지난 항목은 `published`로 전환되고 `content.scheduled_published` 감사 로그를 남긴다. 예약 설정과 예약 해제는 관리자 저장 시 각각 `content.scheduled`, `content.schedule_cleared` 감사 로그로 추적한다. 관리자 미리보기 권한이 있으면 `draft`와 `scheduled` 콘텐츠 공개 URL을 열람할 수 있지만, 유료 열람 차감, 다운로드, 완료 버튼 처리는 실행하지 않는다. 관리자 목록/수정 화면의 사용자 화면 미리보기는 `preview=admin`으로 열려 공개 콘텐츠도 조회수를 올리지 않는다. 콘텐츠 댓글은 `sr_content_comments`가 소유하고 공개 콘텐츠 하단에서 작성/표시된다. 커뮤니티 게시글/댓글과 콘텐츠 댓글은 작성자 계정 ID와 작성 당시 공개 이름 snapshot을 함께 저장해 닉네임 변경 후에도 작성 당시 표시명을 보존한다. 커뮤니티/콘텐츠/퀴즈/설문 댓글 textarea는 로그인 회원에게 `/member/mention-search` 기반 멘션 후보 자동완성을 제공하며, 후보 선택 시 `@공개이름#prefix`를 본문에 삽입한다. 서버는 `@공개이름#prefix`가 현재 공개 이름과 public account hash prefix로 단일 활성 회원에 일치할 때만 확정 멘션으로 처리하고, 모호한 `@공개이름`은 알림 대상으로 확정하지 않는다. 비밀 댓글은 제3자에게 맥락을 노출하지 않도록 멘션 알림을 만들지 않는다. 댓글 알림 처리는 알림 모듈 비활성화나 템플릿 누락 시 no-op이다. 댓글 작성 감사 로그 metadata에는 작성자 알림 생성 여부를 남기고, 댓글 작성/수정 감사 로그에는 멘션 후보 수, 실제 멘션 알림 생성 수, 멘션 대상 공개 해시 목록을 남긴다. 콘텐츠 시리즈는 유료 열람 회차가 있으면 공개 시리즈 내비게이션에 완독 예상 금액을 자산별로 표시하며, 로그인 회원에게 회원 그룹 정책 적용 후 금액이 달라지면 원가와 회원가를 함께 표시한다. 로그인 회원이 이미 접근권을 보유한 회차는 남은 금액 합계에서 제외한다.

감사 로그 조회 화면 `/admin/audit-logs`는 이벤트 유형, 대상 유형, 대상 식별값, 처리자 계정 ID, 처리자 유형, IP, 처리 결과, 날짜 범위 필터를 제공한다. metadata JSON은 민감 키와 민감 문자열을 마스킹한 뒤 상세 모달에 표시하고, 모달에는 원본 이벤트/대상 식별자, 처리자, 결과, IP, user agent를 함께 보여 운영 조사 맥락을 확인할 수 있게 한다. 별도 로그 export는 아직 제공하지 않으며, 보존/삭제는 `/admin/retention`의 감사 로그 보관일 정책을 따른다.

공통 리액션은 반응/추천을 커뮤니티 전용 기능으로 두지 않고 공식 선택 모듈 `reaction`으로 구현한다. 1차 target allowlist는 `content/content`, `content/comment`, `community/post`, `community/comment`, `quiz/quiz_set`, `quiz/comment`, `survey/survey_form`, `survey/comment`이며, 대상 모듈은 `reaction-targets.php` 계약으로 단건/batch resolve, target 상태, viewer별 공개 열람 가능 여부, 반응 가능 여부, target owner/author, 알림 생성 가능 여부, 적용 preset/key를 제공한다. 1차 공개 리액션은 target/account 기준 단일 선택이며 unique 기준은 `(account_id, target_module, target_type, target_id)`이고, `reaction_key`는 같은 row의 현재 선택값으로 저장한다. 작성자 본인은 자신의 글/댓글/콘텐츠/퀴즈/설문 target에 리액션할 수 없고, legacy 자기 target row가 있으면 취소 또는 관리자 정리만 허용한다. 원장의 권위 소스는 `sr_reaction_records`이며 정의와 preset은 `sr_reaction_definitions`, `sr_reaction_presets`, `sr_reaction_preset_items`로 분리한다. 운영자는 리액션 종류를 추가, 수정, 정렬, 사용 중지할 수 있고, 아이콘은 이모지, Material icon key, 직접 업로드한 JPG/PNG/WebP 이미지 중 선택한다. 전체 definition 수는 제한하지 않되 preset별 공개 노출 key 수는 기본 6개, hard safety cap 12개로 둔다. 콘텐츠와 커뮤니티는 환경설정 preset을 기본값으로 쓰고 개별 콘텐츠/게시판 설정이 있으면 그 값을 우선한다. 퀴즈/설문은 환경설정 < 개별 설정 순으로 preset을 상속한다. 사용 중지 key는 신규 적용/변경 write를 막고, 기존 레코드는 보관/공개 숨김/관리자·통계 유지/삭제/병합 중 운영자가 선택한다. 삭제/병합은 영향 수 계산, 확인 문구, CSRF, 권한, 감사 로그를 요구하는 위험 작업이다. 익명 write는 허용하지 않고 탈퇴/익명화 cleanup에서는 계정의 reaction record를 삭제한다. target 작성자/소유자 알림은 notification 모듈이 활성화된 경우 no-op 가능 계약으로 처리하며, actor와 recipient가 같거나 target이 write 불가능/알림 제외 상태이면 알림을 만들지 않는다. 집계 캐시, 추천/랭킹/SEO 반영, 커뮤니티 시리즈 target, 보상 연결은 후속 범위다. 현재 사용자 반응 표면인 커뮤니티 게시글/시리즈 스크랩과 콘텐츠 완료 버튼은 유지하며, reaction은 이 기능들을 대신하지 않는다.

마일스톤 2 퀴즈 보상 모듈은 `quiz` 서비스 모듈로 구현했다. 현재 반영 범위는 모듈 메타데이터, `/quiz` 공개 진입점, `/quiz/{quiz_key}` 풀이/제출/자동 채점, 퀴즈 공개 레이아웃 후보와 환경설정의 레이아웃/스킨/사이트 메뉴 슬롯 선택, `/admin/quiz` CRUD와 복사 및 관리자 사용자 화면 미리보기, `/admin/quiz/settings` 환경설정, `/admin/quiz/manual` 운영자 매뉴얼, `/admin/quiz/attempts` 시도/보상 조회와 적립금 보상 회수 화면/POST, `/admin/quiz/comments` 댓글 관리, 공개 기간/회원 그룹/시도 제한 검증, 단일/복수 선택 문제, 총점/카테고리 결과 규칙, `comments_enabled` 기반 결과 화면 댓글 작성/수정/삭제와 비밀 댓글, 결과 화면 퀴즈/댓글 리액션, `content/content_item`과 `community/community_post` source 연결, 콘텐츠/커뮤니티 상세 퀴즈 CTA/모달/page fallback, `sr_quiz_*` 설치/업데이트 스키마, 개인정보 export/cleanup 계약, 자산 원장 reference 조회 callable, 쿠폰 지급 provider와 쿠폰 정의 읽기 참조 계약, 통과 보상 지급, 댓글 멘션 알림, `.tools/bin/check-quiz-consistency.php` 정합성 검사다. 계획 기준은 [퀴즈 보상 모듈 정합성 평가](plans/quiz-reward-module-plan.md)를 따른다.

퀴즈 공개 본문은 환경설정의 기본 `skin_key`와 개별 퀴즈의 선택값으로 결정한다. 개별 퀴즈의 `skin_key`가 비어 있으면 환경설정 기본값을 상속하고, 값이 있으면 정적 허용 목록으로 검증된 개별값이 우선한다. `sr_quiz_skin_options()`의 정적 허용 목록과 `home`, `view`, `result` 필수 view 목록으로 include 경로를 매핑한다. 스킨 또는 특정 view가 없으면 view 단위로 `modules/quiz/skins/basic/*.php`에 fallback하고 운영 로그에 fallback 맥락을 남긴다. 스킨 key는 `quiz-skin-*`와 `sr-quiz-skin-*` class hook도 함께 결정한다.

마일스톤 18 설문 모듈은 `survey` 서비스 모듈로 구현했다. 현재 반영 범위는 `/survey` 공개 목록, `/survey/{survey_key}` 응답 제출, 로그인/익명/동의/응답 제한, 설문 연구 메타데이터와 개인정보 안내 스냅샷, 단일/복수 선택·짧은 답변·긴 답변·숫자·별점·척도 문항, 설문 공개 레이아웃 후보와 환경설정의 레이아웃/스킨 기본값, `/admin/surveys` CRUD, `/admin/surveys/comments` 댓글 관리, `/admin/surveys/responses` 품질 관리, `/admin/surveys/statistics` 통계, `/admin/surveys/export` CSV, `/admin/surveys/settings` 기본값, `/admin/surveys/manual` 운영 매뉴얼, `/survey/ui-kit`, `comments_enabled` 기반 완료 화면 댓글 작성/수정/삭제와 비밀 댓글, 완료 화면 설문/댓글 리액션, 개인정보 export/cleanup 계약, 쿠폰 정의 읽기 참조 계약, sitemap 계약, `ledger_asset`/`coupon` 응답 보상 지급, 댓글 멘션 알림이다. 선택형 통계는 `question_key`/`choice_key`와 응답 ID 기준으로 집계해 같은 응답의 선택지를 한 번만 계산하고, 개인정보 사본은 응답 스냅샷, 답변 행, 작성 댓글을 함께 제공한다. 퀴즈와 설문 보상 grant 및 콘텐츠/커뮤니티/쿠폰 접근권 중복 방지는 `.tools/bin/check-reward-abuse-standards.php`로 통합 점검하며, 설문 전용 회귀 기준은 `.tools/bin/check-survey-consistency.php`와 `.tools/bin/check-survey-response-runtime.php`로 확인한다.

설문 공개 본문도 환경설정의 기본 `skin_key`와 개별 설문의 선택값으로 결정한다. 개별 설문의 `skin_key`가 비어 있으면 환경설정 기본값을 상속하고, 값이 있으면 정적 허용 목록으로 검증된 개별값이 우선한다. `sr_survey_skin_options()`의 정적 허용 목록과 `home`, `view`, `complete` 필수 view 목록으로 include 경로를 매핑한다. 스킨 또는 특정 view가 없으면 view 단위로 `modules/survey/skins/basic/*.php`에 fallback하고 운영 로그에 fallback 맥락을 남긴다. 설문 스킨은 `survey-skin-*` class hook을 유지하며, 완료 화면에서는 보상 지급 결과 안내 surface를 유지해야 한다.

## 검증 기준

현재 정적 기준은 다음 명령으로 확인한다.

```sh
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n 1 php -l
php .tools/bin/check.php
```

HTTP와 브라우저 검증은 [스모크 테스트 기준](smoke-test.md)을 따른다. 1.0 릴리스 후보에서는 정적 점검 통과만으로 완료 판정하지 않고, 새 설치와 관리자 주요 흐름을 브라우저에서 확인한 기록을 남긴다.
