# 회원정보 마이그레이션 도구 계획

이 문서는 그누보드5, XE/Rhymix, 카페24, Shopify, 아임웹 등 외부 서비스의 회원정보를 산란으로 이전하기 위한 구현 계획이다.

문서 수명:

- 문서 정리 과정에서는 삭제하지 않고 구현 계획으로 보관한다.
- 실제 마이그레이션 도구 구현과 검증이 완료되면 이 문서는 삭제한다.
- 구현 완료 후 유지해야 하는 내용은 이 문서가 아니라 `docs/module-guide.md`, `docs/core-decisions.md`, 모듈 README 중 필요한 곳으로만 옮긴다.

## 기본 방향

회원정보 이전은 코어가 아니라 선택 모듈에서 처리한다.

권장 모듈명은 `member_migration`이다.

- `core`: DB 연결, 설정, 보안 helper, 설치/업데이트 기반만 제공한다.
- `member`: 최종 회원 저장, 계정 생성, 프로필, 동의 기록 helper를 제공한다.
- `member_migration`: 원본 읽기, 필드 매핑, 검증, 미리보기, 실행, 리포트를 소유한다.
- `admin`: 관리자 레이아웃, 메뉴 노출, 접근 권한 연결만 담당한다.

이 구조는 코어가 마이그레이션 정책을 갖지 않게 하고, 회원 모듈도 이전 도구 때문에 커지는 것을 막는다.

## 1차 구현 범위

초기 구현은 CSV 업로드 기반으로 제한한다.

포함한다:

- CSV 파일 업로드
- 원본 서비스 선택
- 원본 필드 자동 인식과 수동 매핑
- 정규화 결과 미리보기
- 필수값, 이메일 형식, 중복 회원 검증
- 실패 행 오류 표시
- 중복 처리 정책 선택
- 공유호스팅 친화적인 분할 import
- 실행 결과 리포트
- 비밀번호 재설정 전제의 회원 생성

포함하지 않는다:

- 외부 서비스 API 직접 연동
- 외부 DB 직접 접속
- 기존 비밀번호 해시 자동 이전
- 장기 실행 worker 전제의 background job
- 코어 공통 마이그레이션 framework

## 처리 흐름

```text
관리자
-> 회원정보 이전 시작
-> 원본 서비스 선택
-> CSV 업로드
-> 필드 매핑
-> 정규화와 검증
-> 미리보기
-> 중복 처리 정책 선택
-> 분할 실행
-> 결과 확인과 오류 파일 다운로드
```

바로 `sr_member_accounts`에 쓰지 않고 staging 테이블에 먼저 저장한다.

## 데이터 저장 계획

`member_migration` 모듈이 자기 테이블을 소유한다.

예상 테이블:

- `sr_member_migration_batches`
- `sr_member_migration_rows`
- `sr_member_migration_source_accounts`

`sr_member_migration_batches`에는 이전 작업 단위를 저장한다.

- source_type
- status
- filename
- total_count
- valid_count
- invalid_count
- imported_count
- skipped_count
- mapping_json
- options_json
- created_by
- created_at
- completed_at

`sr_member_migration_rows`에는 원본 행과 정규화 결과를 저장한다.

- batch_id
- row_no
- external_id
- raw_json
- normalized_json
- status
- errors_json
- action
- target_account_id
- created_at

`sr_member_migration_source_accounts`에는 재실행과 중복 방지를 위한 원본 연결 정보를 저장한다.

- source_type
- source_id
- account_id
- source_email_hash
- imported_at

원본 개인정보는 staging에 오래 남기지 않는다. import 완료 후 관리자가 결과 확인에 필요한 기간만 보관하고, 보관 정리 대상에 포함한다.

## 원본 서비스 프리셋

계약 파일을 늘리지 않고 `member_migration` 내부의 단순 프리셋 파일로 처리한다.

```text
modules/member_migration/sources/gnuboard5.php
modules/member_migration/sources/xe.php
modules/member_migration/sources/cafe24.php
modules/member_migration/sources/shopify.php
modules/member_migration/sources/imweb.php
```

각 파일은 다음 정보만 반환한다.

- 표시 이름
- 예상 CSV 컬럼
- 기본 필드 매핑
- 상태값 매핑
- 날짜 형식 후보
- 주의 문구

## 플랫폼별 매핑 후보

### 그누보드5

- `mb_id` -> login_id 후보
- `mb_email` -> email
- `mb_name` -> display_name
- `mb_nick` -> 회원 `sr_member_nicknames.nickname` (중복 비교는 lowercase lookup 값 기준이며, 중복 닉네임은 가져오기 전에 운영 정책에 맞게 정리)
- `mb_hp` -> phone
- `mb_birth` -> birth_date
- `mb_datetime` -> created_at 참고값
- `mb_today_login` -> last_login_at 참고값
- `mb_level` -> 그룹 또는 등급 후보
- `mb_leave_date`, `mb_intercept_date` -> 상태 후보

