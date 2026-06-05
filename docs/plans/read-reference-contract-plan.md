# 마일스톤 13 읽기 참조 계약 계획

## 목적

GitHub 마일스톤 13 `읽기 참조 계약`의 이슈 #165, #166, #167, #168, #169, #170, #204를 현재 구현 상태에 맞춰 다시 평가하고, 남은 구현 범위와 완료 기준을 정리한다.

평가 기준일은 2026-06-05이다.

## 현재 구현 평가

마일스톤 13의 핵심 방향은 현재 코드베이스와 여전히 맞다. 코어에는 이미 활성 모듈의 계약 파일을 찾고 읽는 `sr_enabled_module_contract_files()`, `sr_load_module_contract_file()`, `contracts.provides`/`contracts.consumes`, `requires.contracts` 검증 흐름이 있다. 따라서 읽기 참조 계약은 새 라우팅 체계나 중앙 도메인 테이블을 만들지 않고, 기존 모듈 계약 체계에 추가하는 것이 맞다.

마일스톤 13 전용 계약 파일과 공통 helper는 2026-06-05 구현 기준으로 저장소에 반영되어 있다.

- `core/helpers/read-references.php`가 활성 모듈의 읽기 참조 계약을 로드하고 row를 정규화한다.
- `coupon-references.php`, `banner-references.php`, `popup-layer-references.php`, `member-group-references.php`, `site-setting-references.php` 계약 파일이 번들 모듈에 추가되어 있다.
- `core/helpers/settings.php`의 `sr_module_known_contract_files()`와 `.tools/bin/check.php`의 계약 allowlist에 새 5종 계약이 포함되어 있다.
- `.tools/bin/check-read-reference-contracts.php`가 추가되어 `.tools/bin/check.php`에서 실행된다.
- 관련 모듈 `module.php`에 읽기 참조 계약 `provides`/`consumes` 선언이 반영되어 있다.

이미 구현된 주변 계약과 기능은 다음처럼 계획에 반영해야 한다.

- `coupon-targets.php`는 쿠폰 정의가 적용될 도메인 대상을 검색하거나 접근 회수를 위임하는 기존 소비 계약이다. 마일스톤 13에서 `coupon` 모듈은 자체 `coupon-references.php`로 발급/사용 이력을 표준 row로 제공하고, 콘텐츠/커뮤니티는 `coupon-targets.php` 선택 확장으로 도메인 target 상태와 관리자 URL만 제공한다.
- 콘텐츠는 `sr_content_items.banner_before_content_id`, `banner_after_content_id`, `popup_layer_id`에 공용 배너/팝업레이어 ID를 직접 저장한다.
- 커뮤니티는 게시판/게시판 그룹 설정에 `banner_before_list_id`, `banner_after_list_id`, `banner_before_view_id`, `banner_after_view_id`, `banner_before_form_id`, `banner_after_form_id`, `popup_layer_list_id`, `popup_layer_view_id`, `popup_layer_form_id` 값을 저장한다.
- 커뮤니티 보드 삭제 안전장치에는 `sr_banner_targets`, `sr_popup_layer_targets`, `sr_coupon_definitions`, `sr_site_menu_items` 직접 카운트가 일부 존재한다. 이 코드는 마일스톤 13 구현 후 읽기 참조 계약 기반 조회로 옮기거나, 보드 삭제 전용 직접 참조 검사로 남길지 명시해야 한다.
- 회원 그룹 참조는 대부분 ID가 아니라 `group_key` 배열로 저장된다. 대표 후보는 reward `withdrawal_allowed_group_keys_json`, deposit `refund_allowed_group_keys_json`, community `message_write_group_keys`, 게시판/게시판 그룹의 `read_group_keys`, `write_group_keys`, `comment_group_keys`, content/community 자산 정책 JSON의 `group_key`이다.
- `member` 내부 회원 그룹 자동 규칙은 ID 기반 대상/제외 그룹을 갖는다. 소유 모듈이 member 자신이더라도 그룹 삭제/key 변경 전에 같은 읽기 참조 계약으로 보여주는 것이 운영 흐름상 자연스럽다.
- 사이트명은 코어 사이트 설정 `site.name`이고, 관리자 설정 화면은 `admin` 모듈에 있다. SEO `title_suffix`, `default_description`, logo_manager `alt_text`가 우선 소비 후보이다.

