# 이슈 326 공통 리액션 정책 결정

## 결정

- 반응/추천은 커뮤니티 전용 기능으로 만들지 않고, 필요해질 때 공식 선택 모듈 `reaction`으로 도입한다.
- 1차 구현 전제는 원장 테이블 `sr_reaction_records`를 권위 소스로 두는 구조다. 집계 캐시 `sr_reaction_counts`와 관리자 CRUD형 정의 화면은 성능 또는 운영 요구가 확인된 뒤 추가한다.
- 1차 target allowlist는 `content/content`와 `community/post`로 좁힌다. `quiz/quiz_set`, `survey/survey_form`, 댓글, 시리즈 반응은 별도 제품 판단과 정책 이슈가 닫힌 뒤 확장한다.
- 익명 반응 쓰기는 허용하지 않는다. `account_id`는 반응 원장의 필수값이며, 비로그인 사용자는 공개 가능한 target의 집계만 읽을 수 있고 내 반응 상태는 제공하지 않는다.
- 회원 탈퇴 또는 익명화 시 해당 계정의 reaction record는 삭제한다. 통계 보존 요구가 생기면 계정 연결이 없는 별도 aggregate snapshot을 새로 설계한다.
- reaction은 스크랩, 콘텐츠 완료, 퀴즈 시도, 설문 응답, 보상 grant를 대신하지 않는다. 반응 수를 보상 조건, 응답 제한, 완료 판정, SEO/랭킹 정책에 바로 연결하지 않는다.

## 모듈 경계

- `reaction` 모듈은 반응 정의, 계정별 반응 원장, 정책 검증, privacy export/cleanup, 관리자 점검 화면을 소유한다.
- 콘텐츠와 커뮤니티는 target 검증과 공개 UI 삽입 위치를 소유한다.
- 공통 모듈은 `sr_content_items`, `sr_community_posts` 같은 도메인 테이블을 직접 조회하지 않는다.
- notification, point, reward, deposit, coupon 모듈에는 hard dependency를 두지 않는다. 알림과 보상 연결은 기본 no-op이며 후속 opt-in 기능으로만 다룬다.

## target 계약

반응 대상 모듈은 `reaction-targets.php` 계약을 제공한다. 계약은 기존 `embed-manager-targets.php`와 같은 명시적 파일 로딩 패턴을 따른다.

필수 기능:

- 단건 resolve와 batch resolve
- `target_module`, `target_type`, `target_id` 검증
- target 상태 반환: `active`, `private`, `deleted`, `broken`
- viewer 기준 공개 열람 가능 여부와 반응 가능 여부
- 공개 URL과 관리자 URL 후보
- 공개 가능한 label snapshot
- target owner account id 또는 알림 수신자 후보
- 적용 가능한 preset 또는 reaction key 목록

목록 화면의 N+1 조회를 피하기 위해 batch resolve는 1차 필수 요구사항이다. 도메인 계약은 target 존재와 접근 가능성을 판단하고, reaction 모듈은 그 결과 위에서 key 적용, 취소, 변경, 중복, 본인 반응 허용 여부를 판단한다.

## 데이터 정책

1차 후보 테이블:

- `sr_reaction_definitions`: 코드 seed 또는 정의 seed로 관리되는 반응 key, label, preset, 표시 정책, exclusive group
- `sr_reaction_records`: 계정별 반응 원장

1차 unique key:

```text
(account_id, target_module, target_type, target_id, reaction_key)
```

상호 배타 반응은 unique key가 아니라 정의의 `exclusive_group`과 쓰기 transaction으로 강제한다. 같은 target/account/group의 기존 key 제거와 새 key 적용은 한 transaction 안에서 처리한다.

## 읽기/쓰기 정책

- 신규 적용/변경 write는 로그인, CSRF, write throttle, target 접근 가능 여부, target 상태 `active`를 모두 통과해야 한다.
- 취소 write는 target이 `private`, `deleted`, `broken`이어도 본인 row에 대해 허용한다.
- 공개 표시는 `private`, `deleted`, `broken` target의 집계를 숨긴다.
- 같은 key 반복 적용, 취소 반복, unique 충돌은 멱등 결과로 처리한다.
- write 응답은 최종 내 상태와 target 집계를 반환한다.
- 읽기 집계는 원장 기준 집계 또는 짧은 TTL의 read-time 캐시를 우선한다. 장기 daemon, DB trigger, 백그라운드 worker를 필수 전제로 두지 않는다.
- MySQL/MariaDB에서는 transaction 지원을 위해 InnoDB를 전제로 한다.

## 개인정보와 운영

- `reaction` 모듈은 `privacy-export.php`와 `privacy-cleanup.php`를 제공한다.
- export 1차 항목은 reaction record id, target module/type/id, reaction key, created_at, updated_at이다.
- target label 또는 URL snapshot은 계약으로 조회 가능하고 공개 가능한 경우에만 export에 포함한다.
- cleanup은 계정의 reaction record 삭제로 통일한다.
- 관리자 화면은 랭킹보다 운영 정합성 점검에 초점을 둔다. target별/계정별 조회, broken/private/deleted target 점검, 개인정보 cleanup 확인, 오용 후보 조회를 우선한다.
- 능동 moderation, 관리자 정의 CRUD, 집계 캐시 재빌드 버튼, 알림 발송은 후속 범위다.

## 보류 기준

다음 중 하나라도 구현 시점에 충족되지 않으면 도입을 보류한다.

- `reaction-targets.php` 계약과 batch resolve가 준비되지 않았다.
- privacy export/cleanup 정책과 탈퇴 삭제 경로가 구현 범위에 없다.
- idempotent write, CSRF, throttle, target 재검증이 빠져 있다.
- 반응과 스크랩/완료/응답/시도/보상/랭킹의 의미 분리가 관리자와 공개 UI에 반영되지 않았다.

## #323 기준

게시판 운영 설정 범위에는 반응/추천 설정을 포함하지 않는다. 커뮤니티 게시판별 기능 후보에서 반응/추천은 계속 제외하고, 공통 `reaction` 모듈 도입 여부가 별도로 결정된 뒤 도메인별 노출 위치만 검토한다.
