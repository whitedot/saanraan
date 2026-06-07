# 설문 모듈 MVP/의존성 지도

## MVP 범위

- 공개 설문 목록 `/survey`
- 공개 설문 상세/응답 제출 `/survey/{survey_key}`
- 관리자 설문 목록/생성/수정/삭제 `/admin/surveys`
- 문항 유형: 단일 선택, 복수 선택, 주관식
- 응답 저장: `sr_survey_responses`, `sr_survey_response_answers`
- 기본 응답 제한: 회원별 설문 1회
- 선택 보상: `ledger_asset`, `coupon`
- 보상 중복 방지: `sr_survey_reward_grants.dedupe_key`
- sitemap 계약과 개인정보 export 계약

## 의존성

- 필수 모듈: `member`, `admin`
- 선택 보상 공급자: `point`, `reward`, `deposit` 같은 `member-assets.php` 제공 모듈
- 선택 쿠폰 보상: `coupon`
- 선택 sitemap 노출: `seo`

## 비범위

- 설문 통계 대시보드
- 응답 CSV 다운로드
- 익명 설문
- 조건부 문항 분기
- 외부 설문 공급자 callback
- 응답 수정/철회

## 공통 보상 기준 적용

설문 보상 로그는 퀴즈와 같은 이름의 `reward_provider`, `reward_module`, `reward_code`, `dedupe_scope`, `dedupe_key`, provider reference 필드를 사용한다. `dedupe_key`는 회원 ID, 설문 ID, 정책 ID, 공급자, 모듈, 응답 scope를 포함한다. 지급 실행 직전에는 자산 거래 함수 또는 쿠폰 정의 활성 상태를 다시 확인한다.