## 결정

마일스톤 13의 핵심 구현은 저장소에 반영되어 있다. 이 문서는 구현된 계약과 남은 운영 확인 범위를 함께 기록한다.

읽기 참조 계약은 역방향 조회 계약이다. 공유 대상을 소유한 모듈은 소비 모듈의 정책 테이블을 직접 update/delete/자동 치환하지 않는다. 소유 모듈은 활성 소비 모듈의 계약 파일을 읽어 참조 현황과 관리자 이동 링크를 보여주고, destructive/admin-sensitive POST에서 최신 참조 현황과 계약 오류를 다시 확인한다.

비활성 모듈의 잔여 데이터 정리는 이 계약의 책임이 아니다. retention, cleanup, 운영 점검 범위로 분리한다.

## 공통 계약 스펙

대상별 명시 계약 파일을 사용한다.

- `coupon-references.php`
- `banner-references.php`
- `popup-layer-references.php`
- `member-group-references.php`
- `site-setting-references.php`

`site-setting-references.php`는 #204 사이트명 참조 현황을 포함한다. `site-name-references.php`는 만들지 않는다. 이후 사이트 운영 설정 중 같은 패턴이 필요하면 같은 계약 파일 안에서 `target_key`로 구분한다.

계약 파일은 배열을 반환한다. 각 항목은 소비 모듈이 자기 정책 테이블에서 특정 공유 대상을 어떻게 참조하는지 설명한다.

필수 항목:

- `consumer_module_key`
- `label`
- `reference_type`
- `count_function`: `function (PDO $pdo, array $target, array $context): int`
- `rows_function`: `function (PDO $pdo, array $target, array $context): array`
- `health_function`: `function (PDO $pdo, array $target, array $row, array $context): array`
- `admin_url_function`: `function (array $row, array $context): string`

선택 항목:

- `helpers`: 소비 모듈 폴더 기준 helper 파일 목록 또는 단일 문자열
- `supports_target_types`
- `sort_order`

`rows_function`은 소비 모듈의 원자료를 반환한다. 공통 helper는 `health_function`과 `admin_url_function`을 적용해 최종 row를 정규화한다. `rows_function`이 이미 `status`나 `admin_url`을 반환해도 공통 helper 검증을 다시 통과해야 한다.

최종 row 필수 항목:

- `consumer_module_key`
- `reference_type`
- `reference_id`
- `title`
- `target_type`
- `target_id`
- `status`
- `admin_url`

최종 row 선택 항목:

- `target_key`
- `policy_status`
- `updated_at`
- `message`
- `metadata`

## target 구조

모든 읽기 참조 조회는 다음 target 구조를 사용한다.

- `owner_module_key`: 공유 대상을 소유한 모듈 key
- `target_type`: `coupon_definition`, `banner`, `popup_layer`, `member_group`, `site_setting`
- `target_id`: 숫자 ID가 있는 대상의 ID. 사이트 설정처럼 숫자 ID가 없으면 `0`
- `target_key`: key 기반 참조가 필요한 대상의 안정 key

대상별 필수값:

| 계약 파일 | `target_type` | `target_id` | `target_key` |
| --- | --- | --- | --- |
| `coupon-references.php` | `coupon_definition` | 쿠폰 정의 ID 필수 | 선택 |
| `banner-references.php` | `banner` | 배너 ID 필수 | 선택 |
| `popup-layer-references.php` | `popup_layer` | 팝업레이어 ID 필수 | 선택 |
| `member-group-references.php` | `member_group` | 회원 그룹 ID 필수 | 회원 그룹 key 필수 |
| `site-setting-references.php` | `site_setting` | `0` | 예: `site.name` |

