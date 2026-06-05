# 마일스톤 13 읽기 참조 계약 계획

## 목적

GitHub 마일스톤 13 `읽기 참조 계약`의 이슈 #165, #166, #167, #168, #169, #170, #204를 기준으로 계약 정합성과 계획 완성도를 점검하고, 구현 전에 닫아야 할 스펙을 정리한다.

이 계획은 현재 구현 문서가 아니라 마일스톤 13 구현 지시서다. 실제 계약 파일과 helper가 추가되는 시점에는 `docs/module-guide.md`, `docs/core-decisions.md`, `docs/admin-ui-guide.md`, `docs/security-checklist.md`, `docs/smoke-test.md`, `.tools/bin/check.php`, `core/helpers/settings.php`의 현재 기준 문서를 함께 갱신한다.

## 결론

마일스톤 13의 방향은 기존 모듈 계약 체계와 맞다. 소유 모듈이 소비 모듈의 정책 테이블을 직접 수정하지 않고, 활성 모듈의 계약 파일만 읽어 참조 현황을 표시하는 방식은 `sr_enabled_module_contract_files()`와 `contracts.provides`/`contracts.consumes` 구조 위에 자연스럽게 얹을 수 있다.

#204 사이트명 참조 계약은 #165 공통 스펙에 늦게 붙은 범위이므로, 아래 보강 기준으로 파일명, target 구조, status 표현, UI/POST 기준을 닫고 구현 완료 조건에 포함한다.

## 공통 계약 스펙

대상별 명시 계약 파일을 사용한다.

- `coupon-references.php`
- `banner-references.php`
- `popup-layer-references.php`
- `member-group-references.php`
- `site-setting-references.php`

`site-setting-references.php`는 #204 사이트명 참조 현황을 포함한다. 더 좁은 파일명인 `site-name-references.php`는 쓰지 않고, 이후 사이트 운영 설정 중 같은 패턴이 필요할 때 같은 계약 파일 안에서 `target_key`로 구분한다.

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

- `helpers`: 소비 모듈 폴더 기준 helper 파일 목록
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

사이트명 변경 화면은 `context.old_value`와 `context.new_value`를 넘길 수 있다. 소비 모듈은 저장값이 이전 사이트명을 직접 포함하는지, 새 값으로 자동 반영되는 fallback인지, 운영자 확인이 필요한지 자기 기준으로 판단한다.

## status 기준

허용 status는 다음 다섯 개로 고정한다.

- `ok`: 현재 참조가 유효하다.
- `stale`: 참조는 남아 있으나 정책 상태, key 변경, 사이트명 변경 같은 이유로 운영자 확인이 필요하다.
- `disabled_target`: 대상이 비활성, 만료, 사용 불가 상태다.
- `missing_target`: 대상을 찾을 수 없다.
- `unknown`: 소비 모듈이 health를 판단할 수 없다.

`consumer_inactive`는 쓰지 않는다. 읽기 참조 계약은 활성 모듈의 계약 파일만 읽는다. 비활성 모듈의 잔여 데이터 확인과 정리는 retention, cleanup, 운영 점검 범위로 분리한다.

회원 그룹 key 불일치나 key 변경 위험은 새 status를 늘리지 않고 `stale`에 `message`와 `metadata.reason=key_mismatch`를 붙여 표현한다.

## 공통 helper 책임

마일스톤 13 구현 시 공통 helper는 다음까지만 맡는다.

- `sr_enabled_module_contract_files()`로 활성 모듈의 대상 계약 파일을 찾는다.
- `sr_load_module_contract_file()`로 계약을 읽는다.
- `helpers` 파일 경로가 소비 모듈 폴더 안쪽인지 검증하고 include한다.
- `supports_target_types`가 있으면 `target_type`을 검증한다.
- callable 존재 여부와 호출 가능 여부를 검증한다.
- `count_function`, `rows_function`, `health_function`, `admin_url_function`을 호출한다.
- 최종 row 필수 필드, status 허용값, 내부 상대 관리자 URL을 검증한다.
- 목록, 상세, 설정 화면처럼 표시 전용 조회에서는 깨진 계약 항목 하나가 전체 관리자 화면을 500으로 죽이지 않게 해당 항목을 제외하고 오류 로그를 남긴다.
- 삭제, 비활성화, key 변경, 사이트명 변경 저장처럼 destructive/admin-sensitive POST에서는 계약 로드 실패, callable 실패, row 정규화 실패를 조용히 제외하지 않는다. 공통 helper는 정규화된 row와 함께 로드/검증 오류를 호출자에게 반환하고, 소유 action은 최신 참조 현황을 확정할 수 없는 오류가 있으면 저장을 중단한다.

