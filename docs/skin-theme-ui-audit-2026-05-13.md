# 스킨/테마 사용 UI 전수조사

조사일: 2026-05-13

## 기준

- 스킨/테마 key가 코드에서 사용되면 관리자 화면에서 선택 UI로 저장할 수 있어야 한다.
- 선택지는 자동 탐색하지 않고 모듈 helper의 명시 allowlist에서 가져온다.
- 잘못된 key는 저장 단계와 렌더링 단계 모두에서 `basic`으로 fallback한다.

## 조사 결과

| 대상 | 사용 key | 렌더링 위치 | 관리자 UI | 상태 |
| --- | --- | --- | --- | --- |
| 사이트 공통 레이아웃 | `public_layout_key` | `layouts/public/basic/layout.php` | `/admin/settings` | 선택 UI 있음 |
| 관리자 스킨 | `admin_skin_key` | `modules/admin/skins/basic/layout-*.php` | `/admin/settings` | 선택 UI 있음 |
| 배너 스킨 | `banner_skin_key`, `skin_key` | `modules/banner/skins/basic/item.php` | `/admin/banners`, 배너 추가/수정 | 개별 선택 UI로 정리함 |
| 팝업레이어 스킨 | `popup_layer_skin_key`, `skin_key` | `modules/popup_layer/skins/basic/layer.php` | `/admin/popup-layers`, 팝업 추가/수정 | 개별 선택 UI로 정리함 |
| 회원 스킨 | `member_skin_key` | `modules/member/skins/basic/*.php` | `/admin/member-settings` | 선택 UI 있음 |
| 커뮤니티 테마 | `theme_key` | `modules/community/themes/basic/home.php` | `/admin/community/settings` | 선택 UI로 정리함 |
| 커뮤니티 게시판 스킨 | `skin_key` | `modules/community/skins/basic/*.php` | `/admin/community/boards`, 게시판 생성/수정 | 선택 UI로 정리함 |

## 항목별 확인 근거

관리자 스킨:

- 옵션: `toy_admin_skin_options()`
- 선택 UI: `/admin/settings`의 `admin_skin_key` select
- 저장: `toy_admin_save_skin_key()`
- 렌더링: `modules/admin/views/layout-header.php`, `layout-footer.php`가 `toy_admin_skin_view()`로 basic layout skin을 include

팝업레이어 스킨:

- 옵션: `toy_popup_layer_skin_options()`
- 기본 선택 UI: `/admin/popup-layers`의 `popup_layer_skin_key` select
- 개별 선택 UI: 팝업 추가/수정 폼의 `skin_key` select
- 저장: 기본값은 `toy_popup_layer_save_skin_key()`, 개별 팝업 값은 `toy_popup_layers.skin_key`
- 렌더링: `toy_popup_layer_render_public_layer()`, `toy_popup_layer_render()`가 각 팝업 row의 `skin_key`로 `toy_popup_layer_render_stack()` 호출

배너 스킨:

- 옵션: `toy_banner_skin_options()`
- 기본 선택 UI: `/admin/banners`의 `banner_skin_key` select
- 개별 선택 UI: 배너 추가/수정 폼의 `skin_key` select
- 저장: 기본값은 `toy_banner_save_skin_key()`, 개별 배너 값은 `toy_banners.skin_key`
- 렌더링: `toy_banner_render_public_banner()`, `toy_banner_render_slot()`이 각 배너 row의 `skin_key`로 item skin 출력
- 호환성: 출력 위치의 `placement_kind`와 스킨의 `supports`를 비교하고, 맞지 않으면 호환되는 `basic`으로 fallback한다.

회원 스킨:

- 옵션: `toy_member_skin_options()`
- 선택 UI: `/admin/member-settings`의 `member_skin_key` select
- 저장: 회원 설정 저장 흐름에서 `member_skin_key`를 `toy_module_settings`에 저장
- 렌더링: 로그인, 회원가입, 계정, 비밀번호 재설정, 개인정보 요청, 탈퇴, 이메일 인증 완료 action이 `toy_member_skin_view()` 사용

커뮤니티 테마:

- 옵션: `toy_community_theme_options()`
- 선택 UI: `/admin/community/settings`의 `theme_key` select
- 저장: 커뮤니티 설정 저장 흐름에서 `theme_key`를 `toy_module_settings`에 저장
- 렌더링: `/community` home action이 `toy_community_theme_view()` 사용

커뮤니티 게시판 스킨:

- 옵션: `toy_community_skin_options()`
- 선택 UI: `/admin/community/boards` 목록의 quick select와 생성/수정 폼의 `skin_key` select
- 저장: 목록 quick save와 게시판 생성/수정 흐름에서 `toy_community_board_settings.skin_key` 저장
- 렌더링: 목록, 글보기, 글쓰기, 글수정 action이 `toy_community_skin_view()` 사용

## 확인한 누락

- 배너와 팝업레이어는 모듈 기본 스킨 선택 UI는 있었지만, 실제 관리 대상인 개별 배너/팝업 추가/수정 화면에는 스킨 선택 UI가 없었다.
- 커뮤니티 테마는 `theme_key`를 저장하고 public home에서 사용하지만, 관리자 화면이 텍스트 입력이었다.
- 커뮤니티 게시판 스킨은 board setting의 `skin_key`를 읽는 구조가 있었지만, 게시판 생성/수정 화면에 선택 UI와 저장 경로가 없었다.

## 처리

- 커뮤니티 테마 allowlist helper를 `toy_community_theme_options()`로 분리했다.
- 배너 추가/수정 폼에 개별 스킨 선택을 추가하고 `toy_banners.skin_key`로 저장/렌더링한다.
- 팝업 추가/수정 폼에 개별 스킨 선택을 추가하고 `toy_popup_layers.skin_key`로 저장/렌더링한다.
- `/admin/community/settings`의 `theme_key` 입력을 allowlist 기반 select로 변경했다.
- `/admin/community/boards` 생성/수정 폼에 게시판 스킨 select를 추가하고, 저장/검증/audit metadata에 반영했다.
- 릴리스 검사에 커뮤니티 테마/스킨 선택 UI와 저장 경로 검사를 추가했다.
- `.tools/bin/check-skin-theme-ui.php`를 추가해 관리자 스킨, 배너 스킨, 팝업레이어 스킨, 회원 스킨, 커뮤니티 테마, 커뮤니티 게시판 스킨의 선택 UI/저장/렌더링 연결을 자동 검사한다.