`coupon-references.php`의 primary target은 항상 `coupon_definition`이다. 쿠폰 정의가 가리키는 `content`, `community_board`, `community_post` 같은 도메인 target은 primary target으로 바꾸지 않고 `context.definition.target_type`, `context.definition.target_id` 또는 최종 row의 `metadata.domain_target`에 담는다.

사이트명 변경 화면은 `context.old_value`와 `context.new_value`를 넘길 수 있다. 소비 모듈은 저장값이 이전 사이트명을 직접 포함하는지, 새 값으로 자동 반영되는 fallback인지, 운영자 확인이 필요한지 자기 기준으로 판단한다.

## status 기준

허용 status는 다음 다섯 개로 고정한다.

- `ok`: 현재 참조가 유효하다.
- `stale`: 참조는 남아 있으나 정책 상태, key 변경, 사이트명 변경 같은 이유로 운영자 확인이 필요하다.
- `disabled_target`: 대상이 비활성, 만료, 사용 불가 상태다.
- `missing_target`: 대상을 찾을 수 없다.
- `unknown`: 소비 모듈이 health를 판단할 수 없다.

`consumer_inactive`는 쓰지 않는다. 읽기 참조 계약은 활성 모듈의 계약 파일만 읽는다.

회원 그룹 key 불일치나 key 변경 위험은 새 status를 늘리지 않고 `stale`에 `message`와 `metadata.reason=key_mismatch`를 붙여 표현한다.

## 공통 helper 책임

공통 helper는 `core/helpers/read-references.php`에 둔다. `core/helpers/settings.php`에는 known contract allowlist와 기존 계약 로딩 primitive만 유지한다.

공통 helper가 맡는 일:

- 활성 모듈의 대상 계약 파일 탐색
- 계약 파일 로드
- `helpers` 파일 경로가 소비 모듈 폴더 안쪽인지 검증하고 include
- `supports_target_types`가 있으면 `target_type` 검증
- callable 존재 여부와 호출 가능 여부 검증
- `count_function`, `rows_function`, `health_function`, `admin_url_function` 호출
- 최종 row 필수 필드, status 허용값, 내부 상대 관리자 URL 검증
- 표시 전용 조회에서는 깨진 계약 항목 하나가 전체 관리자 화면을 500으로 죽이지 않게 해당 항목을 제외하고 오류 로그 기록
- destructive/admin-sensitive POST에서는 계약 로드 실패, helper include 실패, callable 실패, row 정규화 실패를 호출자가 차단 사유로 처리할 수 있게 오류 목록 반환

공통 helper가 하지 않는 일:

- 소비 모듈 정책 테이블 update/delete
- 참조 대상 자동 치환
- 비활성 모듈 테이블 임의 스캔
- 소비 모듈 정책 의미의 최종 판단
- 정상 로드된 참조 row의 대상별 진행/차단 정책 결정

## 대상별 구현 범위

### 쿠폰 정의

`coupon` 모듈은 `coupon-references.php`를 읽는 소유 모듈이다. 현재 번들 모듈에서 쿠폰 정의 ID는 쿠폰 모듈 내부의 발급/사용 이력에 저장되고, 콘텐츠/커뮤니티는 쿠폰 정의 ID를 직접 정책으로 저장하지 않는다. 따라서 마일스톤 13의 쿠폰 정의 경고는 외부 모듈이 저장한 쿠폰 정의 ID보다, 쿠폰 정의가 가리키는 도메인 target과 이미 발급/사용된 쿠폰 이력의 영향을 우선 확인한다.

현재 우선 후보:

- `coupon`: `sr_coupon_issues`, `sr_coupon_redemptions`의 발급/사용 이력
- `content`: 쿠폰 정의 `target_type=content` 대상의 존재와 관리자 이동 URL
- `community`: 쿠폰 정의 `target_type=community_board`/`community_post` 대상의 존재와 관리자 이동 URL

