# 이슈 #42 전체 정밀 리뷰 기록

점검일: 2026-05-28

## 점검 범위

- 전체 모듈: `admin`, `asset_exchange`, `banner`, `ckeditor`, `community`, `content`, `coupon`, `deposit`, `logo_manager`, `member`, `notification`, `point`, `popup_layer`, `privacy`, `reward`, `seo`, `site_menu`
- 코어 요청 흐름, 모듈 `paths.php` 명시 include 흐름, 관리자 메뉴 path 매핑
- 관리자 action의 로그인, 권한, CSRF, redirect/action 결과 규칙
- 모듈 `module.php` 계약 선언과 실제 계약 파일 정합성
- 회원 자산 모듈의 원장 처리, 자산 환전 transaction, 다운로드/첨부 과금 순서, 로그 저장값
- 관리자 key 입력, 조건부 필수 입력, 숫자 입력의 브라우저/서버 검증 정합성
- 개인정보 export/cleanup 계약 소비 흐름과 모듈 enable/loadable 기준
- DB table prefix와 모듈 install/update 버전 정합성
- repository docs, smoke/manual 검증 항목 정합성

## 발견사항

### Medium: 자산 환전 로그 입금액이 수수료 차감 전 금액으로 저장됨

- 위치: `modules/asset_exchange/helpers.php`
- 영향: 환전 실행에서 입금 원장 `exchange_in`을 만든 뒤 수수료가 있으면 `exchange_fee`로 다시 차감하지만, `sr_asset_exchange_logs.deposit_amount`에는 수수료 차감 전 입금액이 저장되어 관리자/회원 환전 로그와 개인정보 export의 입금액이 실제 최종 증가액보다 크게 보일 수 있었다.
- 수정: 신규 환전 로그의 `deposit_amount`를 `quote['deposit_amount']`로 저장하도록 변경했다.
- 보정: `modules/asset_exchange/updates/2026.05.002.sql`에서 기존 성공 로그 중 수수료가 있는 행을 `deposit_amount - fee_amount`로 보정하고 모듈 버전을 `2026.05.002`로 올렸다.
- 회귀 방지: `.tools/bin/check-asset-exchange-logs.php`를 추가해 통합 점검에서 로그 저장값, 보정 업데이트, smoke 문서 기준을 확인한다.

### High: 유료 파일/첨부 다운로드가 전달 준비 전 자산을 차감할 수 있음

- 위치: `modules/content/actions/download.php`, `modules/community/actions/attachment.php`
- 영향: 유료 콘텐츠 파일 다운로드와 커뮤니티 유료 첨부에서 자산 차감/접근권 처리가 S3 서명 URL 생성 또는 로컬 파일 경로 확인보다 먼저 실행될 수 있었다. 저장소 파일 누락, S3 서명 실패, 로컬 파일 경로 오류가 발생하면 회원은 자산이 차감됐는데 실제 파일을 받지 못하는 상태가 될 수 있었다.
- 수정: MIME/storage key, `sr_storage_head`, 파일 크기/checksum 검증 뒤 S3 signed URL 또는 로컬 파일 경로를 먼저 준비하고, 준비가 끝난 경우에만 자산 차감과 접근권 처리를 실행하도록 순서를 바꿨다. 리다이렉트/스트리밍은 차감 성공 후에만 진행한다.
- 회귀 방지: `.tools/bin/check-paid-download-delivery.php`를 추가해 콘텐츠 다운로드와 커뮤니티 첨부 모두 전달 준비가 차감보다 앞서고, 차감이 실제 전달보다 앞서는 순서를 확인한다.

### Medium: 쿠폰 정의의 관리자 key/숫자 서버 검증이 화면 규칙과 맞지 않음

- 위치: `modules/coupon/helpers.php`, `modules/coupon/actions/admin-coupons.php`, `modules/coupon/views/admin-coupons.php`
- 영향: 쿠폰 키 입력은 관리자 key 입력으로 표시되지만 서버가 숫자로 시작하는 key를 허용할 수 있었고, `max_uses_per_issue`는 `(int)` 변환 때문에 `abc` 같은 값이 `1`로 저장될 수 있었다. 중복 키는 DB unique 오류에 의존해 운영자에게 명확한 검증 메시지를 주지 못했다.
- 수정: `sr_coupon_key_is_valid()`를 추가해 영문 소문자 시작, 소문자/숫자/`_` 2-60자 규칙을 서버에서 강제했다. 사용 가능 횟수는 원문 POST 값을 검증해 1-1000 정수만 허용하고, 중복 쿠폰 키는 insert 전에 명확히 거부한다. 화면 pattern/help와 서버 검증도 같은 기준으로 맞췄다.
- 보정: `modules/coupon/updates/2026.05.003.sql`을 추가하고 모듈 버전을 `2026.05.003`으로 올렸다.
- 회귀 방지: `.tools/bin/check-coupon-admin-validation.php`를 추가해 key 패턴, 원문 숫자 검증, 중복 key 확인, 버전 업데이트를 통합 점검에서 확인한다.

## 확인 결과

- 전체 모듈의 `module.php` 계약 선언과 실제 계약 파일 제공 여부에서 불일치는 발견하지 못했다.
- 전체 모듈의 `paths.php` route 중복은 발견하지 못했다.
- 설치/update 파일 중 코드 버전보다 최신인 update 파일은 발견하지 못했다.
- PHP short echo/tag 사용은 XML 문자열 false positive 외 발견하지 못했다.
- 관리자 action의 기본 로그인/권한/CSRF 패턴은 기존 `.tools/bin/check-admin-action-security.php` 기준을 통과했다.

## 문서 반영

- `docs/smoke-test.md`: 환전 로그 `deposit_amount`가 수수료 차감 후 최종 증가액임을 명시했다.
- `docs/smoke-test.md`: 유료 다운로드/첨부의 전달 준비 실패 시 무차감 기준, 쿠폰 key/사용 가능 횟수 서버 검증 기준을 추가했다.
- `manual-check.md`: 환전 확정 수동 점검 항목에 최종 증가액 표시 기준을 추가하고, 유료 다운로드 전달 준비 실패 및 쿠폰 서버 검증 항목을 추가했다.
- 저장소 DB 문서: 환전 로그 `deposit_amount` 의미를 최종 증가액으로 갱신했다.
- 저장소 테스트/검증 문서와 관리자 화면 항목 문서: 유료 다운로드/첨부 차감 순서와 쿠폰 관리자 검증 기준을 추가했다.

## 검증

```text
.tools/bin/check
saanraan checks completed.
```