공통 helper가 하지 않는 일:

- 소비 모듈 정책 테이블 update/delete
- 참조 대상 자동 치환
- 비활성 모듈 테이블 임의 스캔
- 소비 모듈 정책 의미의 최종 판단
- 정상 로드된 참조 row의 대상별 진행/차단 정책 결정. 단, destructive/admin-sensitive POST에서 계약 로드/검증 오류는 소유 action이 공통 차단 기준으로 처리해야 한다.

## 대상별 보강 기준

### 쿠폰 정의

`coupon` 모듈은 `coupon-references.php`를 `contracts.consumes`에 기록한다. 쿠폰 정의를 정책으로 저장하는 소비 모듈은 `contracts.provides`에 기록한다.

쿠폰 비활성화, 삭제, 사용 기간 변경, 사용처 변경 전에는 최신 참조 현황을 서버 POST에서 다시 조회한다. `ok` 참조가 있으면 운영자 확인 후 진행 가능 여부를 쿠폰 모듈 정책으로 정하고, `stale`은 운영자 확인 또는 차단 상태로 구분한다. `disabled_target`, `missing_target`, `unknown`은 차단 우선 상태로 처리한다. 계약 로드 실패나 health 판단 실패는 destructive/admin-sensitive POST에서 저장 중단으로 처리한다.

`coupon` 모듈은 `sr_quiz_*`, 콘텐츠, 커뮤니티, 커머스 같은 소비 모듈 정책 테이블을 직접 update/delete하거나 쿠폰 정의 ID를 자동 치환하지 않는다.

### 배너

`banner` 모듈은 `banner-references.php`를 `contracts.consumes`에 기록한다. 직접 선택 배너를 저장하는 소비 모듈은 `contracts.provides`에 기록한다.

`sr_banner_targets`는 배너 모듈 내부 target rule이므로 읽기 참조 계약 대상이 아니다. 읽기 참조 계약은 content/community/site_menu 등 외부 소비 정책이 배너 ID를 직접 저장한 경우만 대상으로 삼는다.

배너 비활성화 또는 삭제 전에는 화면 경고와 서버 POST 재검증을 모두 수행한다. 참조가 있으면 자동 대체나 소비 정책 삭제를 하지 않고 소비 모듈 관리자 URL로 이동시키는 흐름을 제공한다.

### 팝업레이어

`popup_layer` 모듈은 `popup-layer-references.php`를 `contracts.consumes`에 기록한다. 직접 선택 팝업레이어를 저장하는 소비 모듈은 `contracts.provides`에 기록한다.

`sr_popup_layer_targets`는 팝업레이어 모듈 내부 target rule이므로 읽기 참조 계약 대상이 아니다. 읽기 참조 계약은 외부 소비 정책이 팝업레이어 ID를 직접 저장한 경우만 대상으로 삼는다.

팝업레이어 비활성화 또는 삭제 전에는 화면 경고와 서버 POST 재검증을 모두 수행한다. 참조가 있으면 자동 대체나 소비 정책 삭제를 하지 않고 소비 모듈 관리자 URL로 이동시키는 흐름을 제공한다.

### 회원 그룹

`member` 모듈은 `member-group-references.php`를 `contracts.consumes`에 기록한다. 회원 그룹을 정책으로 참조하는 모듈은 `contracts.provides`에 기록한다.

target은 `target_type=member_group`, `target_id=group_id`, `target_key=group_key`를 함께 받는다. ID 기반 정책과 key 기반 정책을 구분해 반환한다. key 기반 정책에서 key 변경 위험이 있으면 `status=stale`, `metadata.reason=key_mismatch`로 표현한다.

회원 그룹 비활성화, 삭제, key 변경 전에는 화면 경고와 서버 POST 재검증을 모두 수행한다. `member` 모듈은 소비 모듈 정책을 update/delete/자동 치환하지 않고 소비 모듈 관리자 URL로 이동시키는 흐름만 제공한다.

현재 저장소에서 우선 조사할 key 기반 후보:

- `reward`의 `withdrawal_allowed_group_keys`
- `deposit`의 `refund_allowed_group_keys`
- `content`와 `community`의 자산 정책 `group_key`
- `community` 게시판/게시판 그룹의 `read_group_keys`, `write_group_keys`, `comment_group_keys`
- `community` 쪽지 작성 정책의 `message_write_group_keys`
- `member` 내부 회원 그룹 규칙의 대상 그룹과 제외 그룹