쿠폰 내부 발급/사용 이력 row는 `coupon` 모듈의 helper가 만든다. `content`와 `community`는 쿠폰 정의 ID를 역조회하지 않는다. 도메인 target 상태 확인이 필요하면 쿠폰 모듈이 현재 정의의 `target_type`/`target_id`를 `context.definition`에 담아 넘기고, 해당 target type을 제공하는 활성 모듈의 읽기 전용 callable이 target 존재 여부, 상태, 관리자 URL만 판단한다. `target_type=all`처럼 특정 도메인 target이 없는 정의는 도메인 target row를 만들지 않고 발급/사용 이력만 표시한다.

현재 마일스톤 13에서도 `coupon` 모듈은 자체 `coupon-references.php`를 제공한다. 이 파일은 `sr_coupon_issues`, `sr_coupon_redemptions`를 표준 참조 row로 만들고, 필요하면 현재 쿠폰 정의의 도메인 target 정보를 row `metadata.domain_target`에 붙인다.

`coupon-targets.php`는 그대로 유지한다. 가능하면 기존 `coupon-targets.php`에 `health_function`과 `admin_url_function` 같은 읽기 전용 항목을 선택 확장해 target 상태 판정에 재사용한다. `content`와 `community`는 현재 구현 기준으로 `coupon-references.php`를 제공하지 않고, `coupon-targets.php` 확장을 통해 자기 도메인 target 존재 여부, 상태, 관리자 URL만 알려준다. 향후 쿠폰 정의 ID를 실제로 자기 정책에 저장하는 모듈이 생기면 그 모듈은 별도로 `coupon-references.php`를 제공한다. 새 조회 흐름은 쿠폰 정의 비활성화, 삭제, 사용 기간 변경, 사용처 변경 전 발급/사용 이력과 도메인 target 영향 조회, POST 재검증을 맡는다.

쿠폰 모듈은 콘텐츠, 커뮤니티, 이후 커머스 같은 도메인 target을 직접 update/delete하거나 쿠폰 정의 target을 자동 치환하지 않는다.

### 배너

`banner` 모듈은 `banner-references.php`를 읽는 소유 모듈이다. 배너 ID를 직접 저장하는 모듈은 `banner-references.php`를 제공한다.

현재 우선 후보:

- `content`: `sr_content_items.banner_before_content_id`, `banner_after_content_id`
- `content`: `sr_content_group_settings`의 같은 표시 설정 기본값
- `community`: 게시판/게시판 그룹 설정의 `banner_before_list_id`, `banner_after_list_id`, `banner_before_view_id`, `banner_after_view_id`, `banner_before_form_id`, `banner_after_form_id`

`sr_banner_targets`는 배너 모듈 내부 target rule이다. 마일스톤 13의 배너 읽기 참조 계약 대상은 외부 소비 정책이 배너 ID를 직접 저장한 경우다. 단, 커뮤니티 보드 삭제 안전장치처럼 보드가 `sr_banner_targets`에서 참조되는지 확인하는 기존 직접 검사는 보드 삭제 도메인 안전장치로 남길 수 있다. 이 경우 읽기 참조 계약과 별개라고 문서화한다.

배너 비활성화 또는 삭제 전에는 화면 경고와 서버 POST 재검증을 모두 수행한다. 참조가 있으면 자동 대체나 소비 정책 삭제를 하지 않고 소비 모듈 관리자 URL로 이동시키는 흐름을 제공한다.

### 팝업레이어

`popup_layer` 모듈은 `popup-layer-references.php`를 읽는 소유 모듈이다. 팝업레이어 ID를 직접 저장하는 모듈은 `popup-layer-references.php`를 제공한다.

현재 우선 후보:

- `content`: `sr_content_items.popup_layer_id`
- `content`: `sr_content_group_settings.popup_layer_id`
- `community`: 게시판/게시판 그룹 설정의 `popup_layer_list_id`, `popup_layer_view_id`, `popup_layer_form_id`

