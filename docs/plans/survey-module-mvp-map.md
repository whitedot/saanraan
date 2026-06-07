# 설문 모듈 MVP/의존성 지도

## MVP 범위

- 공개 설문 목록 `/survey`
- 공개 설문 상세/응답 제출 `/survey/{survey_key}`
- 관리자 설문 목록/생성/수정/삭제 `/admin/surveys`
- 문항 유형: 단일 선택, 복수 선택, 주관식
- 문항 품질 옵션: 최소/최대 선택 수, 숫자/척도 범위, 기타 선택지, 무응답 선택지, 분석 메모
- 응답 저장: `sr_survey_responses`, `sr_survey_response_answers`
- 기본 응답 제한: 회원별 설문 1회
- 참여 대상 제한: 로그인 필요 설문에서 활성 회원 그룹 지정
- 조사 설계 메타데이터: 프로젝트 개요, 실사 방식, 표본틀, 쿼터, 분석/가중치, 방법론 공표, 윤리/철회/민감정보, 외부 채널 기준
- 공개 전 점검: QA 상태, 설문지 버전, 관리자 미리보기, 테스트 응답
- 선택 보상: `ledger_asset`, `coupon`
- 보상 중복 방지: `sr_survey_reward_grants.dedupe_key`
- sitemap, 개인정보 export/cleanup, 쿠폰 정의 참조, 회원 그룹 참조 계약
- 통계와 CSV: 원본, 분석용 row-per-answer, 코드북 CSV

## 의존성

- 필수 모듈: `member`, `admin`
- 선택 보상 공급자: `point`, `reward`, `deposit` 같은 `member-assets.php` 제공 모듈
- 선택 쿠폰 보상: `coupon`
- 선택 sitemap 노출: `seo`

## 비범위 또는 후속

- 조건부 문항 분기
- 외부 설문 공급자 callback
- 응답 수정/철회 셀프서비스
- 무작위 문항/선택지 순서
- 패널 초대 토큰의 발급·검증 자동화

## 공통 보상 기준 적용

설문 보상 로그는 퀴즈와 같은 이름의 `reward_provider`, `reward_module`, `reward_code`, `dedupe_scope`, `dedupe_key`, provider reference 필드를 사용한다. `dedupe_key`는 회원 ID, 설문 ID, 정책 ID, 공급자, 모듈, 응답 scope를 포함한다. 지급 실행 직전에는 자산 거래 함수 또는 쿠폰 정의 활성 상태를 다시 확인한다.

관리자 미리보기와 테스트 제출은 `sr_survey_responses.is_test = 1`로 저장하고 실제 보상을 지급하지 않는다. 통계와 분석 CSV는 테스트 응답과 제외 응답을 기본 집계에서 뺀다.
