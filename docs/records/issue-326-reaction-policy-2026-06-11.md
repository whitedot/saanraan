# 이슈 326 공통 리액션 정책 결정

## 결정

- 반응/추천은 커뮤니티 전용 기능으로 만들지 않고 공식 선택 모듈 `reaction`으로 도입한다.
- 1차 target allowlist는 `content/content`, `content/comment`, `community/post`, `community/comment`, `quiz/quiz_set`, `quiz/comment`, `survey/survey_form`, `survey/comment`로 확정한다. `community/series`와 각 모듈의 추가 target은 후속 확장으로 둔다.
- 1차 공개 리액션은 target/account 기준 단일 선택이다. 한 회원은 같은 target에 하나의 reaction key만 가질 수 있고, 같은 key 재클릭은 취소, 다른 key 클릭은 같은 row의 `reaction_key` 교체로 처리한다.
- 익명 반응 쓰기는 허용하지 않는다. `account_id`는 반응 원장의 필수값이며, 비로그인 사용자는 공개 가능한 target의 집계만 읽을 수 있고 내 반응 상태는 `N/A`로 취급한다.
- 작성자 본인은 자신이 작성한 글/댓글/콘텐츠/퀴즈/설문 target에 리액션할 수 없다. 관리자도 작성자 본인인 target에는 신규 적용/변경 write를 할 수 없다.
- 내 게시글이나 댓글에 누군가 리액션하면 target 작성자/소유자에게 알림을 보내되, notification 모듈은 hard dependency가 아니다. 알림 생성 경로에서도 actor와 recipient가 같으면 no-op 처리한다.
- 쓰기 자체가 불가능한 target, 공개/권한 정책상 viewer가 반응할 수 없는 target, target 계약에서 알림 제외로 표시한 target은 알림 생성에서도 제외한다.
- 회원 탈퇴 또는 익명화 시 해당 계정의 reaction record는 삭제한다. 통계 보존 요구가 생기면 계정 연결이 없는 별도 aggregate snapshot을 새로 설계한다.
- reaction은 스크랩, 콘텐츠 완료, 퀴즈 시도, 설문 응답, 보상 grant를 대신하지 않는다. 반응 수를 보상 조건, 응답 제한, 완료 판정, SEO/랭킹 정책에 바로 연결하지 않는다.

## 모듈 경계

- `reaction` 모듈은 반응 정의, preset, 계정별 반응 원장, 정책 검증, privacy export/cleanup, 관리자 정의 화면과 운영 점검 화면을 소유한다.
- 콘텐츠, 커뮤니티, 퀴즈, 설문은 target 검증과 공개 UI 삽입 위치를 소유한다.
- 공통 모듈은 `sr_content_items`, `sr_community_posts`, `sr_quiz_sets`, `sr_survey_forms` 같은 도메인 테이블을 직접 조회하지 않는다.
- notification, point, reward, deposit, coupon 모듈에는 hard dependency를 두지 않는다. 알림은 no-op 가능 계약으로 연결하고, 보상 연결은 1차 범위에서 제외한다.

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
- 알림 생성 가능 여부. 쓰기 자체가 불가능한 target은 알림 대상에서도 제외해야 한다.
- 적용 preset key 또는 허용 reaction key 목록

목록 화면의 N+1 조회를 피하기 위해 batch resolve는 1차 필수 요구사항이다. 도메인 계약은 target 존재와 접근 가능성을 판단하고, reaction 모듈은 그 결과 위에서 key 적용, 취소, 변경, 중복, 작성자 본인 제한을 판단한다.

## 데이터 정책

1차 테이블:

- `sr_reaction_definitions`: 개별 reaction key, 표시명, 아이콘/이모지, 색상, 설명, 상태, 정렬 기본값
- `sr_reaction_presets`: preset key, 이름, 상태, 설명, 단일 선택 정책
- `sr_reaction_preset_items`: preset별 reaction key, 정렬, 공개 노출 여부
- `sr_reaction_records`: 계정별 target reaction 원장

1차 unique key:

```text
(account_id, target_module, target_type, target_id)
```

`reaction_key`는 같은 row의 현재 선택값으로 저장한다. 전체 reaction definition 수에는 인위적인 제한을 두지 않지만, 공개 UI와 목록 집계 부하를 위해 preset별 공개 노출 key 수는 기본 6개, hard safety cap 12개로 둔다. `sr_reaction_counts`는 성능 문제가 확인된 뒤 선택적으로 추가한다.

## 정의와 사용 중지 정책

