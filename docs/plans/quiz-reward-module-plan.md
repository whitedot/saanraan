# 마일스톤 2 퀴즈 보상 모듈 정합성 평가

평가 기준일은 2026-06-06이다. 현재 저장소에는 `quiz` 모듈 기반 파일, 설치/업데이트 스키마, `/admin/quiz` CRUD, `/admin/quiz/attempts` 리워드 로그, `/quiz/{quiz_key}` 풀이/제출/자동 채점, 공개 기간/회원 그룹/시도 제한, 콘텐츠와 커뮤니티 source 연결, 모달/page fallback CTA, 개인정보 계약, 자산 원장 조회 계약, 쿠폰 보상 참조 계약, 정합성 검사가 반영되어 있다. 이 문서는 마일스톤 2 범위의 계획 정합성과 구현 중 지켜야 할 결정을 기록한다.

## 결론

퀴즈 보상은 새 서비스 도메인 모듈 `quiz`가 소유하는 방향이 가장 정합적이다. 콘텐츠나 커뮤니티 안에 퀴즈 풀이 화면과 채점 정책을 직접 넣으면 본문/게시판/댓글 흐름에 퀴즈 상태, 보상 원장, 완료 후 이동 정책이 섞인다. 이는 현재 프로젝트의 작은 코어, 명시적 모듈 경계, 읽히는 요청 흐름 원칙과 맞지 않는다.

퀴즈 풀이는 공개 퀴즈 메인/상세 페이지를 기본 표면으로 두고, 콘텐츠나 커뮤니티에서 시작한 경우에는 가능한 한 모달을 우선 사용한다. 모달을 사용할 수 없거나 접근성, 세션 만료, 모바일 화면, 새로고침 복구가 필요한 경우에는 퀴즈 풀이 페이지로 이동하는 fallback을 제공한다.

## 권장 사용자 흐름

1. `quiz` 모듈은 자체 메인 페이지 `/quiz`를 가진다.
2. 개별 퀴즈는 `/quiz/{quiz_key}` 또는 명시 경로의 풀이 페이지를 가진다.
3. 콘텐츠/커뮤니티는 퀴즈를 본문 안에 직접 포함하지 않고, 퀴즈 시작 버튼 또는 링크만 렌더링한다.
4. 같은 화면 안에서 처리 가능한 경우 버튼은 퀴즈 모달을 연다.
5. 모달이 불가능하면 `/quiz/...` 풀이 페이지로 이동하되 `return_to` 또는 서버 저장 흐름 토큰으로 복귀 위치를 전달한다.
6. 모달 안에서 로그인하거나 완료 후 돌아갈 때는 iframe 내부가 아니라 top-level 화면이 원래 페이지로 이동해야 한다.
7. 퀴즈 완료 후 유효한 내부 복귀 위치가 있으면 원래 콘텐츠/커뮤니티 화면으로 돌아가고, 없으면 퀴즈 결과 또는 퀴즈 메인으로 이동한다.

## 보상 처리 방향

퀴즈 보상 정책, 시도 제한, 정답 판정, 중복 보상 방지, 결과 로그는 `quiz` 모듈이 소유한다. 보상은 자동 채점 통과 직후 `ledger_asset` 또는 `coupon` provider로 지급할 수 있다. 포인트/적립금/예치금 지급은 활성 자산 모듈의 `member-assets.php` 계약을 읽어 실행하고, 쿠폰 지급은 활성 쿠폰 정의 ID를 보상 정책에 저장한 뒤 쿠폰 모듈의 발급 helper로 실행한다. 보상 지급은 provider 처리와 퀴즈 시도/완료 로그를 같은 DB transaction 안에서 묶고, `per_quiz`, `per_source`, `per_attempt` dedupe와 보상 grant row 잠금으로 정책 재저장/동시 제출 중복 지급을 막는다.

콘텐츠/커뮤니티는 퀴즈 완료 여부를 자기 테이블에 복사 저장하지 않는다. 해당 화면에서 퀴즈 완료 상태를 보여줘야 하면 `quiz` 모듈의 좁은 조회 helper 또는 계약으로 현재 회원의 완료 여부만 확인한다.

## 복귀 URL 기준

복귀 위치는 외부 URL을 허용하지 않는다. 내부 상대 경로만 받으며, `sr_url()`로 출력 가능한 경로인지 검증한다. `Referer` 헤더는 보조값으로만 사용하고 저장 진실로 삼지 않는다.

권장 파라미터는 다음과 같다.

- `source_module`: `content`, `community`, `quiz` 같은 시작 모듈 key
- `source_type`: `content_item`, `community_post`, `community_board` 같은 시작 대상 type
- `source_id`: 시작 대상 ID
- `return_to`: 검증된 내부 상대 경로

용어는 역할별로 구분한다. 요청 파라미터와 화면 흐름 이름은 `return_to`를 사용하고, DB snapshot 컬럼은 당시 복귀 URL 기록이라는 의미로 `return_url`을 사용할 수 있다.

위 값이 변조되거나 대상이 숨김/삭제/권한 없음 상태이면 응시 로그 source로 저장하지 않고 완료 후 퀴즈 결과 페이지 또는 `/quiz`로 fallback한다.

MVP 비회원 정책은 공개 퀴즈 목록/상세 보기만 허용하고, 응시 시작과 제출은 로그인 회원에게만 허용한다. 비회원 attempt는 저장하지 않는다.

## 관리자 범위