`sr_popup_layer_targets`는 팝업레이어 모듈 내부 target rule이다. 마일스톤 13의 팝업레이어 읽기 참조 계약 대상은 외부 소비 정책이 팝업레이어 ID를 직접 저장한 경우다. 보드 삭제 안전장치의 `sr_popup_layer_targets` 직접 검사는 별도 도메인 안전장치로 남길 수 있다.

팝업레이어 비활성화 또는 삭제 전에는 화면 경고와 서버 POST 재검증을 모두 수행한다. 참조가 있으면 자동 대체나 소비 정책 삭제를 하지 않고 소비 모듈 관리자 URL로 이동시키는 흐름을 제공한다.

### 회원 그룹

`member` 모듈은 `member-group-references.php`를 읽는 소유 모듈이다. 회원 그룹 ID나 key를 정책으로 저장하는 모듈은 `member-group-references.php`를 제공한다.

target은 `target_type=member_group`, `target_id=group_id`, `target_key=group_key`를 함께 받는다. ID 기반 정책과 key 기반 정책을 구분해 반환한다. key 기반 정책에서 key 변경 위험이 있으면 `status=stale`, `metadata.reason=key_mismatch`로 표현한다.

현재 우선 후보:

- `reward`: `withdrawal_allowed_group_keys_json`
- `deposit`: `refund_allowed_group_keys_json`
- `content`: 자산 정책 JSON의 `group_key`
- `community`: 자산 정책 JSON의 `group_key`
- `community`: 게시판/게시판 그룹 `read_group_keys`, `write_group_keys`, `comment_group_keys`
- `community`: 모듈 설정 `message_write_group_keys`
- `member`: 회원 그룹 자동 규칙의 대상 그룹과 제외 그룹

회원 그룹 비활성화, 삭제, key 변경 전에는 화면 경고와 서버 POST 재검증을 모두 수행한다. `member` 모듈은 소비 모듈 정책을 update/delete/자동 치환하지 않고 소비 모듈 관리자 URL로 이동시키는 흐름만 제공한다.

### 사이트 설정

`admin` 모듈의 사이트 설정 화면은 `site-setting-references.php`를 읽는 소유 화면이다. 사이트 설정 값을 복사 저장하거나 표시 문구에 포함할 수 있는 소비 모듈은 `site-setting-references.php`를 제공한다.

사이트명 변경 대상은 다음 target을 사용한다.

- `owner_module_key=admin`
- `target_type=site_setting`
- `target_id=0`
- `target_key=site.name`

현재 우선 후보:

- `seo`: `title_suffix`, `default_description`
- `logo_manager`: `sr_logo_manager_logos.alt_text`

관리자/공개 layout의 사이트명 fallback은 자동으로 새 설정을 읽는 동작이므로 자동 수정 대상이 아니다. 필요하면 낮은 위험 안내 row로만 표시한다.

사이트명 변경 저장 액션은 소비 모듈 설정값을 자동 update/delete/치환하지 않는다. POST에서는 최신 참조 현황을 다시 조회하고, 운영자가 확인한 상태와 현재 상태가 달라졌으면 다시 확인하게 한다.

## 관리자 UI 기준

참조 경고는 대상 상태 변경, 삭제, key 변경, 사이트명 변경처럼 운영자가 영향을 놓치기 쉬운 action 근처에 배치한다. 목록의 상태 배지나 행 액션만으로 대체하지 않는다.

참조 row는 소비 모듈, 참조 제목, 정책 상태, health status, 마지막 수정일, 관리자 확인 URL을 표시한다. 관리자 URL은 내부 상대 URL만 링크로 출력하고, 외부 URL, 프로토콜 포함 URL, `..` 경로, 빈 문자열은 링크로 만들지 않는다.

확인 모달은 다음을 구분한다.

