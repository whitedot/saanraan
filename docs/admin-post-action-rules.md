# 관리자 POST action 작성 규칙

이 문서는 `modules/admin/actions/` 아래 POST 처리 파일을 작성하거나 수정할 때 따르는 기준이다. 목표는 action 파일을 프레임워크처럼 숨기지 않고, 운영자가 장애 상황에서 요청 흐름을 빠르게 추적할 수 있게 하는 것이다.

## 기본 순서

관리자 POST action은 다음 순서를 기본으로 한다.

```text
1. member/admin helper include
2. 로그인 계정 확인
3. 화면 접근 권한 확인
4. $errors = [], $notice = '' 초기화
5. POST 요청이면 추가 작업 권한 확인
6. CSRF 검증
7. intent 또는 작업 입력값 읽기
8. 허용 목록과 형식 검증
9. 대상 row 존재 확인
10. 상태 변경 또는 파일/SQL 작업 실행
11. 성공 시 감사 로그 기록
12. $notice 설정
13. 화면 표시 데이터 다시 조회
14. view include
```

GET에서도 같은 action 파일을 사용한다면 GET은 조회와 화면 출력만 수행한다. 상태 변경, 세션 폐기, 파일 반영, SQL 실행은 POST에서만 처리한다.

응답을 중간에 끝내야 하는 경우 action 파일에서 `exit` 또는 `die`를 직접 호출하지 않는다. redirect는 `toy_redirect()`, 오류 응답은 `toy_render_error()`, 다운로드처럼 본문을 직접 출력한 뒤 끝내는 응답은 `toy_finish_response()`를 사용한다. `header('Location: ...')` 직접 호출은 금지한다.

## 변수 이름

화면으로 전달하는 기본 변수는 다음 이름을 사용한다.

```text
$errors: 사용자에게 보여줄 오류 메시지 배열
$notice: 성공 또는 안내 메시지 문자열
$account: 현재 로그인 계정
$intent: POST 작업 구분 값
```

action 전용 helper로 POST 처리를 분리하는 경우 반환값은 다음 형태를 사용한다.

```php
<?php

return [
    'errors' => $errors,
    'notice' => $notice,
];
```

공통 helper가 필요하면 `toy_admin_action_result()`를 사용한다. 이 helper는 결과 배열의 key를 고정해 새 action과 view 사이의 전달 형식을 일정하게 유지한다.

## 권한 확인

관리자 화면 접근 권한과 POST 작업 권한은 분리할 수 있다.

예:

```text
GET /admin/members: owner, admin, manager 조회 가능
POST /admin/members: owner, admin만 상태 변경 가능
```

이 경우 action 상단에서 조회 권한을 먼저 확인하고, POST 블록 안에서 변경 권한을 다시 확인한다.

권한이 부족한 POST 요청은 조용히 무시하지 않는다. `toy_admin_require_role()`로 명시적으로 차단하거나 `$errors`에 사용자 메시지를 추가한다. 어떤 방식을 쓰든 같은 action 안에서 일관되게 사용한다.

## 입력 검증

관리자 입력도 신뢰하지 않는다.

- `intent`는 허용된 작업 이름만 처리한다.
- id는 정수 변환 후 1 이상인지 확인한다.
- status, role, type 같은 값은 허용 배열로 확인한다.
- key 값은 정규식으로 형식을 제한한다.
- URL은 전용 URL helper로 검증한다.
- JSON 값은 `json_decode()` 오류를 확인한다.
- 파일 경로와 모듈 key는 전용 안전성 helper를 사용한다.

검증 실패 후에도 다음 변경 작업으로 진행하지 않도록 모든 변경 작업 앞에는 `$errors === []` 조건을 둔다.

## 감사 로그

관리자 POST가 상태를 바꾸면 성공 시 감사 로그를 남긴다. 실패 로그는 보안상 의미가 있거나 운영 복구에 필요한 경우에 남긴다.

기본 필드:

```text
actor_account_id: 현재 관리자 계정 id
actor_type: admin
event_type: 점 표기법의 작업 이름
target_type: 변경 대상 종류
target_id: 변경 대상 식별자
result: success 또는 failure
message: 영어 고정 요약
metadata: 운영 추적에 필요한 최소 구조 데이터
```

남기지 않는 값:

```text
비밀번호 원문
토큰 원문
세션 id 원문
CSRF 값
DB 접속 정보
개인정보 원문 대량 payload
업로드 파일 전체 내용
```

설정 변경처럼 전후 비교가 중요한 작업은 `before`와 `after`를 남길 수 있다. 단, 개인정보나 비밀값은 전후 비교 대상에서 제외한다.

## action 분리 기준

action 파일이 길어질 때는 URL을 늘리기 전에 action 전용 helper로 다음 책임을 분리한다.

- POST intent 처리
- 화면용 목록 데이터 조립
- 반복되는 검증 로직
- 파일 업로드와 정리 작업

분리 후에도 action 파일에는 로그인, 권한, CSRF, 어떤 helper가 POST를 처리하는지가 보여야 한다. 자동 등록, 숨은 라우팅, 전역 콜백 방식으로 흐름을 숨기지 않는다.