### 사이트 설정

`admin` 모듈의 사이트 설정 화면이 `site-setting-references.php`를 `contracts.consumes`에 기록한다. 사이트 설정 값을 복사 저장하거나 표시 문구에 포함할 수 있는 소비 모듈은 `contracts.provides`에 기록한다.

사이트명 변경 대상은 다음 target을 사용한다.

- `owner_module_key=admin`
- `target_type=site_setting`
- `target_id=0`
- `target_key=site.name`

우선 소비 후보:

- `seo`: `title_suffix`, `default_description`처럼 사이트명을 직접 포함할 수 있는 설정
- `logo_manager`: 명시 저장된 `alt_text`가 사이트명을 포함할 수 있는 경우
- 관리자/공개 layout: 사이트명 fallback은 자동 수정 대상이 아니라 낮은 위험 설명 또는 참조 안내 대상으로만 분류

사이트명 변경 저장 액션은 소비 모듈 설정값을 자동 update/delete/치환하지 않는다. POST에서는 최신 참조 현황을 다시 조회하고, 운영자가 확인한 상태와 현재 상태가 달라졌으면 다시 확인하게 한다.

## 관리자 UI 기준

참조 경고는 대상 상태 변경, 삭제, key 변경, 사이트명 변경처럼 운영자가 영향을 놓치기 쉬운 action 근처에 배치한다. 목록의 상태 배지나 행 액션만으로 대체하지 않는다.

참조 row는 소비 모듈, 참조 제목, 정책 상태, health status, 마지막 수정일, 관리자 확인 URL을 표시한다. 관리자 URL은 내부 상대 URL만 링크로 출력하고, 외부 URL, 프로토콜 포함 URL, `..` 경로, 빈 문자열은 링크로 만들지 않는다.

확인 모달은 다음을 구분한다.

- 단순 안내: fallback처럼 변경 즉시 자연 반영되는 참조
- 확인 필요: `ok` 또는 `stale` 참조가 있어 운영자가 연결 정책을 확인해야 하는 상태
- 차단 우선: `disabled_target`, `missing_target`, `unknown` 참조가 있는 상태. 이 분류는 정상 로드된 row에만 적용하며, 계약 로드/검증 오류는 운영자 확인으로 우회하지 않고 저장을 중단한다.

프론트엔드 확인 플래그는 편의 기능일 뿐이다. 서버 POST는 target과 최신 참조 현황을 다시 조회하고, 필요한 확인 문구나 확인 플래그를 다시 검증한다.

공통 최소 기준은 다음과 같다.

- `ok`: 대상별 정책에 따라 확인 후 진행할 수 있다.
- `stale`: 운영자 확인 또는 대상별 차단 정책이 필요하다.
- `disabled_target`, `missing_target`, `unknown`: 화면 안내만으로 조용히 진행하지 않는다. 대상별 정책이 명시적으로 허용하지 않으면 차단한다.
- 계약 로드 실패, callable 실패, helper include 실패, row 정규화 실패: destructive/admin-sensitive POST에서는 운영자 확인으로 우회하지 않고 저장을 중단한다.

## 자동 검사 기준

마일스톤 13 구현 시 `.tools/bin/check-read-reference-contracts.php`를 추가하고 `.tools/bin/check.php`에서 실행한다.

검사 항목:

- `core/helpers/settings.php`의 `sr_module_known_contract_files()`에 새 계약 파일이 반영됐는지 확인
- `.tools/bin/check.php`의 known contract allowlist에 새 계약 파일이 반영됐는지 확인
- 실제 계약 파일을 제공하는 모듈이 `contracts.provides`에 선언했는지 확인
- 계약을 읽는 소유 모듈이 `contracts.consumes`에 선언했는지 확인
- 계약 반환값이 배열인지 확인
- `count_function`, `rows_function` callable 존재 확인
- `health_function`, `admin_url_function` callable 확인
- `supports_target_types`가 배열이며 허용 target과 충돌하지 않는지 확인
- 최종 row 필수 필드 확인
- status가 `ok`, `stale`, `disabled_target`, `missing_target`, `unknown` 중 하나인지 확인
- `admin_url`이 내부 상대 URL인지 확인
- 계약 로딩은 활성 모듈만 대상으로 한다는 기준 확인