- 단순 안내: fallback처럼 변경 즉시 자연 반영되는 참조
- 확인 필요: `ok` 또는 `stale` 참조가 있어 운영자가 연결 정책을 확인해야 하는 상태
- 차단 우선: `disabled_target`, `missing_target`, `unknown` 참조가 있는 상태

프론트엔드 확인 플래그는 편의 기능일 뿐이다. 서버 POST는 target과 최신 참조 현황을 다시 조회하고, 필요한 확인 문구나 확인 플래그를 다시 검증한다.

공통 최소 기준:

- `ok`: 대상별 정책에 따라 확인 후 진행할 수 있다.
- `stale`: 운영자 확인 또는 대상별 차단 정책이 필요하다.
- `disabled_target`, `missing_target`, `unknown`: 화면 안내만으로 조용히 진행하지 않는다. 대상별 정책이 명시적으로 허용하지 않으면 차단한다.
- 계약 로드 실패, callable 실패, helper include 실패, row 정규화 실패: destructive/admin-sensitive POST에서는 운영자 확인으로 우회하지 않고 저장을 중단한다.

## 자동 검사 기준

`.tools/bin/check-read-reference-contracts.php`를 `.tools/bin/check.php`에서 실행한다.

검사 항목:

- `core/helpers/settings.php`의 `sr_module_known_contract_files()`에 새 계약 파일 5종이 반영됐는지 확인
- `.tools/bin/check.php`의 known contract allowlist에 새 계약 파일 5종이 반영됐는지 확인
- 실제 계약 파일을 제공하는 모듈이 `contracts.provides`에 선언했는지 확인
- 계약을 읽는 소유 모듈이 `contracts.consumes`에 선언했는지 확인
- 계약 반환값이 배열인지 확인
- `count_function`, `rows_function`, `health_function`, `admin_url_function` callable 확인
- `supports_target_types`가 배열이며 허용 target과 충돌하지 않는지 확인
- 최종 row 필수 필드 확인
- status가 `ok`, `stale`, `disabled_target`, `missing_target`, `unknown` 중 하나인지 확인
- `admin_url`이 내부 상대 URL인지 확인
- 계약 로딩은 활성 모듈만 대상으로 한다는 기준 확인
- 새 5종 읽기 참조 계약 파일 검사는 `coupon-references.php` 등 대상별 계약 파일에 적용하고, 기존 `coupon-targets.php` 선택 확장 검사는 별도 호환성 검사로 분리
- 쿠폰 target 상태 판정 callable을 `coupon-targets.php`에 선택 확장하면 해당 파일의 기존 `search_function`, `revoke_access_function` 호환성을 깨지 않는지 확인
- `coupon-targets.php`의 `health_function`, `admin_url_function`은 선택 필드로 검사하고, 존재할 때만 callable 형식과 내부 상대 관리자 URL 기준을 확인

정적 검사 후보:

- 소유 모듈 action/helper에서 소비 모듈 정책 테이블을 직접 update/delete하는 패턴
- 사이트명 변경 action에서 소비 모듈 설정값을 자동 치환하는 패턴
- 비활성 모듈 테이블을 읽기 참조 계약으로 임의 스캔하는 패턴

정적 검사 후보는 SQL 문자열 오탐 가능성이 있으므로 처음에는 경고성 검사로 시작한다. 검사 결과를 차단으로 승격할 때는 허용 예외와 실제 오탐 사례를 먼저 정리한다.

## 스모크 기준

구현 후 수동 또는 HTTP 스모크에 다음 항목을 포함한다.

