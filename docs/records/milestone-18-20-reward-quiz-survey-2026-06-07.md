# 마일스톤 18-20 보상·퀴즈·설문 구현 기록

## 처리 순서

1. #250 공통 보상 부정 수급 기준 초안을 `docs/plans/reward-abuse-common-standards.md`에 정리했다.
2. #240 퀴즈의 `survey` 모드를 신규 선택지에서 제거하고, 기존 DB 값은 `modules/quiz/updates/2026.06.007.sql`에서 `scored`로 정리한다.
3. #249 퀴즈 보상 dedupe key를 회원/퀴즈/정책/공급자/모듈/scope 기준으로 확장하고, 중복 제출은 새 보상을 만들지 않도록 정리했다.
4. #248 퀴즈 `sitemap.php` 계약을 추가해 공개 중인 퀴즈만 sitemap에 노출한다.
5. #246 설문 MVP/의존성 지도는 `docs/plans/survey-module-mvp-map.md`에 정리했다.
6. #226 설문 기본 모듈을 추가했다.
7. #251/#252 기존 쿠폰·자산·콘텐츠·커뮤니티 확장 점검은 아래 기준으로 확인했다.

## 설문 MVP

- `survey` 모듈을 선택 설치 가능한 공식 모듈로 추가했다.
- `sr_survey_forms`, `sr_survey_questions`, `sr_survey_choices`, `sr_survey_responses`, `sr_survey_response_answers`, `sr_survey_reward_policies`, `sr_survey_reward_grants`를 추가했다.
- 관리자 `/admin/surveys`에서 설문 기본 정보, 공개 기간, 보상 정책, 문항을 저장한다.
- 공개 `/survey`와 `/survey/{survey_key}`에서 공개 설문 목록과 회원 응답 제출을 처리한다.
- 응답 보상은 `ledger_asset`과 `coupon`을 지원하고, 지급 직전 provider 상태를 다시 확인한다.

## 기존 모듈 확장 점검

- 쿠폰: `sr_coupon_redemptions.dedupe_key`는 이미 사용 중이며, 공통 기준과 같이 중복 사용 방지 key로 남긴다. 쿠폰 발급 자체의 provider 재검증은 보상을 호출하는 퀴즈/설문 grant 쪽에서 실행 직전에 수행한다.
- 자산: `point`, `reward`, `deposit`은 `member-assets.php` 계약으로 거래 함수와 거래 유형을 노출한다. 퀴즈/설문은 지급 직전에 계약과 함수 존재를 다시 확인한다.
- 콘텐츠: `sr_content_author_reward_logs.dedupe_key`는 제출본별 1회 보상에 쓰이고, 상태는 `pending`, `granted`, `failed`다. 공통 용어와 완전히 같지는 않지만 지급 로그와 재시도 점검 대상으로 유지할 수 있다.
- 커뮤니티: `sr_community_publisher_reward_logs.dedupe_key`는 첨부 다운로드 차감 후 게시자 리워드 중복 방지에 쓰이고, 상태는 `pending`, `granted`, `failed`, `held`, `reversed`, `cancelled`다. 보류/회수 상태가 있어 공통 grant 상태보다 넓다.

## 검증

- `php .tools/bin/check.php`: 통과
- 변경 PHP 파일 `php -l`: 통과
- HTTP 스모크: 로컬 `config/config.php` 권한 거부로 전체 동적 라우트가 500을 반환해 완료하지 못했다. 서버 로그의 공통 원인은 `include(/home/lab/www/saanraan/config/config.php): Failed to open stream: Permission denied`다.