정적 검사 후보:

- 소유 모듈 action/helper에서 소비 모듈 정책 테이블을 직접 update/delete하는 패턴
- 사이트명 변경 action에서 소비 모듈 설정값을 자동 치환하는 패턴
- 비활성 모듈 테이블을 읽기 참조 계약으로 임의 스캔하는 패턴

정적 검사 후보는 SQL 문자열 오탐 가능성이 있으므로 처음에는 경고성 검사로 시작한다. 검사 결과를 차단으로 승격할 때는 허용 예외와 실제 오탐 사례를 먼저 정리한다.

## 스모크 기준

구현 후 수동 또는 HTTP 스모크에 다음 항목을 포함한다.

- 쿠폰 정의를 비활성화/삭제/기간 변경할 때 참조 경고가 표시되고 POST에서 최신 참조가 재검증되는지 확인
- content/community가 직접 선택한 배너를 배너 관리자 화면에서 참조 현황으로 볼 수 있는지 확인
- content/community가 직접 선택한 팝업레이어를 팝업레이어 관리자 화면에서 참조 현황으로 볼 수 있는지 확인
- 회원 그룹을 게시판 권한, 쪽지 권한, 자산 정책, 출금/환불 신청 대상에 사용한 뒤 그룹 비활성화/삭제/key 변경 전 경고가 표시되는지 확인
- 사이트명을 SEO title suffix 또는 로고 alt text에 직접 포함한 뒤 사이트명 변경 화면에서 참조 현황과 관리자 이동 링크가 표시되는지 확인
- 소비 모듈을 비활성화하면 새 읽기 참조 계약 row로 표시하지 않고, 잔여 데이터 정리는 별도 운영 점검 범위로 남는지 확인
- 잘못된 `admin_url`을 반환하는 계약 항목은 링크를 출력하지 않고 전체 화면을 500으로 만들지 않는지 확인
- malformed 계약 파일, 누락 callable, 잘못된 row를 가진 활성 소비 모듈이 있을 때 삭제/비활성화/key 변경/사이트명 변경 POST가 진행되지 않고 계약 오류로 중단되는지 확인

## 구현 후 문서 반영 위치

구현 완료 시 다음 문서를 현재 구현 기준으로 갱신한다.

- `docs/module-guide.md`: 대표 계약 파일, 반환 구조, 계약 파일별 소비 주체, 번들 모듈 제공/소비 지도
- `docs/core-decisions.md`: 역방향 읽기 참조 계약 원칙과 소비 모듈 직접 수정 금지
- `docs/admin-ui-guide.md`: 참조 경고 위치, destructive action 확인 UI, 관리자 이동 링크 기준
- `docs/security-checklist.md`: 역방향 update/delete/자동 치환 금지, POST 재검증
- `docs/smoke-test.md`: 쿠폰/배너/팝업레이어/회원 그룹/사이트명 참조 경고 스모크
- `docs/implementation-snapshot.md`: 구현된 계약 파일과 helper 요약

GitHub 이슈 기준도 함께 맞춘다.

- #170의 문서/자동 검사 범위에 `site-setting-references.php`를 추가한다.
- #204는 `site-setting-references.php`, `target_type=site_setting`, `target_key=site.name`, `owner_module_key=admin` 기준으로 닫는다.
- #165 공통 스펙에서 `health_function`과 `admin_url_function`을 필수 callable로 유지한다.

## 완료 판정

마일스톤 13은 다음 조건을 모두 만족해야 완료로 본다.

- 공통 helper와 대상별 계약 파일이 구현되어 있다.
- 제공 모듈과 소비 모듈의 `module.php` 계약 선언이 맞다.
- `core/helpers/settings.php`와 `.tools/bin/check.php`의 계약 allowlist가 맞다.
- `.tools/bin/check-read-reference-contracts.php`가 통합 점검에 포함되어 통과한다.
- 관련 관리자 화면은 참조 현황과 내부 관리자 이동 링크를 표시한다.
- destructive/admin-sensitive POST는 최신 참조 현황을 서버에서 다시 확인하고, 계약 로드/검증 오류가 있으면 저장을 중단한다.
- 소유 모듈이 소비 모듈 정책을 직접 update/delete/자동 치환하지 않는다.
- 관련 저장소 문서와 Wiki 반영 필요성이 함께 정리되어 있다.
- `php .tools/bin/check.php`와 가능한 HTTP 스모크 결과가 완료 보고에 포함되어 있다.