- 운영자는 관리자 화면에서 `좋아요`, `슬퍼요`, `재밌어요` 같은 리액션 종류를 추가, 수정, 정렬, 사용 중지할 수 있다.
- 기본 리액션은 seed로 제공한다.
- 이미 원장에 사용된 `reaction_key`는 변경하거나 재사용하지 않는다. 의미 변경이 필요하면 새 key를 만들고 기존 key는 사용 중지 또는 병합한다.
- 표시명과 설명은 plain text로 저장하고 출력 시 escape한다. 아이콘은 이모지 또는 허용된 Material icon key로 제한하고, 색상은 허용 swatch 또는 안전한 hex 값으로 제한한다. 이미지 아이콘 업로드는 1차에서 제외한다.
- 리액션 종류를 사용 중지하면 신규 적용/변경 write는 차단한다. 기존 사용자는 해당 key를 취소할 수 있다.
- 운영자는 사용 중지 시 기존 레코드를 보관하고 공개 UI에서 숨김, 보관하고 관리자/통계에만 표시, 삭제, 다른 reaction key로 병합 중 하나로 처리할 수 있다.
- 삭제/병합은 위험 작업으로 분리하고, 실행 전 대상 row 수, target 수, affected account 수 계산, CSRF, 권한, 확인 문구, 감사 로그 metadata를 요구한다.

## 읽기/쓰기 정책

- 신규 적용/변경 write는 로그인, CSRF, write throttle, target 접근 가능 여부, target 상태 `active`, 작성자 본인 제한을 모두 통과해야 한다.
- 취소 write는 target이 `private`, `deleted`, `broken`이어도 본인 row에 대해 허용한다.
- legacy/import/정책 변경 전 자기 target row가 발견되면 신규 적용/변경은 계속 차단하고, 본인 취소 또는 관리자 정리만 허용한다.
- 공개 표시는 `private`, `deleted`, `broken` target의 집계를 숨긴다.
- 비밀 댓글, 유료/회원그룹 제한 콘텐츠, 비공개/예약/숨김 target은 viewer별 열람 가능 여부를 기준으로 count 노출과 write 허용을 판단한다. 접근 불가 viewer에게는 count를 0으로 표시하지 않고 reaction UI 자체를 숨긴다.
- 댓글 target은 댓글 상태와 부모 target 상태를 함께 보고 더 제한적인 상태를 우선한다.
- 알림 생성은 신규 적용/변경 write가 실제로 성공한 뒤에만 시도하며, target 상태가 `active`가 아니거나 `can_write=false`이면 이벤트를 만들지 않는다.
- 같은 key `apply` 반복, `cancel` 반복, unique 충돌은 멱등 결과로 처리한다.
- write 응답은 최종 내 상태와 target 집계를 반환한다.
- 읽기 집계는 원장 기준 집계 또는 짧은 TTL의 read-time 캐시를 우선한다. 장기 daemon, DB trigger, 백그라운드 worker를 필수 전제로 두지 않는다.
- MySQL/MariaDB에서는 transaction 지원을 위해 InnoDB를 전제로 한다.

## 개인정보와 운영

- `reaction` 모듈은 `privacy-export.php`와 `privacy-cleanup.php`를 제공한다.
- export 1차 항목은 reaction record id, target module/type/id, reaction key, 현재 표시명, created_at, updated_at이다.
- target label 또는 URL snapshot은 계약으로 조회 가능하고 viewer에게 공개 가능한 경우에만 export에 포함한다.
- cleanup은 계정의 reaction record 삭제로 통일한다.
- 관리자 화면은 랭킹보다 운영 정합성 점검에 초점을 둔다. reaction 정의/preset 관리, target별/계정별 조회, broken/private/deleted target 점검, 사용 중지 key 정리, 개인정보 cleanup 확인, 오용 후보 조회를 우선한다.
- 능동 moderation, 관리자 숨김/계정 차단, 집계 캐시 재빌드 버튼, 추천/랭킹/SEO 연결은 후속 범위다.

## 보류 기준

다음 중 하나라도 구현 시점에 충족되지 않으면 도입을 보류한다.

- `reaction-targets.php` 계약과 batch resolve가 준비되지 않았다.
- privacy export/cleanup 정책과 탈퇴 삭제 경로가 구현 범위에 없다.
- idempotent write, CSRF, throttle, target 재검증, 작성자 본인 제한이 빠져 있다.
- 반응과 스크랩/완료/응답/시도/보상/랭킹의 의미 분리가 관리자와 공개 UI에 반영되지 않았다.

## #323 기준

게시판 운영 설정 범위에는 반응/추천 설정을 포함하지 않는다. 커뮤니티 게시판별 기능 후보에서 반응/추천은 계속 제외하고, 공통 `reaction` 모듈이 정의/preset과 원장을 소유한 뒤 도메인별 노출 위치만 각 모듈에서 연결한다.