- 쿠폰 정의를 비활성화/삭제/기간 변경/사용처 변경할 때 발급/사용 이력과 도메인 target 영향 경고가 표시되고 POST에서 최신 참조가 재검증되는지 확인
- 콘텐츠와 커뮤니티가 직접 선택한 배너를 배너 관리자 화면에서 참조 현황으로 볼 수 있는지 확인
- 콘텐츠와 커뮤니티가 직접 선택한 팝업레이어를 팝업레이어 관리자 화면에서 참조 현황으로 볼 수 있는지 확인
- 회원 그룹을 게시판 권한, 쪽지 권한, 자산 정책, 출금/환불 신청 대상에 사용한 뒤 그룹 비활성화/삭제/key 변경 전 경고가 표시되는지 확인
- 사이트명을 SEO title suffix, SEO 기본 설명, 로고 alt text에 직접 포함한 뒤 사이트명 변경 화면에서 참조 현황과 관리자 이동 링크가 표시되는지 확인
- 소비 모듈을 비활성화하면 새 읽기 참조 계약 row로 표시하지 않고, 잔여 데이터 정리는 별도 운영 점검 범위로 남는지 확인
- 잘못된 `admin_url`을 반환하는 계약 항목은 링크를 출력하지 않고 전체 화면을 500으로 만들지 않는지 확인
- malformed 계약 파일, 누락 callable, 잘못된 row를 가진 활성 소비 모듈이 있을 때 삭제/비활성화/key 변경/사이트명 변경 POST가 진행되지 않고 계약 오류로 중단되는지 확인

## 문서 반영 위치

구현 기준은 저장소 문서에 반영한다.

- `docs/module-guide.md`: 대표 계약 파일, 반환 구조, 계약 파일별 소비 주체, 번들 모듈 제공/소비 지도
- `docs/core-decisions.md`: 역방향 읽기 참조 계약 원칙과 소비 모듈 직접 수정 금지
- `docs/admin-ui-guide.md`: 참조 경고 위치, destructive action 확인 UI, 관리자 이동 링크 기준
- `docs/security-checklist.md`: 역방향 update/delete/자동 치환 금지, POST 재검증
- `docs/smoke-test.md`: 쿠폰/배너/팝업레이어/회원 그룹/사이트명 참조 경고 스모크
- `docs/implementation-snapshot.md`: 구현된 계약 파일과 helper 요약

GitHub 이슈 기준도 함께 맞춘다.

- #170의 문서/자동 검사 범위에 `site-setting-references.php`를 포함한다.
- #204는 `site-setting-references.php`, `target_type=site_setting`, `target_key=site.name`, `owner_module_key=admin` 기준으로 닫는다.
- #165의 새 5종 읽기 참조 계약 공통 스펙에서 `health_function`과 `admin_url_function`을 필수 callable로 유지한다.

## 완료 판정

마일스톤 13 구현은 다음 조건을 만족한다.

- 공통 helper, 새 5종 읽기 참조 계약 파일, 쿠폰 target 상태 판정을 위한 `coupon-targets.php` 선택 확장이 구현되어 있다.
- 새 5종 읽기 참조 계약의 `module.php` 제공/소비 선언과 기존 `coupon-targets.php` 제공 선언 및 선택 필드가 맞다.
- `core/helpers/settings.php`와 `.tools/bin/check.php`의 계약 allowlist가 맞다.
- `.tools/bin/check-read-reference-contracts.php`가 통합 점검에 포함되어 통과한다.
- destructive/admin-sensitive POST는 최신 참조 현황을 서버에서 다시 확인하고, 계약 로드/검증 오류가 있으면 저장을 중단한다.
- 소유 모듈이 소비 모듈 정책을 직접 update/delete/자동 치환하지 않는다.
- 관련 저장소 문서와 Wiki 반영 필요성이 함께 정리되어 있다.
- `php .tools/bin/check.php`와 가능한 HTTP 스모크 결과가 완료 보고에 포함되어 있다.

현재 제한:

- 참조 row의 관리자 화면 표시는 `sr_admin_read_reference_button_html()`과 `sr_admin_read_reference_modal_html()` 공통 헬퍼를 통해 배너, 팝업레이어, 쿠폰 정의, 회원 그룹, 사이트명 설정 화면에서 표/모달로 제공한다.
- HTTP 스모크는 로컬 `config/config.php` 권한 문제로 공통 500이 발생해 환경 이슈로 기록한다.