### XE/Rhymix

- 회원 기본 테이블의 user_id, email_address, user_name, nick_name 후보를 매핑한다.
- 확장 변수는 profile 후보로만 표시하고 자동 저장은 최소화한다.
- 상태값과 가입일, 최근 로그인 컬럼은 버전별 차이를 허용한다.

### 카페24

- CSV 내보내기를 1차 대상으로 한다.
- 회원 ID, 이메일, 이름, 휴대폰, 생일, 등급, 가입일, 이메일/SMS 수신 동의 후보를 매핑한다.
- API 연동은 이후 단계에서 공식 문서를 확인한 뒤 별도 어댑터로 검토한다.

### Shopify

- Customer CSV를 1차 대상으로 한다.
- email, first_name, last_name, phone, accepts_marketing, tags, state, verified_email, created_at 후보를 매핑한다.
- 비밀번호는 이전하지 않는다.

### 아임웹

- CSV 내보내기를 1차 대상으로 한다.
- 이메일, 이름, 연락처, 그룹/등급, 가입일, 수신 동의 후보를 매핑한다. 닉네임을 이전해야 하는 경우 회원 모듈의 `sr_member_nicknames`로 매핑한다.
- API 연동은 이후 단계에서 검토한다.

## 비밀번호 정책

기본 정책은 비밀번호를 이전하지 않는 것이다.

- 가져온 회원은 임시 사용 불가 비밀번호 상태로 생성한다.
- 회원에게 비밀번호 재설정 또는 이메일 인증 흐름을 안내한다.
- 원본 비밀번호 원문을 저장하지 않는다.
- 외부 서비스에서 password hash를 제공하더라도 기본 import에서는 사용하지 않는다.

레거시 비밀번호 브리지는 이후 단계로 미룬다.

레거시 브리지를 구현한다면 `member_migration`이 원본 해시를 임시 보관하고, `member` 로그인 흐름에 좁은 검증 확장점을 추가해야 한다. 첫 로그인 성공 시 산란 방식으로 재해시하고 레거시 해시는 삭제한다.

## 중복 처리 정책

기본 중복 기준은 정규화된 이메일 hash다.

관리자가 선택할 수 있는 정책:

- 기존 회원이면 건너뛰기
- 기존 회원의 프로필만 업데이트
- 기존 회원과 충돌하면 오류 처리

login_id는 원본 값이 산란 규칙에 맞을 때만 후보로 사용한다.

## 동의와 개인정보

원본 서비스의 동의 이력을 그대로 신뢰하지 않는다.

- 정확한 동의 시각, 버전, 항목이 없으면 산란의 약관/마케팅 동의로 자동 인정하지 않는다.
- 마케팅 수신 동의가 명확히 export된 경우에만 후보로 표시한다.
- 개인정보 처리 요청, 삭제 요청, 다운로드 요청 이력은 회원 import 범위에 포함하지 않는다.
- import 로그에는 이메일, 전화번호 같은 개인정보를 필요 이상으로 노출하지 않는다.

## 관리자 화면

예상 화면:

- `/admin/member-migrations`
- `/admin/member-migrations/new`
- `/admin/member-migrations/{batch_id}`

화면 구성:

- 이전 작업 목록
- 새 이전 시작
- 원본 서비스 선택
- 파일 업로드
- 필드 매핑
- 검증 결과
- 실행 진행률
- 결과 리포트

권한은 owner 또는 회원 운영 권한을 가진 관리자에게 제한한다. 실제 import 실행, staging 삭제, 오류 파일 다운로드에는 CSRF 검증과 재인증을 검토한다.

## 구현 단계

1. `member_migration` 모듈 골격과 설치 SQL을 만든다.
2. CSV 업로드와 batch/row staging을 구현한다.
3. 공통 정규화와 검증 helper를 만든다.
4. 필드 매핑 화면과 미리보기를 구현한다.
5. 회원 생성 import를 분할 실행으로 구현한다.
6. 결과 리포트와 실패 행 다운로드를 구현한다.
7. 그누보드5, XE/Rhymix, 카페24, Shopify, 아임웹 프리셋을 추가한다.
8. 보관 정리 대상에 완료된 staging 데이터를 포함한다.
9. 스모크 검증 항목을 추가한다.
10. 구현 완료 후 이 계획 문서를 삭제하고 필요한 운영 문서만 남긴다.
