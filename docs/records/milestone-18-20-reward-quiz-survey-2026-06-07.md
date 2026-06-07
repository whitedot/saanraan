# 마일스톤 18-20 보상·퀴즈·설문 구현 기록

## 처리 순서

1. #250 공통 보상 부정 수급 기준 초안을 `docs/plans/reward-abuse-common-standards.md`에 정리했다.
2. #240 퀴즈의 `survey` 모드를 신규 선택지에서 제거하고, 기존 DB 값은 `modules/quiz/updates/2026.06.007.sql`에서 `scored`로 정리한다.
3. #249 퀴즈 보상 dedupe key를 회원/퀴즈/정책/공급자/모듈/scope 기준으로 확장하고, 중복 제출은 새 보상을 만들지 않도록 정리했다.
4. #248 퀴즈 `sitemap.php` 계약을 추가해 공개 중인 퀴즈만 sitemap에 노출하고, 관리자 미리보기는 `noindex`로 처리한다.
5. #246 설문 MVP/의존성 지도는 `docs/plans/survey-module-mvp-map.md`에 정리했다.
6. #226 설문 모듈을 공개 응답, 관리자 운영, 통계, 설정, 문서, 개인정보 계약까지 확장했다.
7. #251/#252 기존 쿠폰·자산·콘텐츠·커뮤니티 확장 점검은 `.tools/bin/check-reward-abuse-standards.php`를 통합 점검에 추가해 회귀 방지 대상으로 만들었다.

## 설문 구현 범위

- `survey` 모듈을 선택 설치 가능한 공식 모듈로 추가했다.
- `sr_survey_forms`, `sr_survey_questions`, `sr_survey_choices`, `sr_survey_responses`, `sr_survey_response_answers`, `sr_survey_reward_policies`, `sr_survey_reward_grants`를 추가했다.
- 관리자 `/admin/surveys`에서 연구 목적, 대상자, 모집 방법, 프로젝트 개요, 의뢰/후원, 지역/언어, 실사 방식, 표본틀, 표본 추출, 목표 표본 수, 쿼터, 응답률 산정 기준, 분석/가중치/오차/방법론 공표, 윤리/민감정보/재연락/철회/외부 채널/초대 토큰 기준, QA 상태, 설문지 버전, 회원 그룹 제한, 공개/검색 정책, 로그인/익명 정책, 응답 제한, 보상 정책, 문항 품질 메모와 문항별 숫자/척도/선택 제한을 저장한다.
- 관리자 설문 목록은 검색어, 상태, 응답 가능 여부 필터와 복사/미리보기 동선을 제공한다.
- 공개 `/survey`와 `/survey/{survey_key}`에서 공개 설문 목록과 로그인/익명 응답 제출, 회원 그룹 제한, 동의 확인, 응답 제한, 공개 안내, `noindex` 정책을 처리한다. 일반 제출은 완료 화면으로 GET redirect하며 관리자 미리보기는 초안/기간 외 설문도 `noindex`로 열고 테스트 응답을 보상 없이 저장한다.
- 관리자 `/admin/surveys/responses`에서 raw 응답 스냅샷을 확인하고 품질 상태와 메모를 관리한다.
- 관리자 `/admin/surveys/statistics`에서 테스트/제외 응답을 뺀 선택형/숫자형 통계를 확인하고, `/admin/surveys/export`에서 원본 CSV, 분석용 CSV, 코드북 CSV를 내려받는다. CSV 수식 주입 방지를 적용한다.
- 관리자 `/admin/surveys/settings`, `/admin/surveys/manual`, `/survey/ui-kit`을 추가했다.
- 개인정보 export/cleanup 계약, 쿠폰 정의 읽기 참조 계약, 회원 그룹 읽기 참조 계약, 공개 레이아웃 계약, 초기화면 후보 계약을 추가했다.
- 응답 보상은 `ledger_asset`과 `coupon`을 지원하고, 지급 직전 provider 상태를 다시 확인한다.
- 설문 sitemap은 공개 목록 노출, 로그인 필요 여부, robots 정책을 반영한다.

## 기존 모듈 확장 점검

- 쿠폰: `sr_coupon_redemptions.dedupe_key`는 이미 사용 중이며, 공통 기준과 같이 중복 사용 방지 key로 남긴다. 쿠폰 발급 자체의 provider 재검증은 보상을 호출하는 퀴즈/설문 grant 쪽에서 실행 직전에 수행한다.
- 자산: `point`, `reward`, `deposit`은 `member-assets.php` 계약으로 거래 함수와 거래 유형을 노출한다. 퀴즈/설문은 지급 직전에 계약과 함수 존재를 다시 확인한다.
- 콘텐츠: `sr_content_author_reward_logs.dedupe_key`는 제출본별 1회 보상에 쓰이고, 상태는 `pending`, `granted`, `failed`다. 공통 용어와 완전히 같지는 않지만 지급 로그와 재시도 점검 대상으로 유지할 수 있다.
- 커뮤니티: `sr_community_publisher_reward_logs.dedupe_key`는 첨부 다운로드 차감 후 게시자 리워드 중복 방지에 쓰이고, 상태는 `pending`, `granted`, `failed`, `held`, `reversed`, `cancelled`다. 보류/회수 상태가 있어 공통 grant 상태보다 넓다.
- 통합 점검: `.tools/bin/check-reward-abuse-standards.php`가 퀴즈/설문 grant 로그, 쿠폰 redemption dedupe, 포인트 환불 누적 초과 방지, 적립금/예치금 신청 재검증, 자산 환전 중복 실행 잠금, 콘텐츠/커뮤니티 접근권·자산 로그·확인 POST·placeholder 기준을 확인한다.

## 검증

- `php .tools/bin/check.php`: 통과. 통합 점검 안에 `check-reward-abuse-standards.php`가 포함된다.
- 변경 PHP 파일 `php -l`: 통과
- HTTP 스모크: 로컬 `config/config.php` 권한 거부로 전체 동적 라우트가 500을 반환해 완료하지 못했다. 서버 로그의 공통 원인은 `include(/home/lab/www/saanraan/config/config.php): Failed to open stream: Permission denied`다.
