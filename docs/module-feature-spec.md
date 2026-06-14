# 모듈별 기능정의서

이 문서는 PM 검토를 위해 `modules/*/module.php` 기준 번들 모듈의 현재 구현 기능, 화면, 버튼/명령어 동작, 데이터 변경 범위를 한곳에 정리한다. 기준일은 2026-06-14이며, 실제 확인 파일은 각 행의 `관련 파일`에 적었다.

구현 계획이나 희망 기능은 현재 동작과 섞지 않는다. 파일만으로 처리 세부가 완전히 확인되지 않은 항목은 `확인 필요`로 표시한다.

## 목차

- [공통 읽기 기준](#공통-읽기-기준)
- [admin](#admin)
- [antispam](#antispam)
- [antispam_captcha_providers](#antispam_captcha_providers)
- [asset_exchange](#asset_exchange)
- [asset_ledger](#asset_ledger)
- [banner](#banner)
- [ckeditor](#ckeditor)
- [community](#community)
- [content](#content)
- [coupon](#coupon)
- [deposit](#deposit)
- [embed_manager](#embed_manager)
- [logo_manager](#logo_manager)
- [member](#member)
- [notification](#notification)
- [point](#point)
- [popup_layer](#popup_layer)
- [privacy](#privacy)
- [quiz](#quiz)
- [reaction](#reaction)
- [reward](#reward)
- [seo](#seo)
- [site_menu](#site_menu)
- [survey](#survey)
- [PM 검토 필요 항목](#pm-검토-필요-항목)
- [관련 Wiki](#관련-wiki)

## 공통 읽기 기준

| 구분 | 내용 |
| --- | --- |
| 요청 흐름 | 각 모듈의 `paths.php`가 `GET /path`, `POST /path`를 action 파일에 연결한다. 화면 조회는 GET, 저장/상태 변경은 POST로 분리되어 있다. |
| 관리자 화면 | 관리자 action은 `member`, `admin` 의존 모듈 위에서 로그인, 권한, POST CSRF를 검증한 뒤 도메인 처리를 수행하는 구조를 따른다. |
| 출력 | view는 action이 준비한 값을 출력하고, 변수 출력은 `sr_e()` 또는 관련 escaping helper를 사용한다. |
| 데이터 | 설치 테이블은 각 모듈의 `install.sql` 기준이다. 공통 설정은 `sr_module_settings`, 사이트 설정은 `sr_site_settings`에 저장된다. |
| 감사/알림 | 중요한 관리자 저장, 상태 변경, 발송, 정리 작업은 `sr_audit_log` 또는 모듈별 로그/원장 테이블에 남기는 패턴을 사용한다. 알림 연동은 `notification-events.php`, `admin-notification-events.php` 계약이 있는 경우에 수행된다. |
| 테스트 | 코드 변경이 아닌 문서 산출물이므로 기본 검증은 문서 링크와 파일 존재 확인이다. 모듈 동작 자체는 관련 런타임 점검과 HTTP smoke로 확인한다. |

## admin

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 관리자 대시보드, 사이트 설정, 홈페이지 후보, 관리자 메뉴, 모듈 설치/활성화, 업데이트, 운영 상태, 권한, 감사 로그, 보존 정책, UI-KIT, 썸네일 캐시 관리를 제공한다. |
| 기본 동작 기전 | `paths.php`가 `/admin` 하위 요청을 action에 연결한다. `module.php`는 `member`를 요구하고 `dashboard.php`, `homepage-candidates.php`, `site-setting-references.php`, `admin-notification-events.php`를 소비한다. |
| 데이터 구조 | `sr_admin_account_roles`, `sr_admin_account_permissions`, `sr_admin_menu_overrides`를 설치한다. 모듈/사이트 설정, update 적용 이력, 감사 로그는 코어 및 공통 테이블과 함께 사용한다. |
| 관리자 화면 | `/admin`, `/admin/settings`, `/admin/homepage`, `/admin/menu`, `/admin/modules`, `/admin/updates`, `/admin/operations`, `/admin/storage-cache`, `/admin/roles`, `/admin/audit-logs`, `/admin/retention`, `/admin/ui-kit`. |
| 사용자 화면 | 공개 사용자 화면은 없다. 관리자 shell, 대시보드, 관리 도구 화면을 제공한다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 로그인, owner/role/permission 검사, POST CSRF, 업로드 zip 안전 경로 검사, owner 재인증, 민감 설정 마스킹, 감사 로그 metadata 정리를 수행한다. |
| 다른 모듈 연동 | 모듈 metadata, 계약 파일, 대시보드 섹션, homepage 후보, 사이트 설정 참조, 관리자 알림 이벤트, output helper와 연동한다. |
| 설정값 | `admin_skin_key`, `admin_color_scheme`, `list_pagination_per_page`, `admin_editor`, `icon_key_overrides`. |
| 운영 주의사항 | 모듈 파일 교체와 DB update는 별도 단계다. zip 업로드/추출은 파일 권한과 압축 크기 제한의 영향을 받는다. update 실패는 복구 marker와 적용 이력 확인이 필요하다. |
| 테스트 관점 | `php .tools/bin/check.php`, `php .tools/bin/check-admin-navigation-runtime.php`, `php .tools/bin/check-admin-action-security.php`, HTTP smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 사이트 설정 | 저장 | 설정 권한 | 사이트 이름, 상태, locale, timezone, layout 등 | 권한과 CSRF를 확인하고 허용된 설정 key만 읽는다. 값 타입과 지원 locale을 검증하고 이전 값과 비교해 변경된 항목만 저장한다. 민감값은 감사 로그 표시에서 마스킹한다. | 설정 화면으로 돌아가 완료 메시지를 표시하고 공개 layout/helper 조회에 새 설정이 반영된다. | 권한 부족, CSRF 실패, 허용되지 않은 값, locale 검증 실패 | `sr_site_settings` update/insert, 감사 로그 가능 | `modules/admin/actions/settings.php`, `modules/admin/views/settings.php` |
| 관리자 > 모듈 관리 | 설치/활성/비활성/업로드 | 모듈 관리 권한, 필요 시 owner 재확인 | module key, zip 파일, 작업 intent | 모듈 metadata와 의존성, 버전, route 충돌, 압축 안전 경로, 업로드 크기를 검증한다. 설치 시 `install.sql`과 module record를 반영하고, 활성 상태 변경 시 foundation 의존성과 요구 모듈을 확인한다. 파일 교체는 작업 디렉터리에서 검증 후 반영한다. | 목록에 상태와 version drift가 갱신되고 감사 로그가 남는다. | 의존성 누락, route 충돌, 안전하지 않은 zip entry, 다운그레이드/owner 확인 실패 | `sr_modules`, `sr_module_settings`, 모듈 파일, 감사 로그 | `modules/admin/actions/modules.php`, `modules/admin/views/modules.php` |
| 관리자 > 업데이트 | 업데이트 적용 | 업데이트 권한 | 적용 대상 SQL | pending update를 계산하고 lock을 잡은 뒤 허용 경로의 SQL을 statement 단위로 적용한다. checksum과 적용 version을 기록하고 실패 시 marker를 남긴다. | 업데이트 목록이 갱신되고 적용 이력이 남는다. | lock 획득 실패, SQL 오류, 허용되지 않은 경로, checksum 불일치 | schema version/update 이력, 대상 모듈 테이블 | `modules/admin/actions/updates.php`, `.tools/bin/check.php` |
| 관리자 > 권한 | 역할/권한 저장 | owner 또는 권한 관리 권한 | 계정, role, permission token | owner 보호 규칙을 확인하고 permission token을 path/action 단위로 정규화한다. 보기 권한이 필요한 action 조합을 검증한 뒤 계정별 역할과 권한을 동기화한다. | 권한 목록이 갱신되고 대상 관리자 접근 범위가 바뀐다. | owner 잠금 위험, 잘못된 token, 권한 부족 | `sr_admin_account_roles`, `sr_admin_account_permissions` | `modules/admin/actions/roles.php`, `modules/admin/views/roles.php` |
| 관리자 > 보존 정책 | 저장/정리 실행 | 보존 정책 권한 | 보존 일수, 정리 scope | 설정 저장은 scope별 일수 범위를 검증한다. 정리 실행은 대상 테이블 존재 여부와 cutoff를 계산하고, 미리보기/실행 범위에 따라 오래된 로그·세션·캐시·백업을 삭제한다. | 삭제 건수와 완료 메시지가 표시되고 감사 로그가 남는다. | 잘못된 일수, 알 수 없는 scope, 권한/CSRF 실패 | 설정값 update, 대상 로그/캐시 delete | `modules/admin/actions/retention.php`, `modules/admin/views/retention.php` |
| CLI/점검 | 운영 점검 | 로컬/스테이징 | 명령 실행 환경 | 체크 스크립트가 요청 계약, 관리자 보안, 모듈 상태, 개인정보/보안 문서 일관성을 정적으로 확인한다. HTTP smoke는 내장 서버를 띄워 공개/관리자 주요 경로 응답을 확인한다. | 통과/실패 결과가 터미널에 표시된다. | PHP 미설치, DB/환경 누락, smoke base URL 오류 | 기본적으로 데이터 변경 없음. smoke fixture가 있으면 로컬 데이터 생성 가능 | `.tools/bin/check.php`, `.tools/bin/smoke-http.php` |

## antispam

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 회원가입과 공개 제출 폼에 자동등록방지 challenge/provider 검증 정책을 제공한다. |
| 기본 동작 기전 | `/admin/antispam/settings`에서 설정을 저장하고, `antispam-providers.php` 계약을 소비해 외부 CAPTCHA provider를 선택한다. |
| 데이터 구조 | 별도 설치 테이블은 없고 설정은 `sr_module_settings`에 저장한다. challenge/session/runtime rate 처리는 helper와 공통 runtime 저장소를 사용한다. |
| 관리자 화면 | 자동등록방지 > 설정. |
| 사용자 화면 | 회원가입, 비회원 게시글/댓글 등 소비 모듈이 surface 설정에 따라 challenge를 표시한다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 설정 저장은 권한/CSRF를 요구한다. 공개 검증은 TTL, 최소 제출 시간, remote IP 전달 여부, provider 실패 정책을 적용한다. |
| 다른 모듈 연동 | `member`, `community` 같은 공개 입력 surface와 provider plugin 계약에 연결된다. |
| 설정값 | `enabled`, `default_mode`, `challenge_type`, `ttl_seconds`, `min_submit_seconds`, `provider_timeout_seconds`, `provider_failure_policy`, surface별 정책. |
| 운영 주의사항 | fail closed 설정은 provider 장애 시 제출을 막을 수 있다. 공유호스팅에서 외부 provider timeout을 짧게 유지해야 한다. |
| 테스트 관점 | `php .tools/bin/check-antispam-runtime.php`, 회원가입/비회원 제출 수동 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 자동등록방지 설정 | 저장 | 설정 권한 | 사용 여부, challenge/provider, TTL, surface 정책 | 권한과 CSRF를 확인하고 boolean, 초 단위 제한, provider 실패 정책, surface 모드를 allowlist로 검증한다. provider 계약이 있는 경우 선택지를 구성하고 저장값을 정규화한다. | 설정 화면으로 돌아가고 이후 대상 폼의 challenge 표시/검증 정책이 바뀐다. | 잘못된 숫자, 허용되지 않은 mode/provider, 권한 부족 | `sr_module_settings` update | `modules/antispam/actions/admin-settings.php`, `modules/antispam/helpers.php` |
| 공개 입력 폼 | challenge 검증 | surface 정책이 active | challenge token/answer, provider 응답 | 소비 모듈의 제출 action이 antispam helper를 호출한다. TTL, 최소 제출 시간, 세션/토큰, provider 응답과 remote IP 옵션을 검증하고 실패 정책에 따라 제출을 중단한다. | 원래 제출 처리가 계속된다. | 만료, 오답, 너무 빠른 제출, provider 실패 | 성공 자체는 데이터 변경 없음. 원래 제출 action이 후속 insert/update 수행 | `modules/antispam/helpers.php`, 소비 모듈 action |

## antispam_captcha_providers

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 자동등록방지 모듈에 Turnstile, hCaptcha, reCAPTCHA provider 계약을 제공하는 plugin이다. |
| 기본 동작 기전 | `module.php`가 `antispam`을 요구하고 `antispam-providers.php`를 제공한다. 자체 route와 관리자 화면은 없다. |
| 데이터 구조 | 별도 설치 테이블 없음. provider별 secret/site key는 antispam 설정 또는 provider 계약 소비 위치에서 사용한다. |
| 관리자 화면 | 자체 메뉴 없음. antispam 설정 화면의 provider 선택지로 노출된다. |
| 사용자 화면 | antispam이 provider challenge를 표시할 때 간접적으로 사용된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | secret 원문 노출 방지와 provider endpoint timeout 정책은 antispam 소비 흐름에 따른다. |
| 다른 모듈 연동 | `antispam-providers.php` 계약으로만 연동한다. |
| 설정값 | 자체 기본 설정 없음. |
| 운영 주의사항 | provider 계정 키 누락 또는 외부 통신 장애 시 antispam 실패 정책의 영향을 받는다. |
| 테스트 관점 | antispam 설정에서 provider 노출 여부와 공개 폼 검증을 확인한다. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 계약 로딩 | provider 목록 제공 | plugin 설치/활성, antispam 활성 | 없음 | 코어가 활성 모듈 계약 파일을 로드하면 provider 정의가 antispam 모듈의 선택지와 검증 callback 후보가 된다. | antispam 설정/검증 흐름에서 provider를 사용할 수 있다. | 계약 파일 누락, antispam 비활성 | 없음 | `modules/antispam_captcha_providers/module.php`, `modules/antispam_captcha_providers/antispam-providers.php` |

## asset_exchange

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 포인트, 적립금, 예치금 등 설치된 회원 자산 사이의 환전 정책과 회원 환전 실행 로그를 관리한다. |
| 기본 동작 기전 | `/account/asset-exchange`에서 회원 환전을 처리하고 `/admin/asset-exchange`에서 정책, 로그, 기본 설정을 관리한다. |
| 데이터 구조 | `sr_asset_exchange_policies`, `sr_asset_exchange_logs`. 환전 실행은 원천/대상 자산 모듈의 거래 생성 helper와 함께 원장 거래를 만든다. |
| 관리자 화면 | 환전 정책, 환전 로그, 환전 환경설정. |
| 사용자 화면 | `/account/asset-exchange`. 로그인 회원이 보유 자산을 선택해 환전을 요청한다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장은 권한/CSRF, 회원 실행은 로그인/CSRF/정책/잔액 검증을 요구한다. |
| 다른 모듈 연동 | `asset-exchange.php`, `member-assets.php`, `privacy-export.php`, 대시보드 계약과 연동한다. |
| 설정값 | 환전 기본 반올림, 수수료/최소/최대 입력 기본값 등은 환경설정 action에서 관리한다. |
| 운영 주의사항 | 반복 실행은 로그와 양쪽 원장 거래를 남긴다. 정책 비활성화는 기존 로그를 보존하고 신규 실행만 막는다. |
| 테스트 관점 | `php .tools/bin/check-asset-exchange-runtime.php`, `php .tools/bin/check-asset-exchange-logs.php`, HTTP 회원 환전 smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 환전 정책 | 저장/삭제/상태 변경 | 환전 관리 권한 | from/to asset, 비율, 수수료, 최소/최대, 상태 | 권한과 CSRF를 확인하고 자산 provider 존재, 동일 자산 금지, 숫자 범위, 반올림 정책, 상태값을 검증한다. 정책을 생성/수정/비활성 처리하고 감사 로그를 남긴다. | 정책 목록과 회원 환전 가능 조합이 갱신된다. | 잘못된 자산, 금액 범위 오류, 중복 정책, 권한 부족 | `sr_asset_exchange_policies` insert/update | `modules/asset_exchange/actions/admin-asset-exchange.php` |
| 회원 > 환전 | 환전 실행 | 로그인, 활성 정책 | 정책 ID, 환전 금액 | 로그인과 CSRF를 확인하고 정책 활성/기간/최소·최대/잔액을 검증한다. 수수료와 대상 금액을 계산하고 출금/입금 원장 거래를 생성한 뒤 실행 로그에 묶음 결과를 저장한다. | 양쪽 잔액이 갱신되고 환전 내역이 표시된다. | 잔액 부족, 정책 비활성, 계산 결과 0 이하, 원장 생성 실패 | 자산별 거래 테이블, `sr_asset_exchange_logs` insert | `modules/asset_exchange/actions/account-asset-exchange.php`, `modules/asset_exchange/helpers.php` |
| 관리자 > 환전 로그 | 상태 처리/조회 | 환전 로그 권한 | 기간, 회원, 정책, 상태 | 필터를 정규화해 로그와 연결 거래를 조회한다. POST 작업은 확인 필요: 파일 기준으로 로그 상태 변경 intent가 존재하는지 추가 검토가 필요하다. | 실행 결과와 원장 연결을 확인한다. | 잘못된 필터, 권한 부족 | 조회 중심. POST intent가 있으면 확인 필요 | `modules/asset_exchange/actions/admin-asset-exchange-logs.php` |

## asset_ledger

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 회원 자산 모듈의 잔액 갱신과 거래 기록 primitive를 제공하는 숨김 기반 모듈이다. |
| 기본 동작 기전 | `module.php`는 foundation/hidden admin metadata를 가진다. `/admin/assets/reconciliation`에서 원장 정합성을 점검한다. |
| 데이터 구조 | 자체 설치 테이블은 없다. `point`, `reward`, `deposit`의 balances/transactions 테이블을 공통 helper 방식으로 다룬다. |
| 관리자 화면 | 자산 점검 > 원장 정합성. |
| 사용자 화면 | 직접 화면 없음. 자산 모듈 화면에서 간접 사용된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 점검은 권한을 요구한다. 거래 생성 helper는 호출 모듈의 검증 이후 원장 일관성을 보장한다. |
| 다른 모듈 연동 | `point`, `reward`, `deposit`, `asset_exchange`, 콘텐츠/커뮤니티/퀴즈/설문 보상 흐름과 간접 연동한다. |
| 설정값 | 자체 설정 없음. |
| 운영 주의사항 | 숫자 직접 수정 대신 원장 거래를 쌓아 잔액을 만든다. 정합성 오류는 자산 모듈별 복구 정책이 필요하다. |
| 테스트 관점 | `php .tools/bin/check-asset-reconciliation.php`, `php .tools/bin/check-member-assets-transaction-contract.php`. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 원장 정합성 | 조회 | 자산 점검 권한 | 자산 모듈, 기간/회원 필터 | 활성 자산 모듈의 balance와 transaction 합계를 조회해 잔액 불일치, 음수, 참조 오류 후보를 계산한다. | 점검 결과와 의심 행이 표시된다. | 권한 부족, 대상 테이블 없음 | 조회만 수행 | `modules/asset_ledger/actions/admin-assets-reconciliation.php` |
| 내부 helper | 거래 생성 | 호출 모듈 검증 완료 | account_id, amount, type, reference | 호출 모듈이 이미 권한/정책을 검증한 뒤 helper를 호출한다. helper는 잠금/트랜잭션 안에서 거래를 기록하고 잔액을 증감해 원장과 잔액을 같이 유지한다. | 호출 모듈의 보상/차감/환전/환불 처리가 완료된다. | DB 오류, 잔액 부족 정책, 중복 reference | 자산별 balances/transactions insert/update | `modules/asset_ledger/helpers.php` |

## banner

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 공개 output slot에 노출할 배너와 클릭 로그를 관리한다. |
| 기본 동작 기전 | 공개 `/banner/image`, `/banner/click`은 이미지 제공과 클릭 추적을 처리하고, 관리자 `/admin/banners` 하위 action이 CRUD를 담당한다. |
| 데이터 구조 | `sr_banners`, `sr_banner_targets`, `sr_banner_clicks`. 상태, 기간, 위치, 대상 조건, 이미지 storage reference를 가진다. |
| 관리자 화면 | 배너 목록, 신규/수정, 설정, 대상 검색. |
| 사용자 화면 | output slot 렌더링으로 공개 페이지에 배너가 표시되고 클릭 시 `/banner/click`을 거친다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장은 권한/CSRF와 파일 업로드 검증을 수행한다. 공개 image/click은 안전한 storage reference와 redirect URL 검증이 필요하다. |
| 다른 모듈 연동 | `output-slots.php`, `extension-points.php`, `coupon-targets.php`, `banner-references.php` 계약을 사용한다. |
| 설정값 | `banner_skin_key`, 기본 상태, 기본 target/match/sort. |
| 운영 주의사항 | 기간/상태/대상 조건이 맞지 않으면 레코드가 있어도 공개 출력 후보에서 제외된다. 삭제는 클릭 로그 보존 정책과 함께 검토한다. |
| 테스트 관점 | 배너 CRUD 수동 확인, output slot 렌더링, 이미지/클릭 redirect smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 배너 목록 | 필터/일괄 상태 | 배너 관리 권한 | 상태, 위치, 검색어, 선택 ID, intent | 권한을 확인하고 필터를 정규화해 목록을 조회한다. POST 일괄 작업은 CSRF와 선택 ID 존재 여부, 상태 allowlist를 검증한 뒤 대상 배너 상태를 갱신한다. | 목록에 변경된 상태와 메시지가 표시된다. | 잘못된 intent/ID/상태, 권한 부족 | `sr_banners` update | `modules/banner/actions/admin-banners.php` |
| 관리자 > 배너 작성/수정 | 저장 | 배너 관리 권한 | 제목, 위치, 이미지, 링크, 기간, 대상 조건 | 권한과 CSRF를 확인하고 필수값, 기간, 링크, 상태, 정렬, 대상 module/type/id를 검증한다. 새 이미지가 있으면 MIME/크기/확장자를 확인해 storage에 저장하고, 배너와 대상 조건을 transaction으로 저장한다. | 편집/목록 화면으로 이동하고 output slot 후보가 갱신된다. | 필수값 누락, 기간 오류, 업로드 실패, target 검증 실패 | `sr_banners` insert/update, `sr_banner_targets` replace, 파일 저장 가능 | `modules/banner/actions/admin-banner-save.php`, `modules/banner/views/admin-banner-edit.php` |
| 관리자 > 배너 | 복사/삭제 | 배너 관리 권한 | banner_id | 복사는 원본 존재와 권한을 확인한 뒤 제목/상태/기간/대상 조건을 사본으로 만든다. 삭제는 참조/로그 보존 기준을 확인하고 배너와 대상 조건을 제거하거나 비활성 처리한다. | 사본이 생성되거나 목록에서 제외된다. | 원본 없음, 권한/CSRF 실패, 참조 제한 | `sr_banners`, `sr_banner_targets` insert/delete/update | `modules/banner/actions/admin-banner-copy.php`, `modules/banner/actions/admin-banner-delete.php` |
| 공개 배너 | 이미지/클릭 | 활성 배너 후보 | 배너 ID 또는 image ref, redirect target | 이미지 action은 배너 이미지 storage reference를 검증해 파일 헤더와 함께 전송한다. 클릭 action은 배너/기간/URL을 확인하고 클릭 로그를 남긴 뒤 안전한 링크로 이동한다. | 이미지가 표시되거나 링크 대상 페이지로 이동한다. | 배너 없음, 비활성, 파일 없음, 안전하지 않은 URL | `sr_banner_clicks` insert 가능 | `modules/banner/actions/image.php`, `modules/banner/actions/click.php` |

## ckeditor

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 설정된 textarea에 CKEditor 5 편집기를 붙이는 에디터 plugin이다. |
| 기본 동작 기전 | `editor-options.php` 계약을 제공하고 `/admin/ckeditor/settings`에서 asset mode, CDN version, license, toolbar preset을 설정한다. |
| 데이터 구조 | 별도 설치 테이블 없음. 설정은 `sr_module_settings`에 저장한다. |
| 관리자 화면 | CKEditor > 설정. |
| 사용자 화면 | 콘텐츠/커뮤니티 등 소비 모듈이 editor option으로 선택한 경우 textarea에 편집기가 적용된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 설정 저장은 권한/CSRF를 요구한다. 저장되는 HTML 정화는 소비 모듈과 sanitizer 정책이 담당한다. |
| 다른 모듈 연동 | admin editor, content editor, community editor 설정과 연동한다. |
| 설정값 | `asset_mode`, `cdn_version`, `license_key`, `toolbar_preset`. |
| 운영 주의사항 | CDN 모드는 외부 네트워크 정책 영향을 받는다. GPL license 기본값을 변경할 경우 라이선스 검토가 필요하다. |
| 테스트 관점 | 설정 저장, 콘텐츠/커뮤니티 작성 화면 editor 로딩, sanitizer 정책 점검. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > CKEditor 설정 | 저장 | CKEditor 설정 권한 | asset mode, CDN version, license key, toolbar preset | 권한과 CSRF를 확인하고 asset mode와 toolbar preset allowlist를 검증한다. CDN version/license 문자열을 정리해 모듈 설정으로 저장한다. | 이후 editor option을 소비하는 화면에서 새 로딩 정책과 toolbar가 적용된다. | 허용되지 않은 mode/preset, 권한 부족 | `sr_module_settings` update | `modules/ckeditor/actions/admin-settings.php`, `modules/ckeditor/helpers.php` |
| 편집 화면 | 에디터 적용 | 소비 모듈이 CKEditor 선택 | textarea selector, toolbar preset | 소비 모듈이 editor option을 조회하면 CKEditor plugin이 asset URL과 toolbar 구성을 반환한다. 브라우저에서 textarea를 rich editor로 초기화한다. | 사용자가 HTML 본문을 편집할 수 있다. 저장 시 소비 모듈 정화 정책으로 처리된다. | asset 로딩 실패, preset 미지원 | 없음 | `modules/ckeditor/editor-options.php`, 소비 모듈 view |

## community

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 게시판, 게시글, 댓글, 첨부, 신고, 스크랩, 쪽지, 시리즈, 레벨, 게시자 보상, 자산 과금 정책을 제공한다. |
| 기본 동작 기전 | 공개 `/community` 하위 route와 관리자 `/admin/community` 하위 route가 분리되어 있다. 게시판 설정과 전역 설정을 helper가 합성하고 skin/theme가 공개 출력을 담당한다. |
| 데이터 구조 | `sr_community_board_groups`, `sr_community_boards`, `sr_community_board_settings`, `sr_community_posts`, `sr_community_comments`, `sr_community_attachments`, `sr_community_reports`, `sr_community_messages`, `sr_community_scraps`, `sr_community_series`, `sr_community_board_copy_jobs`, `sr_community_levels`, `sr_community_asset_logs`, `sr_community_access_entitlements` 등. |
| 관리자 화면 | 게시판/그룹 관리, 게시글/댓글/신고/시리즈 관리, 환경설정, 레벨, 회원 그룹별 설정, 리워드 로그, 게시판 복사 job. |
| 사용자 화면 | 커뮤니티 홈, 그룹, 게시판 목록, 글 보기/작성/수정/삭제, 댓글, 신고, 스크랩, 시리즈, 쪽지, 첨부 다운로드. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 로그인/그룹/레벨/게시판 권한, CSRF, rate limit, 업로드 MIME/크기, paid download 전달 실패 시 차감 방지, 개인정보 동의 조건을 검증한다. |
| 다른 모듈 연동 | menu link, extension point, privacy export/cleanup, sitemap, member group rule/reference, coupon target, banner/popup reference, embed target, reaction target, member asset, notification, admin notification. |
| 설정값 | 페이지당 글/댓글 수, rate limit, 첨부 제한, 레벨 정책, 쪽지 정책, nickname, layout/menu slot, editor, 개인정보 동의, 보상/과금 정책. |
| 운영 주의사항 | 게시판 복사는 job으로 나뉘며 재시도/취소가 필요할 수 있다. 첨부 과금은 자산 원장과 delivery 결과가 함께 맞아야 한다. |
| 테스트 관점 | `php .tools/bin/check-community-release.php`, board settings/copy/attachment/privacy/auth smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 공개 > 글 작성/수정 | 등록/저장 | 게시판 쓰기 권한 | 제목, 본문, 카테고리, 첨부, 시리즈, 개인정보 동의 | 로그인/게시판 상태/그룹/레벨/CSRF/rate limit을 확인한다. 카테고리 필수 여부와 본문 legacy token 금지, 첨부 개수/크기/MIME, 시리즈 소유권을 검증한다. 필요 시 자산 차감/보상 정책을 적용하고 게시글, field value, 첨부, embed refs를 저장한다. | 게시글 상세나 목록으로 이동하고 sitemap/output/reaction 대상 후보가 갱신된다. | 권한 없음, 필수 카테고리 누락, 업로드 실패, 자산 부족, 본문 검증 실패 | `sr_community_posts`, 첨부/field/ref/asset log insert/update | `modules/community/actions/write.php`, `modules/community/actions/edit.php`, `modules/community/helpers.php` |
| 공개 > 댓글 | 작성/수정/숨김/삭제 | 댓글 권한 또는 작성자/관리자 | 댓글 본문, parent_id, secret | 로그인/CSRF/rate limit과 게시글 공개 상태, 댓글 권한, 답글 depth와 부모 댓글 상태를 검증한다. 본문 snapshot과 멘션을 처리하고 수정/삭제는 작성자 또는 관리 권한을 확인한다. | 댓글 목록이 갱신되고 멘션/작성자 알림이 생성될 수 있다. | 권한 없음, 부모 댓글 오류, depth 초과, 본문 누락 | `sr_community_comments` insert/update, 알림 가능 | `modules/community/actions/comment.php`, `comment-edit.php`, `comment-hide.php`, `comment-delete.php` |
| 공개 > 첨부 다운로드 | 다운로드 | 읽기 권한, paid policy 충족 | attachment_id | 게시글/첨부 존재, 읽기 권한, 과금 정책, 기존 entitlement를 확인한다. 유료인 경우 원장 차감 후 delivery 가능성을 확인하고 권한을 기록한다. 파일 헤더를 보내고 실패 시 차감 방지 또는 보정 정책을 적용한다. | 파일이 전송되고 접근권/로그가 남는다. | 권한 없음, 잔액 부족, 파일 없음, 전달 실패 | `sr_community_access_entitlements`, asset logs, 자산 거래 | `modules/community/actions/attachment.php` |
| 공개 > 신고/스크랩/쪽지 | 제출/토글/보내기 | 로그인, 대상 공개/정책 충족 | 신고 사유, post/series ID, 쪽지 수신자/본문 | CSRF와 rate limit을 확인한다. 신고는 대상 존재와 중복/상태를 검증해 접수한다. 스크랩은 같은 대상 기존 record를 찾아 생성/해제한다. 쪽지는 수신자와 작성 정책을 검증하고 메시지를 저장한다. | 내 스크랩/신고/쪽지 화면에 반영되고 알림 가능성이 생긴다. | 대상 없음, 권한 없음, rate limit, 수신자 오류 | reports/scraps/messages insert/delete/update | `modules/community/actions/report.php`, `scrap-toggle.php`, `message-write.php` |
| 관리자 > 게시판/그룹 | 생성/수정/복사 | 게시판 관리 권한 | key, 이름, 상태, 권한, 스킨, 자산 정책 | 권한/CSRF를 확인하고 key 형식, 중복, 그룹 참조, 권한 정책, 카테고리/필드/자산 설정을 검증한다. 복사는 원본 범위와 사본 상태를 정해 job 또는 즉시 저장으로 처리한다. | 공개 게시판 목록과 쓰기/읽기 정책이 바뀐다. | key 중복, 잘못된 권한, 복사 범위 오류 | board/group/settings/category/job 테이블 insert/update | `modules/community/actions/admin-board-create.php`, `admin-board-update.php`, `admin-board-copy.php` |
| 관리자 > 게시글/댓글/신고 | 상태 변경/처리 | 관리 권한 | 선택 ID, 상태, 운영 메모, 조치 | 권한/CSRF와 대상 존재를 확인한다. 게시글/댓글 상태를 공개/숨김/삭제 등으로 바꾸고 신고는 접수/검토/완료/기각 상태와 대상/신고자 조치를 처리한다. 완료 조치는 대상 게시글/댓글/게시자에 적용되고 기각 조치는 허위신고자 정책에만 적용된다. | 관리 목록과 공개 표시가 갱신되고 감사 로그가 남는다. | 상태 전환 불가, 대상 없음, 권한 부족 | posts/comments/reports/account 상태 update | `modules/community/actions/admin-posts.php`, `admin-comments.php`, `admin-reports.php` |
| 관리자/CLI > 게시판 복사 job | 진행/취소/재시도 | 복사 권한 | job_id, 단계 intent | job 상태와 lock을 확인하고 prepare, board, posts, comments, attachments, verify 단계를 나누어 실행한다. 실패 단계는 오류를 기록하고 재시도 가능 상태로 둔다. | 사본 게시판과 선택 데이터가 생성되고 완료/실패 상태가 표시된다. | lock 실패, 원본 없음, 파일 복사 실패 | copy job/maps, board/post/comment/attachment insert | `modules/community/actions/admin-board-copy-jobs.php` |

## content

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 콘텐츠 글, 그룹, 시리즈, 댓글, 다운로드 파일, 회원 제출/작성자 신청, 유료 열람/다운로드, author reward를 관리한다. |
| 기본 동작 기전 | `/content/*` 공개 상세와 `/admin/content` 관리 화면이 있고, slug 기반 공개 URL과 관리자 draft preview를 제공한다. |
| 데이터 구조 | `sr_content_groups`, `sr_content_items`, `sr_content_revisions`, `sr_content_submissions`, `sr_content_author_permissions`, `sr_content_author_applications`, `sr_content_series`, `sr_content_comments`, `sr_content_files`, `sr_content_file_download_logs`, `sr_content_access_entitlements` 등. |
| 관리자 화면 | 콘텐츠/그룹/시리즈/파일/다운로드 내역/회원 제출/작성자 신청/작성자 승인/환경설정/회원 그룹별 설정. |
| 사용자 화면 | 콘텐츠 홈, 그룹 목록, 상세, 댓글, 다운로드, 내 콘텐츠 제출, 작성자 신청. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 권한, 작성자 권한, slug 예약어/중복, CSRF, HTML sanitizer, 업로드 검증, 유료 자산 처리, 승인 보상 중복 방지를 적용한다. |
| 다른 모듈 연동 | extension point, menu link, privacy export/cleanup, sitemap, homepage candidate, member group rule/reference, coupon/banner/popup reference, layout, embed target, reaction target, member asset, notification. |
| 설정값 | editor, toolbar, auto link, once history policy, layout/menu slot, member submission/reward 정책. |
| 운영 주의사항 | 예약 발행 전에는 공개 목록에서 제외된다. 파일 삭제/교체는 entitlement와 다운로드 로그 보존을 함께 고려한다. |
| 테스트 관점 | `php .tools/bin/check-content-copy-runtime.php`, file cleanup/paid download/cover image 관련 smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 콘텐츠 | 저장 | 콘텐츠 관리 권한 | 제목, slug, 상태, 본문, SEO, 공개시각, 그룹/시리즈, 자산 정책 | 권한/CSRF를 확인하고 slug 형식/중복/예약어, 상태, 예약 시각, 본문 sanitizer, legacy embed token 금지, 그룹/시리즈 참조, 커버/파일/자산 정책을 검증한다. item과 revision을 저장하고 refs를 transaction 안에서 동기화한다. | 목록/편집 화면으로 이동하고 공개 상세, sitemap, menu 후보가 갱신된다. | slug 오류, 본문 정화 실패, 참조 대상 없음, 자산 정책 오류 | `sr_content_items`, revisions, refs, settings insert/update | `modules/content/actions/admin-content-save.php`, `modules/content/actions/admin-content-edit.php` |
| 관리자 > 콘텐츠 | 복사/삭제 | 콘텐츠 관리 권한 | content_id, 복사 범위 | 복사는 원본과 연결 그룹/시리즈 범위를 확인해 draft/hidden 사본을 만들고 필요한 refs를 새 대상 기준으로 동기화한다. 삭제는 상태와 참조를 확인해 공개 제외 또는 삭제 처리를 수행한다. | 사본이 생성되거나 공개 목록에서 제외된다. | 원본 없음, 참조 제한, 권한 부족 | items/revisions/series refs insert/update/delete | `modules/content/actions/admin-content-copy.php`, `admin-content-delete.php` |
| 공개 > 콘텐츠 상세 | 완료/댓글/다운로드 | 공개 상태, 접근 정책 충족 | action intent, 댓글, file_id | 공개 기간/상태/열람 권한/유료 entitlement를 확인한다. 완료 버튼은 설정된 자산 지급/차감을 처리하고, 댓글은 로그인/권한/depth/secret/멘션을 처리하며, 다운로드는 파일 접근권과 전달 가능성을 확인한다. | 상세 화면에 완료 상태/댓글/다운로드 결과가 반영되고 알림/자산 원장이 생성될 수 있다. | 비공개, 권한 없음, 잔액 부족, 파일 없음, 댓글 검증 실패 | comments, entitlements, file download logs, asset action logs | `modules/content/actions/view.php`, `action.php`, `comment.php`, `download.php` |
| 회원 > 제출/작성자 신청 | 저장/신청 | 로그인, 설정 활성 | 제출 본문, 신청 메모, 프로필 | 로그인/CSRF와 설정 활성 여부를 확인한다. 제출은 임시저장/제출 상태를 저장하고, 작성자 신청은 중복 신청과 상태를 검증해 접수한다. | 내 콘텐츠 또는 신청 화면에 상태가 표시된다. | 기능 비활성, 필수값 누락, 중복 신청 | submissions, author_applications insert/update | `modules/content/actions/account-content.php`, `account-content-author-application.php` |
| 관리자 > 제출/작성자 | 승인/반려/차단 | 승인 권한 | submission/application ID, 상태, 메모 | 대상 상태와 권한/CSRF를 확인한다. 제출 승인은 콘텐츠 item/revision으로 반영하고 author reward 설정이 있으면 중복 지급을 막은 뒤 자산 지급 로그를 남긴다. 작성자 승인은 권한 row를 생성/갱신한다. | 공개 콘텐츠 또는 작성자 권한이 생성되고 회원에게 상태가 보인다. | 상태 전환 불가, 보상 지급 실패, 대상 없음 | content items, author permissions, reward logs | `modules/content/actions/admin-content-submissions.php`, `admin-content-author-applications.php`, `admin-content-authors.php` |

## coupon

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 쿠폰·이용권 정의, 회원 지급, 사용 내역을 관리한다. |
| 기본 동작 기전 | 회원 `/account/coupons`가 보유 쿠폰을 보여주고, 관리자 `/admin/coupons`가 정의/지급/사용 내역을 한 action에서 탭별 처리한다. |
| 데이터 구조 | `sr_coupon_definitions`, `sr_coupon_issues`, `sr_coupon_redemptions`. |
| 관리자 화면 | 쿠폰 관리, 지급 내역, 사용 내역, 대상/회원 검색. |
| 사용자 화면 | 내 쿠폰 목록. 소비 모듈은 coupon reference/target 계약으로 사용 가능 여부를 조회한다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장/지급/회수는 권한/CSRF와 대상/회원 검증을 요구한다. |
| 다른 모듈 연동 | coupon target/reference, notification, privacy export, member withdrawal assets, dashboard. |
| 설정값 | 정의별 상태, 기간, 대상 module/type, 사용 가능 수량/조건. 별도 module setting은 확인 필요. |
| 운영 주의사항 | 쿠폰 정의 비활성화는 기존 지급분을 자동 삭제하지 않는다. 사용 내역은 사후 추적을 위해 보존한다. |
| 테스트 관점 | `php .tools/bin/check-coupon-admin-validation.php`, `php .tools/bin/check-coupon-redemption-runtime.php`. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 쿠폰 관리 | 정의 저장/삭제 | 쿠폰 관리 권한 | key, 이름, 상태, 기간, 대상, 수량 | 권한/CSRF와 key 형식, 기간, 대상 계약 존재, 수량/상태를 검증한다. 정의를 생성/수정하고 삭제 또는 비활성 처리 시 기존 지급/사용 참조를 확인한다. | 소비 모듈의 쿠폰 선택 후보와 회원 지급 가능 항목이 갱신된다. | key 중복, 대상 없음, 기간 오류 | `sr_coupon_definitions` insert/update/delete | `modules/coupon/actions/admin-coupons.php` |
| 관리자 > 지급 내역 | 지급/취소 | 쿠폰 관리 권한 | coupon_id, account_id, 만료일, 사유 | 회원 검색 결과와 정의 상태를 확인하고 중복/수량 제한을 검증해 issue row를 만든다. 취소는 미사용 상태인지 확인한 뒤 상태를 바꾼다. 알림 모듈이 있으면 지급 알림을 만들 수 있다. | 회원 내 쿠폰 화면에 지급분이 표시된다. | 회원 없음, 정의 비활성, 이미 사용됨, 권한 부족 | `sr_coupon_issues` insert/update, 알림 가능 | `modules/coupon/actions/admin-coupons.php`, `admin-coupon-member-search.php` |
| 소비 모듈 | 쿠폰 사용 | 대상 모듈 정책 충족 | issue_id, target reference | 소비 모듈이 쿠폰 사용 가능 여부를 조회하고, 쿠폰 모듈은 issue 상태/기간/대상 일치/중복 사용을 검증한다. 사용 성공 시 redemption을 기록하고 issue 상태나 사용 수량을 갱신한다. | 대상 기능의 할인/이용권 처리가 완료된다. | 만료, 대상 불일치, 이미 사용됨 | `sr_coupon_redemptions`, `sr_coupon_issues` update | `modules/coupon/helpers.php`, `modules/coupon/coupon-references.php` |

## deposit

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 회원 예치금 잔액, 거래, 수동 조정, 환불 신청을 관리한다. |
| 기본 동작 기전 | 회원 `/account/deposits`, 관리자 `/admin/deposits/*` route를 제공하고 `asset_ledger` 기반 원장 거래를 사용한다. |
| 데이터 구조 | `sr_deposit_balances`, `sr_deposit_transactions`, `sr_deposit_refund_requests`. |
| 관리자 화면 | 잔액, 거래 내역, 환불 신청, 환경설정, 수동 조정, 참조 검색. |
| 사용자 화면 | 내 예치금 잔액/거래, 환불 신청/취소. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 로그인/CSRF, 관리자 권한, 조정 한도/승인자, 환불 가능 금액/대상 그룹을 검증한다. |
| 다른 모듈 연동 | asset exchange, member assets, member withdrawal assets, member group references, notification, privacy export, dashboard. |
| 설정값 | 표시명/단위, 환불 신청 사용 여부, 최소/최대 금액, 대상 그룹, 관리자 조정 한도. |
| 운영 주의사항 | 거래 직접 삭제가 아니라 반대 거래/환불 거래로 보정한다. 환불 완료는 원장 차감과 신청 상태가 함께 맞아야 한다. |
| 테스트 관점 | 자산 원장 계약, 환불 신청/처리 수동 smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 회원 > 예치금 | 환불 신청/취소 | 로그인, 신청 기능 활성 | 금액, 환불 계좌/메모 | 로그인/CSRF와 신청 대상 그룹, 최소/최대, 가용 잔액, 기존 pending 금액을 검증한다. 신청 row를 만들고 취소는 본인 pending 상태만 허용한다. | 신청 목록에 요청/취소 상태가 표시되고 관리자 처리 대상이 된다. | 기능 비활성, 금액 오류, 잔액 부족 | `sr_deposit_refund_requests` insert/update | `modules/deposit/actions/account-deposits.php` |
| 관리자 > 예치금 조정 | 지급/차감/환불 | 조정 권한 | 회원, 금액, 사유, 참조 | 권한/CSRF, 회원 존재, 금액 부호와 type, 1회/일일 한도, 승인자 요구 여부, 참조 형식을 검증한다. 원장 거래와 잔액을 transaction으로 반영하고 감사 로그를 남긴다. | 잔액/거래 목록이 갱신되고 회원 화면에도 반영된다. | 한도 초과, 회원 없음, 잔액 부족, 승인자 누락 | `sr_deposit_transactions`, `sr_deposit_balances` | `modules/deposit/actions/admin-deposits-adjust.php`, `modules/deposit/helpers.php` |
| 관리자 > 환불 신청 | 완료/반려/일괄 처리 | 환불 관리 권한 | request_id, 상태, 관리자 메모 | 대상 신청 상태와 가용 잔액을 다시 확인한다. 완료는 원장 차감 거래를 만들고 신청에 처리자/처리시각/거래 ID를 연결한다. 반려는 메모와 상태를 저장하고 잔액은 바꾸지 않는다. | 신청 상태가 완료/반려로 바뀌고 회원 내역에 반영된다. | 이미 처리됨, 잔액 부족, 메모 필수 조건 누락 | refund_requests update, transactions/balances update | `modules/deposit/actions/admin-deposits-refund-requests.php` |
| 관리자 > 환경설정 | 저장 | 설정 권한 | 표시명, 환불 정책, 한도, 대상 그룹 | 권한/CSRF와 금액/그룹 key/boolean을 검증해 설정을 저장한다. 대상 그룹 참조 상태를 점검한다. | 회원 환불 신청 노출 조건과 관리자 조정 정책이 바뀐다. | 잘못된 그룹, 금액 범위 오류 | `sr_module_settings` update | `modules/deposit/actions/admin-deposits-settings.php` |

## embed_manager

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 본문 안의 여러 모듈 대상을 marker와 참조 행으로 연결하는 기반 모듈이다. |
| 기본 동작 기전 | `/admin/embed-manager`에서 refs 상태를 조회한다. 콘텐츠/커뮤니티 저장 action은 marker를 정화 후 refs로 동기화한다. |
| 데이터 구조 | `sr_embed_manager_refs`. subject module/type/id와 target module/type/id, ref key, 상태 정보를 저장한다. |
| 관리자 화면 | 임베드 참조 조회. |
| 사용자 화면 | 직접 화면 없음. 본문 렌더링 시 대상 카드/링크 표시로 간접 노출된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 조회 권한. 저장 action은 legacy token 저장을 거부하고 target resolver로 공개 가능 대상만 refs에 넣어야 한다. |
| 다른 모듈 연동 | `embed-manager-targets.php`를 제공하는 content/community/quiz/survey 등과 연동한다. |
| 설정값 | 자체 설정 없음. |
| 운영 주의사항 | refs는 삭제 차단 원장이 아니라 상태 점검/렌더링 보조 자료다. 대상 비공개/삭제 시 broken/private/deleted 표시 정책이 필요하다. |
| 테스트 관점 | `php .tools/bin/check-read-reference-contracts.php`, 콘텐츠/커뮤니티 저장 시 marker 동기화 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 임베드 참조 | 조회 | 임베드 관리 권한 | module/type/status 필터 | 권한을 확인하고 refs를 subject/target 기준으로 조회한다. target 계약을 통해 현재 상태와 관리자 URL을 보강한다. | 깨진 참조나 비공개 대상 후보를 한 화면에서 확인한다. | 권한 부족, 잘못된 필터 | 조회만 수행 | `modules/embed_manager/actions/admin-embed-manager.php` |
| 소비 모듈 저장 | refs 동기화 | 콘텐츠/커뮤니티 저장 권한 | 정화된 본문 marker | 저장 action이 legacy token을 거부하고 최종 HTML에 남은 marker만 파싱한다. target module/type/id 소유권과 공개/삽입 가능 여부를 검증한 뒤 기존 refs를 subject 단위로 교체한다. | 공개 렌더링과 관리자 점검에서 연결 대상이 표시된다. | target 없음, marker 변조, legacy token 잔존 | `sr_embed_manager_refs` delete/insert | `modules/embed_manager/helpers.php`, 소비 모듈 save action |

## logo_manager

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 용도별 로고와 favicon/icon variant, 적용 기간을 관리한다. |
| 기본 동작 기전 | `/admin/logo-manager`에서 로고를 저장하고 `/logo-manager/image`가 저장 이미지를 제공한다. `site-setting-references.php`를 제공한다. |
| 데이터 구조 | `sr_logo_manager_logos`, `sr_logo_manager_icon_variants`. |
| 관리자 화면 | 로고 매니저 목록/저장. |
| 사용자 화면 | public layout/helper가 활성 로고 URL과 favicon link를 출력한다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장은 권한/CSRF와 이미지 MIME/크기/storage key 검증을 수행한다. |
| 다른 모듈 연동 | `logo-positions.php`, site setting reference, public layout helper. |
| 설정값 | 위치별 활성 로고는 DB row 기준. site setting 참조 연결 가능. |
| 운영 주의사항 | 기간/상태/position 조건에 따라 같은 로고 파일이 있어도 출력되지 않을 수 있다. favicon variant 생성/삭제 파일 권한을 확인한다. |
| 테스트 관점 | 로고 업로드/삭제, public layout 로고와 favicon 출력 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 로고 매니저 | 저장/삭제 | 로고 관리 권한 | 위치, 제목, 이미지, 기간, 상태 | 권한/CSRF를 확인하고 position allowlist, 기간, 상태, 이미지 업로드를 검증한다. 로고 row와 icon variant를 저장하거나 삭제/비활성 처리한다. | 공개 helper가 해당 위치의 새 로고를 반환한다. | 업로드 실패, 잘못된 position/기간, 권한 부족 | `sr_logo_manager_logos`, `sr_logo_manager_icon_variants`, 파일 저장 | `modules/logo_manager/actions/admin-logo-manager.php` |
| 공개 layout | 로고 이미지 출력 | 활성 로고 존재 | image ID/ref | image action이 storage reference와 MIME을 확인해 파일을 전송한다. helper는 현재 시각과 position에 맞는 로고 URL을 선택한다. | 헤더 로고/favicon이 표시된다. | 파일 없음, 비활성/기간 외 | 조회만 수행 | `modules/logo_manager/actions/image.php`, `modules/logo_manager/helpers.php` |

## member

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 회원 계정, 인증, 프로필, 닉네임, 이메일 인증, 비밀번호 재설정, 탈퇴, 관리자 회원/그룹/규칙 관리를 제공한다. |
| 기본 동작 기전 | 로그인/회원가입/계정 route와 관리자 회원 route가 분리되어 있다. 공개 계정 식별은 public hash와 public name을 사용한다. |
| 데이터 구조 | `sr_member_accounts`, `sr_member_auth_logs`, `sr_member_profiles`, `sr_member_nicknames`, `sr_member_sessions`, `sr_member_password_resets`, `sr_member_email_verifications`, `sr_member_consents`, `sr_member_groups`, `sr_member_group_memberships`, `sr_member_group_rules`, `sr_member_group_membership_logs`. |
| 관리자 화면 | 회원 목록/생성/수정/검색/요약, 회원 설정, 그룹, 그룹 규칙, 그룹 평가/배정. |
| 사용자 화면 | 로그인, 회원가입, 내 계정, 탈퇴, 이메일 인증, 비밀번호 재설정, 아바타/멘션 검색. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 비밀번호 hash, 로그인/가입/재설정 throttle, CSRF, session 관리, 이메일 token hash, 개인정보 cleanup, 관리자 권한을 적용한다. |
| 다른 모듈 연동 | extension point, menu link, privacy export, dashboard, member group rules/references, member registration, privacy cleanup, member withdrawal assets. |
| 설정값 | 가입 허용, 이메일 인증, 로그인 식별자, throttle, 스킨, 프로필/닉네임 항목 표시/필수 여부. |
| 운영 주의사항 | 탈퇴는 자산/개인정보 cleanup 계약과 함께 처리된다. owner/admin 계정 상태 변경은 잠금 방지 기준을 따라야 한다. |
| 테스트 관점 | `php .tools/bin/check-auth-runtime.php`, `php .tools/bin/check-member-auth-policy.php`, authenticated smoke는 로컬/스테이징 자격 증명 필요. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 공개 > 회원가입 | 가입 | 가입 허용 | login_id, email, password, profile, 약관 동의 | CSRF, throttle, antispam surface, 필수 동의, 이메일/login ID 중복, 비밀번호 정책, 닉네임/프로필 필수 조건을 검증한다. 계정, profile, nickname, consent, 이메일 인증 token을 생성하고 auth log를 남긴다. | 로그인 또는 인증 안내 화면으로 이동한다. | 가입 비활성, 중복, 비밀번호 오류, 필수 동의 누락 | member accounts/profiles/nicknames/consents/verifications insert | `modules/member/actions/register.php` |
| 공개 > 로그인/로그아웃 | 로그인/로그아웃 | 비로그인/로그인 | 식별자, 비밀번호 | throttle과 계정 상태를 확인하고 password hash를 검증한다. 성공 시 session을 생성/갱신하고 auth log를 남긴다. 로그아웃은 session을 폐기한다. | 로그인 후 return URL 또는 계정/관리자 화면으로 이동한다. | 인증 실패, 잠금/탈퇴 상태, throttle 초과 | sessions, auth_logs insert/update | `modules/member/actions/login.php`, `logout.php` |
| 공개 > 내 계정 | 저장/비밀번호 변경/탈퇴 | 로그인 | 프로필, 비밀번호, 탈퇴 확인 | CSRF와 현재 계정을 확인한다. 프로필 필수 조건과 아바타 업로드를 검증하고, 비밀번호 변경은 현재 비밀번호와 정책을 확인한다. 탈퇴는 재확인 후 개인정보 cleanup과 자산 withdrawal 계약을 호출한다. | 계정 정보가 갱신되거나 탈퇴 처리 후 세션이 종료된다. | 비밀번호 불일치, 필수값 누락, cleanup 실패 | accounts/profiles/nicknames/sessions update, cleanup 대상 테이블 | `modules/member/actions/account.php`, `withdraw.php` |
| 공개 > 이메일/비밀번호 재설정 | 요청/확인 | 기능 활성 | email, token, 새 비밀번호 | 요청은 throttle과 계정 존재를 확인하고 token hash/만료를 저장한다. 확인은 token 원문을 hash로 비교하고 만료/사용 여부를 검증한 뒤 비밀번호를 갱신한다. | 인증 완료 또는 비밀번호 재설정 완료 화면으로 이동한다. | token 만료/불일치, throttle, 계정 없음 | email_verifications/password_resets/accounts update | `modules/member/actions/email-verification-request.php`, `password-reset-request.php`, `password-reset.php` |
| 관리자 > 회원 | 생성/수정/상태 변경 | 회원 관리 권한 | 계정 정보, 상태, 그룹, 프로필 | 권한/CSRF, login/email/nickname 중복, 상태 allowlist, owner 잠금 위험을 검증한다. 계정과 프로필/닉네임/그룹을 저장하고 감사 로그를 남긴다. | 회원 목록/상세에 반영되고 다른 모듈 권한/그룹 조건도 바뀐다. | 중복, 필수값 누락, owner 보호, 권한 부족 | member accounts/profiles/nicknames/group memberships update | `modules/member/actions/admin-members-save.php` |
| 관리자 > 그룹/규칙 | 저장/평가/배정 | 그룹 관리 권한 | group key, rule 조건, account_id | key 형식/중복, rule provider 존재, 조건값을 검증한다. 수동 배정/해제는 대상 계정과 그룹을 확인하고 로그를 남긴다. 재계산은 rule을 평가해 membership을 갱신한다. | 그룹 기반 접근/혜택 조건이 반영된다. | key 오류, provider 없음, 계정/그룹 없음 | groups/rules/memberships/logs insert/update/delete | `modules/member/actions/admin-groups-save.php`, `admin-group-rules-save.php`, `admin-group-assignments-grant.php` |

## notification

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 사이트 알림, 운영 알림, 이메일 발송 작업, 알림 읽음 상태, 이벤트 템플릿을 관리한다. |
| 기본 동작 기전 | 회원 `/account/notifications`, 관리자 `/admin/notifications` 및 delivery 화면, CLI delivery runner가 함께 동작한다. |
| 데이터 구조 | `sr_notifications`, `sr_notification_deliveries`, `sr_notification_push_endpoints`, `sr_notification_reads`, `sr_admin_notifications`, `sr_admin_notification_reads`, `sr_notification_event_templates`. |
| 관리자 화면 | 운영 알림, 회원 알림, 전달 내역, 알림 설정, 새 알림 작성. |
| 사용자 화면 | 내 알림 목록, 알림 읽기. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 발송/삭제는 권한/CSRF, 회원 읽기는 로그인과 본인 알림 검증을 수행한다. 이메일 발송은 transport 설정과 수신자 상태를 확인한다. |
| 다른 모듈 연동 | `notification-events.php`, `admin-notification-events.php`를 제공해 콘텐츠/커뮤니티/자산/리액션/퀴즈/설문 이벤트를 받는다. privacy export/cleanup. |
| 설정값 | 이메일 transport, 발송 batch, 이벤트 템플릿/사용 여부, 알림 보존. |
| 운영 주의사항 | CLI delivery는 반복 실행 가능해야 하며 실패 delivery는 상태/오류 메시지로 추적한다. |
| 테스트 관점 | `php .tools/bin/check-notification-runtime.php`, `php .tools/bin/run-notification-deliveries.php`는 로컬/스테이징 설정 확인 후 실행. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 알림 작성 | 발송 생성 | 알림 관리 권한 | 대상, 제목, 본문, channel | 권한/CSRF와 대상 회원/그룹, 제목/본문, channel을 검증한다. notification row와 delivery 후보를 만들고 이벤트 템플릿을 적용할 수 있다. | 대상 회원 알림함과 delivery 대기 목록에 표시된다. | 대상 없음, 필수값 누락, 권한 부족 | notifications, deliveries insert | `modules/notification/actions/admin-notification-create.php` |
| 관리자 > 전달 내역 | 상태 변경/재시도 | 전달 관리 권한 | delivery_id, 상태/intent | 권한/CSRF와 delivery 상태를 확인한다. 실패/대기 건을 재시도 가능 상태로 바꾸거나 수동 상태를 저장한다. | delivery 목록 상태가 갱신된다. | 상태 전환 불가, 대상 없음 | `sr_notification_deliveries` update | `modules/notification/actions/admin-notification-deliveries.php`, `admin-notification-delivery-status.php` |
| 회원 > 알림 | 읽음/삭제성 처리 | 로그인 | notification_id | 로그인 계정과 대상 알림을 확인한다. 읽음 row를 만들거나 읽음 상태를 갱신하고 안전한 action URL이면 이동한다. | 알림이 읽음 처리되고 관련 화면으로 이동한다. | 대상 없음, 본인 알림 아님 | `sr_notification_reads` insert/update | `modules/notification/actions/account-notifications.php`, `account-notification-read.php` |
| CLI | delivery 실행 | 로컬/운영 cron | batch 옵션 | 대기 delivery를 조회하고 transport 준비 상태를 확인한다. 이메일/API 발송을 시도해 성공/실패 상태, 시각, 오류 메시지를 기록한다. 반복 실행 시 이미 완료된 건은 건너뛴다. | 발송 상태가 완료/실패로 갱신된다. | transport 미설정, 외부 API 실패 | `sr_notification_deliveries` update | `.tools/bin/run-notification-deliveries.php`, `modules/notification/helpers.php` |

## point

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 회원 포인트 잔액, 거래, 만료 소비 매핑, 수동 조정을 관리한다. |
| 기본 동작 기전 | 회원 `/account/points`, 관리자 `/admin/points/*` route를 제공하고 `asset_ledger` 기반으로 거래를 생성한다. |
| 데이터 구조 | `sr_point_balances`, `sr_point_transactions`, `sr_point_expiration_consumptions`. |
| 관리자 화면 | 잔액, 거래 내역, 환경설정, 수동 조정, 참조 검색. |
| 사용자 화면 | 내 포인트 잔액/거래. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 권한/CSRF, 조정 한도/사유/참조, 포인트 만료 정책을 검증한다. |
| 다른 모듈 연동 | asset exchange, member assets, member withdrawal assets, notification, privacy export, dashboard. |
| 설정값 | 표시명/단위, 기본 유효기간, 조정 한도, 알림. |
| 운영 주의사항 | 만료는 지급분별 잔여량과 소비 매핑을 기준으로 처리한다. 잔액 직접 수정 금지. |
| 테스트 관점 | `php .tools/bin/expire-points.php`, 자산 원장 계약 점검. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 포인트 조정 | 지급/차감/환불/회수 | 조정 권한 | 회원, 금액, 유형, 사유, 참조 | 권한/CSRF, 회원, 금액, 유형별 허용 부호, 한도, 참조를 검증한다. asset ledger로 거래와 잔액을 반영하고 만료 가능 지급분이면 expiration metadata를 저장한다. | 잔액과 거래 내역이 갱신된다. | 잔액 부족, 한도 초과, 회원 없음 | `sr_point_transactions`, `sr_point_balances`, 만료 매핑 가능 | `modules/point/actions/admin-points-adjust.php`, `modules/point/helpers.php` |
| 회원/관리자 > 거래 조회 | 조회 | 로그인 또는 관리 권한 | 기간, 회원, 유형 | 필터를 정규화하고 본인 또는 관리자 권한을 확인한다. 거래와 잔액을 pagination으로 조회한다. | 내역 화면에 상대/정확 시간이 함께 표시된다. | 권한 부족, 잘못된 필터 | 조회만 수행 | `modules/point/actions/account-points.php`, `admin-points-transactions.php` |
| CLI | 포인트 만료 | 운영자 실행 | cutoff/옵션 | 만료 대상 지급 거래의 남은 만료 가능 금액을 계산하고 오래된 지급분부터 만료 차감 거래를 만든다. 반복 실행 시 이미 소비/만료된 잔여량은 제외한다. | 만료 거래가 생성되고 잔액이 줄어든다. | DB 오류, 대상 없음 | point transactions/balances/expiration_consumptions update | `.tools/bin/expire-points.php`, `modules/point/helpers.php` |

## popup_layer

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 공개 output slot에 노출할 팝업레이어와 본문 파일을 관리한다. |
| 기본 동작 기전 | 관리자 `/admin/popup-layers` CRUD와 공개 `/popup-layer/body-file` 파일 제공 route를 사용한다. |
| 데이터 구조 | `sr_popup_layers`, `sr_popup_layer_targets`. |
| 관리자 화면 | 팝업레이어 목록, 신규/수정, 설정, body file upload. |
| 사용자 화면 | output slot에서 기간/대상/쿠키 조건에 맞는 팝업이 표시된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장/업로드 권한/CSRF, 본문 HTML 정화, 파일 업로드 검증, dismiss cookie 일수 검증. |
| 다른 모듈 연동 | `output-slots.php`, `extension-points.php`, `popup-layer-references.php`. |
| 설정값 | `popup_layer_skin_key`, 기본 상태, 기본 target/match, dismiss cookie days. |
| 운영 주의사항 | 팝업은 사용자 경험에 직접 영향을 주므로 기간, 대상, dismiss 일수를 검토해야 한다. |
| 테스트 관점 | CRUD, body file 제공, output slot 노출/닫기 쿠키 수동 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 팝업레이어 | 저장 | 팝업 관리 권한 | 제목, 본문, 상태, 기간, 대상, 위치, 닫기 정책 | 권한/CSRF와 필수값, 기간, 상태, target, dismiss 일수, 본문 sanitizer를 검증한다. 팝업과 target 조건을 저장하고 body file refs를 정리한다. | 공개 output slot 후보가 갱신된다. | 기간 오류, 본문 정화 실패, target 없음 | `sr_popup_layers`, `sr_popup_layer_targets` insert/update | `modules/popup_layer/actions/admin-popup-layer-save.php` |
| 관리자 > 팝업레이어 | 복사/삭제/일괄 상태 | 팝업 관리 권한 | popup_id, 선택 ID, 상태 | 원본/대상 존재와 권한/CSRF를 확인한다. 복사는 draft 사본을 만들고 삭제/일괄 상태 변경은 공개 노출 후보에서 제외되도록 상태를 바꾼다. | 목록과 공개 출력이 갱신된다. | 대상 없음, 상태 오류 | popup tables insert/update/delete | `modules/popup_layer/actions/admin-popup-layer-copy.php`, `admin-popup-layer-delete.php`, `admin-popup-layers.php` |
| 관리자 > 본문 파일 | 업로드 | 팝업 관리 권한 | file upload | CSRF, MIME, 크기, 저장 경로를 검증하고 storage에 저장한다. 반환 URL은 본문 editor에서 사용할 수 있게 제공한다. | 본문에 삽입 가능한 파일 URL이 반환된다. | 업로드 실패, 허용되지 않은 파일 | 파일 저장 | `modules/popup_layer/actions/admin-body-file-upload.php` |

## privacy

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 개인정보 사본 제공, 계정 개인정보 요청, cookie consent 보조, 관리자 요청 처리를 제공한다. |
| 기본 동작 기전 | 회원 `/account/privacy-requests`, `/account/privacy-export`, 관리자 `/admin/privacy-requests`, cookie route가 분리되어 있다. |
| 데이터 구조 | `sr_privacy_requests`. 각 모듈의 `privacy-export.php`, `privacy-cleanup.php` 계약을 소비한다. |
| 관리자 화면 | 개인정보 요청 목록/처리/export. |
| 사용자 화면 | 내 개인정보 요청, 개인정보 사본 다운로드, cookie settings/consent. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 로그인/본인 확인, 관리자 권한/CSRF, export 권한, 민감 로그 sanitize, admin note 조건을 검증한다. |
| 다른 모듈 연동 | member, content, community, notification, quiz, survey, reaction, 자산 모듈의 export/cleanup 계약. admin notification events. |
| 설정값 | 요청 처리 정책은 모듈 설정 또는 site setting 여부 확인 필요. |
| 운영 주의사항 | export는 민감 정보를 포함할 수 있어 로컬/스테이징 테스트와 production 처리를 구분해야 한다. |
| 테스트 관점 | `php .tools/bin/check-privacy-export-runtime.php`, cleanup runtime, contract matrix. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 회원 > 개인정보 요청 | 요청 생성/취소 | 로그인 | 요청 유형, 메모 | 로그인/CSRF와 요청 유형, 중복 pending 여부를 확인한다. 요청 row를 만들고 관리자 알림 이벤트를 만들 수 있다. 취소는 본인 pending 요청만 허용한다. | 내 요청 목록에 상태가 표시된다. | 중복 요청, 잘못된 유형, 권한 없음 | `sr_privacy_requests` insert/update | `modules/privacy/actions/account-privacy-requests.php` |
| 회원/관리자 > export | 사본 생성 | 본인 또는 관리자 권한 | request_id/account_id | 권한과 대상 계정을 확인하고 활성 모듈의 privacy export 계약을 순회한다. 결과를 JSON/다운로드 응답으로 구성하고 요청 상태/처리 기록을 갱신한다. | 개인정보 사본 파일이 내려받아진다. | 계약 오류, 대상 없음, 권한 부족 | `sr_privacy_requests` update 가능 | `modules/privacy/actions/account-privacy-export.php`, `admin-privacy-request-export.php` |
| 관리자 > 개인정보 요청 | 상태 저장 | 개인정보 관리 권한 | 상태, 관리자 메모, terminal 처리 | 권한/CSRF, 상태 전환, terminal 상태에서 관리자 메모 필수 조건을 검증한다. 처리자/처리시각과 메모를 저장하고 알림 가능성을 남긴다. | 요청 목록 상태가 갱신되고 회원에게 처리 결과가 보인다. | 메모 누락, 상태 오류, 권한 부족 | `sr_privacy_requests` update | `modules/privacy/actions/admin-privacy-requests.php` |
| 공개 > cookie consent | 저장 | 공개 사용자 | consent 값 | consent 값을 allowlist로 검증하고 쿠키 또는 세션에 저장한다. | cookie settings가 반영된다. | 잘못된 값 | 쿠키/세션 변경 | `modules/privacy/actions/cookie-consent.php`, `cookie-settings.php` |

## quiz

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 퀴즈 세트, 문제/선택지, 공개 응시, 채점, 결과, 보상, 댓글, 시도/보상 관리를 제공한다. |
| 기본 동작 기전 | 공개 `/quiz`, `/quiz/*`, 관리자 `/admin/quiz` route가 있다. 콘텐츠 source 연동과 reward provider를 사용한다. |
| 데이터 구조 | `sr_quiz_sets`, `sr_quiz_comments`, `sr_quiz_sources`, `sr_quiz_questions`, `sr_quiz_choices`, `sr_quiz_results`, `sr_quiz_result_rules`, `sr_quiz_reward_policies`, `sr_quiz_attempts`, `sr_quiz_attempt_answers`, `sr_quiz_attempt_result_scores`, `sr_quiz_reward_grants`. |
| 관리자 화면 | 퀴즈 관리, 시도/보상 내역, 댓글 관리, 환경설정, 매뉴얼. |
| 사용자 화면 | 퀴즈 홈, 퀴즈 상세/응시/결과, 댓글. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 공개 기간, 로그인/그룹 조건, 시도 제한, CSRF, 댓글 권한/depth, 보상 중복 지급, 적립금 회수 검증. |
| 다른 모듈 연동 | menu link, layout, privacy export/cleanup, dashboard, coupon reference, sitemap, embed target, reaction target, member asset, notification. |
| 설정값 | MVP source module/type, layout/skin/menu slot, 보상 provider. |
| 운영 주의사항 | 보상 지급 실패는 grant 상태로 남겨 재시도/회수 판단이 필요하다. |
| 테스트 관점 | `php .tools/bin/check-quiz-consistency.php`, `php .tools/bin/check-quiz-delete-runtime.php`. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 퀴즈 관리 | 저장/삭제 | 퀴즈 관리 권한 | key, 제목, 상태, 기간, 문제/선택지, 결과/보상 | 권한/CSRF와 key, 상태, 기간, 문제 유형, 선택지 정답, 결과 규칙, source, 그룹 조건, 보상 provider를 검증한다. 퀴즈와 하위 문제/선택지/결과/보상 정책을 교체 저장한다. | 공개 퀴즈 목록과 응시 정책이 갱신된다. | key 중복, 문항 오류, 보상 대상 없음 | quiz tables insert/update/delete | `modules/quiz/actions/admin-quiz.php` |
| 공개 > 퀴즈 응시 | 제출 | 공개/응시 가능 | 답안, return_to | 로그인 필요 여부, 공개 기간, 그룹, 시도 제한, CSRF를 확인한다. 답안을 문항별로 검증하고 점수/통과/결과를 계산해 attempt와 answer snapshot을 저장한다. 보상 정책 dedupe를 확인해 자산/쿠폰 지급 grant를 만든다. | 결과 화면이 표시되고 보상/알림이 생성될 수 있다. | 기간 외, 시도 초과, 답안 누락, 보상 실패 | attempts/answers/result_scores/reward_grants insert | `modules/quiz/actions/view.php`, `modules/quiz/helpers.php` |
| 공개/관리자 > 댓글 | 작성/수정/삭제/상태 | 댓글 사용, 권한 충족 | 댓글 본문, parent_id, secret/status | CSRF, 로그인, target 상태, 댓글 사용 여부, depth, secret visibility를 검증한다. 멘션 알림을 만들고 관리자 상태 변경은 감사 로그를 남긴다. | 댓글 목록과 알림이 갱신된다. | 권한 없음, 부모 오류, 본문 누락 | `sr_quiz_comments` insert/update | `modules/quiz/actions/comment.php`, `admin-comments.php` |
| 관리자 > 시도/보상 | 조회/회수 | 시도 관리 권한 | attempt/grant ID | 필터로 시도/보상 내역을 조회한다. 적립금 회수는 grant 기준 원장 거래와 회수 가능액을 확인하고 이미 회수된 금액을 제외해 회수 거래를 만든다. | 보상 상태와 자산 원장이 갱신된다. | 회수 가능액 없음, 대상 없음, 권한 부족 | reward_grants update, 자산 거래 가능 | `modules/quiz/actions/admin-attempts.php` |

## reaction

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 콘텐츠, 커뮤니티, 퀴즈, 설문이 함께 사용하는 공통 리액션 정의, preset, 원장, 레코드 점검을 제공한다. |
| 기본 동작 기전 | `/reaction/write`가 회원 리액션을 처리하고 `/admin/reactions`가 정의/preset/record를 관리한다. |
| 데이터 구조 | `sr_reaction_definitions`, `sr_reaction_presets`, `sr_reaction_preset_items`, `sr_reaction_records`. |
| 관리자 화면 | 리액션 정의, Preset 관리, 레코드 점검. |
| 사용자 화면 | 대상 모듈의 widget에서 리액션 버튼으로 노출된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 로그인, CSRF, rate limit, 대상 계약 resolve, 작성자 본인 제한, 단일 선택 unique 정책을 적용한다. |
| 다른 모듈 연동 | `reaction-targets.php`, `notification-events.php`, privacy export/cleanup. |
| 설정값 | 기본 preset key, visible default/hard cap, write window/account limit. |
| 운영 주의사항 | disabled key 정리는 삭제/병합/보존 정책에 따라 기존 집계가 바뀐다. |
| 테스트 관점 | `php .tools/bin/check-reaction-runtime.php`, target별 widget 수동 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 리액션 정의 | 저장/삭제 | 리액션 관리 권한 | key, label, icon, color, 상태 | 권한/CSRF와 key 형식, label, icon type, 업로드 이미지 MIME/크기, color, 상태를 검증한다. 정의를 저장하고 disabled key의 기존 기록 영향 수를 계산한다. | widget 후보와 preset 선택지가 갱신된다. | key 중복, icon 오류, 업로드 실패 | `sr_reaction_definitions` insert/update, 파일 저장 가능 | `modules/reaction/actions/admin-reactions.php` |
| 관리자 > Preset | 저장 | 리액션 관리 권한 | preset key, 표시 key 목록, 상태 | key 형식, 공개 표시 개수, 정의 존재/활성, 중복을 검증한다. preset과 item 목록을 교체 저장한다. | 대상 모듈 설정에서 새 preset을 사용할 수 있다. | 최대 개수 초과, 정의 없음 | `sr_reaction_presets`, `sr_reaction_preset_items` insert/update/delete | `modules/reaction/actions/admin-reactions.php` |
| 공개 widget | 리액션 쓰기/취소/변경 | 로그인, 대상 반응 가능 | target module/type/id, reaction key | CSRF, 로그인, rate limit, target 계약 resolve, 공개/반응 가능 여부, 작성자 본인 여부를 확인한다. 같은 key 재클릭은 취소, 다른 key는 기존 record를 변경하고 알림 이벤트를 생성한다. | count와 내 선택이 갱신되고 대상 작성자에게 알림 가능 | 권한 없음, target 없음, 본인 제한, rate limit | `sr_reaction_records` insert/update/delete, 알림 가능 | `modules/reaction/actions/write.php`, `modules/reaction/helpers.php` |
| 관리자 > 레코드 점검 | 삭제/병합/보존 | 리액션 관리 권한 | disabled key, target filter, cleanup policy | 영향 record를 조회하고 policy에 따라 삭제 또는 다른 key로 병합한다. target 상태 점검 결과를 함께 표시하고 감사 로그를 남긴다. | 집계와 record 목록이 정리된다. | 병합 대상 없음, 권한 부족 | `sr_reaction_records` update/delete | `modules/reaction/actions/admin-reactions.php` |

## reward

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 회원 적립금 잔액, 거래, 수동 조정, 출금 신청을 관리한다. |
| 기본 동작 기전 | 회원 `/account/rewards`, 관리자 `/admin/rewards/*` route를 제공하고 `asset_ledger` 기반 원장 거래를 사용한다. |
| 데이터 구조 | `sr_reward_balances`, `sr_reward_transactions`, `sr_reward_withdrawal_requests`. |
| 관리자 화면 | 잔액, 거래, 출금 신청, 환경설정, 수동 조정, 참조 검색. |
| 사용자 화면 | 내 적립금 잔액/거래, 출금 신청/취소. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 로그인/CSRF, 관리자 권한, 조정 한도, 출금 가능 금액/대상 그룹, 회수 가능액 검증. |
| 다른 모듈 연동 | asset exchange, member assets, member withdrawal assets, member group references, notification, privacy export, dashboard. |
| 설정값 | 출금 신청 사용, 전체/그룹 대상, 최소/최대 금액, 조정 한도, 표시명. |
| 운영 주의사항 | 출금 완료는 원장 차감과 신청 상태 연결을 함께 기록한다. 지급 회수는 원거래별 회수 가능액을 기준으로 한다. |
| 테스트 관점 | reward reclaim, withdrawal workflow local smoke. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 회원 > 적립금 | 출금 신청/취소 | 로그인, 신청 기능 활성 | 금액, 계좌/메모 | 로그인/CSRF와 대상 그룹, 최소/최대, pending 차감 후 가용 잔액을 검증한다. 신청 row를 만들고 취소는 본인 pending 상태만 허용한다. | 신청 목록이 갱신된다. | 기능 비활성, 금액 오류, 잔액 부족 | `sr_reward_withdrawal_requests` insert/update | `modules/reward/actions/account-rewards.php` |
| 관리자 > 적립금 조정 | 지급/차감/회수 | 조정 권한 | 회원, 금액, 사유, 원거래 | 권한/CSRF, 회원, 금액, 한도, 원거래 회수 가능액을 검증한다. asset ledger로 거래/잔액을 반영하고 notification을 예약할 수 있다. | 잔액과 거래 내역이 갱신된다. | 회수 가능액 없음, 한도 초과, 잔액 부족 | `sr_reward_transactions`, `sr_reward_balances` | `modules/reward/actions/admin-rewards-adjust.php`, `modules/reward/helpers.php` |
| 관리자 > 출금 신청 | 완료/반려/일괄 처리 | 출금 관리 권한 | request_id, 상태, 관리자 메모 | 대상 신청 상태와 가용 잔액을 재확인한다. 완료는 차감 거래를 만들고 신청에 처리자/거래 ID를 연결한다. 반려는 메모와 상태만 갱신한다. | 회원 화면과 관리자 목록 상태가 바뀐다. | 이미 처리됨, 잔액 부족, terminal note 누락 | withdrawal_requests update, transactions/balances update | `modules/reward/actions/admin-rewards-withdrawal-requests.php` |
| 관리자 > 환경설정 | 저장 | 설정 권한 | 출금 정책, 대상 그룹, 한도 | 권한/CSRF와 그룹 key, 금액 범위, boolean을 검증한다. 대상 그룹 참조 health를 확인하고 저장한다. | 회원 출금 신청 노출과 검증 정책이 바뀐다. | 잘못된 그룹/금액 | `sr_module_settings` update | `modules/reward/actions/admin-rewards-settings.php` |

## seo

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | SEO 기본 태그, OG 이미지, robots.txt, sitemap.xml 출력을 제공한다. |
| 기본 동작 기전 | `/admin/seo`에서 설정을 저장하고 공개 `/robots.txt`, `/sitemap.xml`, `/seo/image`가 출력 route로 동작한다. |
| 데이터 구조 | 별도 설치 테이블 없음. 설정은 `sr_module_settings`와 site setting reference를 사용한다. |
| 관리자 화면 | SEO 설정. |
| 사용자 화면 | 공개 head meta tag, robots.txt, sitemap.xml, OG image. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장은 권한/CSRF, OG 이미지 업로드 검증, URL/path 안전성 검증을 수행한다. |
| 다른 모듈 연동 | `sitemap.php` 계약을 소비하고 `site-setting-references.php`를 제공한다. |
| 설정값 | `title_suffix`, `default_description`, `default_og_image`, `sitemap_include_home`, `robots_disallow_paths`. |
| 운영 주의사항 | domain별 canonical/base URL 설정과 robots 차단 경로가 검색 노출에 직접 영향을 준다. |
| 테스트 관점 | sitemap/robots HTTP smoke, OG image 제공 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > SEO | 저장 | SEO 설정 권한 | 제목 suffix, 설명, OG 이미지, robots path, sitemap 옵션 | 권한/CSRF를 확인하고 텍스트 길이, robots path 줄 목록, boolean, OG 이미지 MIME/크기/storage key를 검증한다. 설정을 저장하고 업로드 이미지를 storage에 둔다. | 공개 meta/robots/sitemap 출력이 바뀐다. | 잘못된 path/이미지, 권한 부족 | `sr_module_settings` update, 파일 저장 가능 | `modules/seo/actions/admin-settings.php` |
| 공개 > robots/sitemap | 출력 | 모듈 활성 | 없음 | 설정값과 활성 모듈의 sitemap 계약을 조회한다. URL을 절대 경로로 정규화하고 중복을 제거한 XML 또는 robots text를 출력한다. | 검색 엔진용 파일이 응답된다. | 계약 오류, URL 정규화 실패 | 조회만 수행 | `modules/seo/actions/robots.php`, `modules/seo/actions/sitemap.php` |
| 공개 > SEO image | 이미지 출력 | default OG image 존재 | image ref | storage reference를 검증해 안전한 파일 헤더로 전송한다. | OG 이미지 URL이 표시 가능해진다. | 파일 없음, MIME 오류 | 조회만 수행 | `modules/seo/actions/image.php` |

## site_menu

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 사이트 공통 내비게이션 메뉴와 메뉴 항목을 관리하고 output slot으로 렌더링한다. |
| 기본 동작 기전 | `/admin/site-menus`에서 메뉴와 항목을 저장하고, public layout이 slot key로 메뉴를 렌더링한다. |
| 데이터 구조 | `sr_site_menus`, `sr_site_menu_items`. |
| 관리자 화면 | 사이트 메뉴 관리. |
| 사용자 화면 | header/secondary 등 layout slot에 메뉴가 표시된다. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 관리자 저장은 권한/CSRF, key 형식, URL 안전성, parent depth/cycle, icon allowlist를 검증한다. |
| 다른 모듈 연동 | `menu-links.php` 후보, `output-slots.php`, admin icon helper. |
| 설정값 | 메뉴/항목 row의 상태, 정렬, slot 연결. |
| 운영 주의사항 | 잘못된 외부 URL이나 parent 구조는 공개 내비게이션을 깨뜨릴 수 있다. 기본 header seed가 있다. |
| 테스트 관점 | `php .tools/bin/check-site-menu-seed-order.php`, 공개 메뉴 렌더링 수동 확인. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 사이트 메뉴 | 메뉴 저장 | 메뉴 관리 권한 | menu key, label, status | 권한/CSRF와 key 형식/중복, label, status를 검증한다. 메뉴 row를 생성/수정하고 runtime cache를 비운다. | layout slot에서 선택 가능한 메뉴가 갱신된다. | key 중복/형식 오류 | `sr_site_menus` insert/update | `modules/site_menu/actions/admin-site-menus.php` |
| 관리자 > 사이트 메뉴 | 항목 저장/정렬/삭제 | 메뉴 관리 권한 | item key, parent, label, URL, icon, sort | parent depth와 cycle, URL 안전성, link suggestion 참조, icon allowlist, 상태와 정렬을 검증한다. 항목을 저장하고 하위 항목 삭제/이동 영향을 계산한다. | 공개 메뉴 트리가 갱신된다. | parent 오류, 안전하지 않은 URL, icon 오류 | `sr_site_menu_items` insert/update/delete | `modules/site_menu/actions/admin-site-menus.php`, `modules/site_menu/helpers.php` |
| 공개 layout | 메뉴 렌더링 | 메뉴 활성 | slot/menu key | 활성 메뉴와 항목 tree를 조회하고 현재 URL과 community board matching을 계산한다. escape된 label/icon/link를 출력한다. | 공개 header/slot에 메뉴가 표시된다. | 메뉴 없음 | 조회/cache 사용 | `modules/site_menu/helpers.php`, `modules/site_menu/output-slots.php` |

## survey

| 구분 | 작성 내용 |
| --- | --- |
| 모듈 개요 | 설문 작성, 문항/선택지, 공개 응답, 응답 품질 관리, 통계, CSV 내보내기, 보상, 댓글을 제공한다. |
| 기본 동작 기전 | 공개 `/survey`, `/survey/*`, 관리자 `/admin/surveys` 하위 route가 있다. 응답 제출과 보상 grant를 저장한다. |
| 데이터 구조 | `sr_survey_forms`, `sr_survey_comments`, `sr_survey_questions`, `sr_survey_choices`, `sr_survey_responses`, `sr_survey_response_answers`, `sr_survey_reward_policies`, `sr_survey_reward_grants`. |
| 관리자 화면 | 설문 관리, 응답 관리, 통계, 댓글 관리, 환경설정, 매뉴얼, export. |
| 사용자 화면 | 설문 홈, 설문 상세/응답 제출, 댓글. |
| 버튼/명령어 동작 | 아래 상세 표에서 화면별 버튼/명령어의 노출 조건, 입력값, 수행 작업, 성공/실패 결과, 데이터 변경, 관련 파일을 정리한다. |
| 권한/보안 | 공개 기간, 로그인/익명/동의, 응답 제한, 그룹 조건, CSRF, 댓글 depth/secret, CSV export 권한, 보상 중복 지급. |
| 다른 모듈 연동 | menu link, homepage candidate, dashboard, layout, privacy export/cleanup, coupon/member group references, sitemap, embed target, reaction target, member asset, notification. |
| 설정값 | skin, 기본 상태, 기본 로그인/동의/응답 제한, reaction preset, 공개 목록 수. |
| 운영 주의사항 | 익명 설문도 운영 품질 관리와 개인정보 처리 기준을 구분해 봐야 한다. export 파일은 민감 데이터 취급. |
| 테스트 관점 | `php .tools/bin/check-survey-consistency.php`, response/statistics/export/reward runtime checks. |

| 위치 | 버튼/명령어 | 노출 조건 | 입력값 | 수행 작업 | 성공 결과 | 실패/검증 메시지 | 데이터 변경 | 관련 파일 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 관리자 > 설문 관리 | 저장/삭제 | 설문 관리 권한 | key, 제목, 상태, 기간, 문항/선택지, 응답 제한, 보상 | 권한/CSRF와 key, 상태, 기간, 문항 유형별 필수값, 선택지, 익명/로그인/동의, 그룹, 보상 provider를 검증한다. form과 하위 질문/선택지/보상 정책을 교체 저장한다. | 공개 설문 목록과 응답 정책이 갱신된다. | key 중복, 문항 오류, 보상 대상 없음 | survey tables insert/update/delete | `modules/survey/actions/admin-surveys.php` |
| 공개 > 설문 응답 | 제출 | 공개/응답 가능 | 문항 답변, 동의, return_to | 공개 기간, 로그인/익명, 동의 필수, 그룹 조건, 응답 제한, CSRF를 확인한다. 문항 유형별 답변을 검증하고 response/answers를 저장한다. 보상 dedupe를 확인해 자산/쿠폰 grant를 만든다. | 완료 화면이 표시되고 보상/알림이 생성될 수 있다. | 기간 외, 중복 응답, 필수 답변 누락, 보상 실패 | responses/answers/reward_grants insert | `modules/survey/actions/view.php`, `modules/survey/helpers.php` |
| 관리자 > 응답 관리 | 품질 상태 저장 | 응답 관리 권한 | response_id, quality_status, note | 권한/CSRF, 응답 존재, 품질 상태 allowlist, 메모를 검증한다. 응답의 분석 포함/제외 판단을 저장한다. | 응답 목록과 통계/export 필터 결과가 바뀐다. | 상태 오류, 대상 없음 | `sr_survey_responses` update | `modules/survey/actions/admin-responses.php` |
| 관리자 > 통계/export | 조회/CSV | 통계/export 권한 | survey_id, 필터, export mode | 설문과 응답 필터를 확인한다. 통계는 선택형/숫자형 요약을 계산하고, export는 raw/analysis/codebook CSV를 생성해 다운로드 헤더로 전송한다. | 화면 통계 또는 CSV 파일이 제공된다. | 대상 없음, 권한 부족 | 조회만 수행 | `modules/survey/actions/admin-statistics.php`, `admin-export.php` |
| 공개/관리자 > 댓글 | 작성/수정/삭제/상태 | 댓글 사용, 권한 충족 | 댓글 본문, parent_id, secret/status | CSRF, 로그인, 설문 상태, 댓글 사용 여부, depth, secret visibility를 검증한다. 멘션 알림을 만들고 관리자 상태 변경은 감사 로그를 남긴다. | 댓글 목록과 알림이 갱신된다. | 권한 없음, 부모 오류, 본문 누락 | `sr_survey_comments` insert/update | `modules/survey/actions/comment.php`, `admin-comments.php` |

## PM 검토 필요 항목

| 항목 | 이유 | 확인 파일 |
| --- | --- | --- |
| 자산 계열 관리자 POST intent 세부 명칭 | `point`, `reward`, `deposit`은 공통 패턴이 명확하지만 화면 버튼의 label과 intent 전체 목록은 view/action 전체 정밀 대조가 필요하다. | `modules/*/actions/admin-*-*.php`, `modules/*/views/admin-*.php` |
| `asset_exchange` 로그 화면 POST 동작 | route에는 POST가 있으나 로그 상태 변경/재처리 여부는 추가 세부 검토가 필요하다. | `modules/asset_exchange/actions/admin-asset-exchange-logs.php` |
| plugin 계열 설정 저장 범위 | `antispam_captcha_providers`는 자체 화면이 없고 antispam 계약으로만 동작한다. provider secret 저장 위치는 운영 정책과 함께 재확인한다. | `modules/antispam_captcha_providers/antispam-providers.php`, `modules/antispam/actions/admin-settings.php` |
| SEO/antispam/privacy 별도 DB 없음 | 설치 테이블 없이 module/site settings와 계약으로 동작한다. PM 검토 시 "데이터 없음"이 아니라 "공통 설정 저장소 사용"으로 이해해야 한다. | 각 `install.sql`, `module.php` |
| Wiki 상세 표 중복 관리 | 이 문서가 PM용 단일 기능정의서이고, Wiki의 DB/관리자 항목 문서는 상세 필드 명세 역할을 유지한다. 중복 내용을 복사하면 불일치 위험이 있다. | `../saanraan.wiki/*.md` |

## 관련 Wiki

- [DB 명세서](../saanraan.wiki/DB-명세서.md): 테이블과 주요 필드 상세.
- [관리자 화면별 항목 설명서](../saanraan.wiki/관리자-화면별-항목-설명서.md): 관리자 입력 필드와 화면별 항목.
- [모듈 개발 가이드](../saanraan.wiki/모듈-개발-가이드.md): route/action/view/contract 작성 기준.
- [요청 처리 흐름](../saanraan.wiki/요청-처리-흐름.md): public/admin entry와 paths 처리 흐름.
- [보안 개인정보 개발 가이드](../saanraan.wiki/보안-개인정보-개발-가이드.md): CSRF, 출력 escape, 개인정보 export/cleanup 기준.
- [테스트와 검증](../saanraan.wiki/테스트와-검증.md): `check.php`, HTTP smoke, 모듈별 점검 명령 기준.