마일스톤 2 계획은 최소한 다음 관리자 화면을 포함해야 한다.

- 퀴즈 목록과 생성/수정
- 문제/선택지/정답/해설 관리
- 공개 상태, 공개 기간, 응시 가능 회원 조건
- 보상 사용 여부, 보상 자산, 금액, 중복 보상 정책
- 시도/완료/보상 지급 내역
- 콘텐츠/커뮤니티에서 연결할 퀴즈 후보 검색 또는 선택 기준

관리자 검증은 서버가 source of truth다. 필수 제목, key, 문제, 정답, 보상 자산, 보상 금액, 공개 기간, 복귀 정책은 POST action에서 다시 검증해야 한다.

## 범위 결정

- 문제 유형은 `single_choice`와 `multiple_choice`를 지원한다. `scored/correct_answer`, `scored/total_score`, `diagnostic/category_score` 조합은 기존 `sr_quiz_results`와 `sr_quiz_result_rules`에 결과 프로필과 규칙을 저장해 처리한다.
- source 연계는 `content/content_item`과 `community/community_post`를 지원한다. 독립 `/quiz` 메인과 개별 풀이 페이지를 제공하고, source 연계와 완료 후 복귀 검증은 각 source의 공개/접근 권한 helper를 통해 처리한다.
- 보상 지급 시점은 자동 채점 통과 직후로 둔다.
- `ledger_asset` 복구 절차는 point/reward/deposit 자산 모듈이 `reference_type=quiz_reward`, `reference_id={grant_id}` 기준 원장 거래 조회 helper를 제공할 때만 활성화한다. 현재 계약에는 거래 생성 함수만 있으므로 #82 첫 구현 작업은 자산별 조회 helper와 `member-assets.php`의 `transaction_lookup_function` 조회 callable 추가다.
- `member-assets.php`의 `transaction_lookup_function`은 계약 파일에 선언하는 것만으로 끝내지 않고 `sr_member_ledger_asset_definitions()` 결과에도 노출되어야 한다.
- 공개 퀴즈 상세 URL의 `quiz_key`는 정규화해서 다른 키로 맞추지 않는다. URL 세그먼트가 `quiz_key` 규칙과 다르면 404로 처리한다.
- `deleted_at`이 있는 퀴즈 row는 공개 목록/상세와 기본 관리자 목록에서 제외한다.
- `quiz_key`는 DB unique 제약과 맞춰 삭제된 퀴즈 row까지 포함해 중복을 막는다.
- 관리자 수정 저장은 대상 퀴즈 row를 transaction 안에서 잠금 확인한 뒤 하위 문제/보상/source를 교체한다.
- 공개 기간은 `starts_at`/`ends_at`으로 저장하고 공개 목록, 콘텐츠 CTA, 상세 진입에서 같은 기준으로 제외한다.
- 응시 가능 회원 조건은 `member_group_keys_json`의 활성 회원 그룹 중 하나라도 속한 로그인 회원으로 제한한다. 비어 있으면 로그인 회원 전체가 응시할 수 있다.
- 공개 퀴즈 상세는 `comments_enabled`가 켜진 경우 로그인 회원 댓글을 지원한다. 댓글 본문은 `sr_quiz_comments`가 소유하고, 비밀 댓글은 작성자와 댓글 관리 권한 관리자만 본문을 볼 수 있다. 댓글 본문의 `@공개이름#prefix` 멘션은 알림 모듈의 `quiz/comment.mention` 템플릿으로 선택적 사이트 알림을 만든다.
- MVP 시도 제한은 `unlimited`, `per_quiz_once`, `per_period`를 서버 제출 직전에 검증한다.
- 모달은 접근성 기준을 만족해야 한다. 포커스 trap, ESC/닫기 버튼, `aria-modal`/제목 연결, 키보드 제출, 모바일 화면 높이 초과 시 내부 스크롤, JS 실패 시 개별 풀이 페이지 링크 fallback을 제공한다.
- 오답 재시도는 attempt 제한 정책에 따르되, 같은 dedupe scope에서 보상은 중복 지급하지 않는다.
- 개인정보 export/cleanup은 계정별 시도, 제출 답안 snapshot, 결과 snapshot, 보상 지급 로그, 작성 댓글을 포함하고, 탈퇴/익명화 시 account 연결, 댓글 작성자 snapshot, 직접 식별 가능한 네트워크 식별 hash를 비운다.
- HTTP smoke test 범위는 `/quiz`, 콘텐츠/커뮤니티 source 시작, 퀴즈 풀이, 완료 후 `return_to` 복귀, 중복 제출 방지, 보상 원장 또는 쿠폰 발급 생성을 포함한다. 인증 기반 전용 E2E는 `.tools/bin/smoke-quiz-e2e.php`로 고정하고, 관리자 권한 테스트 계정으로 퀴즈 생성, 복수/단일 선택 제출, 통과 결과, 보상 지급, 재응시 차단, 생성 퀴즈 cleanup을 확인한다.

## 판정

현재 제안된 "모달 우선, 필요 시 퀴즈 페이지 이동, 퀴즈 자체 메인 페이지 보유, 완료 후 출발 화면 복귀" 방향은 프로젝트 경계 규칙과 잘 맞는다. 후속으로 등록된 `multiple_choice`, `total_score`, `category_score`, 커뮤니티 source, `per_source`/`per_attempt` dedupe, 쿠폰 provider와 쿠폰 정의 읽기 참조 계약은 현재 구현 범위에 포함되어 있다.
