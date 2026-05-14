# 관리자 토스트 안내 전환 계획

이 문서는 관리자 화면에서 `회원 세션을 폐기했습니다.` 같은 짧은 성공 안내를 본문 상단 문구가 아니라 토스트 메시지로 표시하기 위한 구현 계획이다.

문서 수명:

- 토스트 기반 관리자 안내를 구현하기 전까지 계획 문서로 보관한다.
- 실제 구현과 검증이 완료되면 이 문서는 삭제한다.
- 구현 후 유지해야 하는 기준은 `docs/module-guide.md`, `docs/security-model.md`, 관리자 모듈 README 중 필요한 곳으로만 옮긴다.

## 대상

우선 적용 대상은 회원 관리 화면의 세션 폐기 성공 안내다.

현재 흐름:

```text
modules/member/helpers/admin-members.php
-> sr_admin_handle_members_post()
-> $notice = '회원 세션을 폐기했습니다.'
-> modules/member/views/admin-members.php
-> 본문 상단 <p>로 출력
```

변경 목표:

```text
회원 세션 폐기 성공
-> 관리자 flash notice 저장
-> redirect
-> 공통 관리자 레이아웃에서 toast 출력
```

## 기본 방향

토스트는 `member` 모듈 전용 기능이 아니라 관리자 UI의 공통 표시 방식으로 둔다.

책임 분리:

- `admin`: flash 메시지 저장, 소비, 공통 toast 렌더링, CSS/JS 표시 기반
- `member`: 세션 폐기 성공 시 어떤 메시지를 띄울지만 결정
- `core`: 토스트 정책을 모른다.

다만 1차 구현은 적용 범위를 `회원 세션을 폐기했습니다.` 하나로 제한한다. 관리자 전체 notice를 한 번에 바꾸지 않는다.

## 왜 redirect가 필요한가

현재 회원 관리 POST는 같은 요청에서 `$notice`를 만들고 바로 view를 렌더링한다. 이 구조에서도 토스트 출력은 가능하지만, 새로고침하면 같은 POST 재제출 문제가 남는다.

토스트 전환 시점에는 PRG 흐름을 같이 적용한다.

```text
POST /admin/members
-> 작업 처리
-> flash notice 저장
-> GET /admin/members?status=...
-> toast 출력
-> flash 제거
```

이렇게 하면 토스트가 한 번만 표시되고, 새로고침으로 세션 폐기 요청이 반복되지 않는다.

## 1차 구현 범위

포함한다:

- 관리자 flash helper 추가
- success toast 렌더링
- 관리자 기본 스킨에 toast container 추가
- CSS로 고정 위치 toast 스타일 추가
- `회원 세션을 폐기했습니다.` 성공 안내만 toast로 전환
- 세션 폐기 POST 후 redirect 적용
- 오류 메시지는 기존 본문 출력 유지

포함하지 않는다:

- 모든 관리자 notice 일괄 전환
- public/account 화면 토스트 전환
- 알림 모듈과의 통합
- 브라우저 notification API
- 자동 닫힘 시간을 운영자가 설정하는 기능
- 여러 토스트 queue 고급 제어

## helper 계획

`admin` 모듈에 flash helper를 둔다.

예상 함수:

```text
sr_admin_flash_notice(string $message, string $level = 'success'): void
sr_admin_consume_flash_notices(): array
```

세션 저장 키 후보:

```text
$_SESSION['sr_admin_flash_notices']
```

저장 값 예:

```php
[
    [
        'level' => 'success',
        'message' => '회원 세션을 폐기했습니다.',
    ],
]
```

허용 level:

- `success`
- `warning`
- `error`
- `info`

1차에서는 `success`만 사용한다.

## 렌더링 계획

관리자 스킨의 `layout-header.php` 또는 `layout-footer.php`에서 flash를 소비한다.

권장 위치:

- `layout-header.php`: `<main>` 시작 직후 screen reader용 영역과 toast container 출력
- 또는 `layout-footer.php`: `</main>` 직전 toast container 출력

토스트 markup 예:

```php
<div data-admin-toast-stack role="status" aria-live="polite" aria-atomic="false">
    <div class="admin-flash-message admin-flash-message-success" data-admin-toast>
        <strong>완료</strong>
        <span><?php echo sr_e('회원 세션을 폐기했습니다.'); ?></span>
        <button type="button" class="btn btn-sm btn-icon" data-admin-toast-close aria-label="알림 닫기">
            <span class="close-icon" aria-hidden="true"></span>
        </button>
    </div>
</div>
```

메시지는 반드시 `sr_e()`로 escape한다.

## 동작 계획

JavaScript 없이도 보이게 만든다.

1차 CSS 동작:

- 화면 상단 중앙 고정
- 관리자 header와 겹치지 않도록 여백 확보
- 4-6초 정도 보이다가 CSS animation으로 사라짐
- `prefers-reduced-motion: reduce`에서는 사라짐 animation을 끈다.

닫기 버튼은 `data-admin-toast-close` 속성으로 연결하고, 버튼 자체는 기존 `btn` 계열 클래스를 사용한다.

## 접근성

- 성공/정보 toast는 `aria-live="polite"`와 `role="status"`를 사용한다.
- 오류 toast를 도입할 경우 `aria-live="assertive"` 또는 기존 본문 오류 유지 중 하나를 선택한다.
- 오류는 1차에서 toast로 옮기지 않는다. 폼 검증 오류는 화면 맥락과 함께 보여야 하므로 기존 본문 목록이 더 안전하다.

## 회원 세션 폐기 적용 계획

`sr_admin_handle_members_post()`의 반환값은 현재 `sr_admin_action_result($errors, $notice)` 구조다.

1차 변경 후보:

1. `intent === 'revoke_sessions'` 성공 시 `sr_admin_flash_notice('회원 세션을 폐기했습니다.')` 호출
2. 현재 status filter를 유지한 redirect URL 생성
3. `sr_redirect('/admin/members?...')`
4. view에서는 해당 notice를 본문에 출력하지 않음

상태 저장 성공 안내인 `회원 상태를 저장했습니다.`는 1차에서는 기존 출력으로 둔다. 같은 화면의 모든 성공 안내를 동시에 바꾸면 검증 범위가 넓어진다.

## 검증 항목

- 세션 폐기 성공 후 토스트가 표시되는가
- 새로고침해도 같은 토스트가 반복 표시되지 않는가
- 새로고침해도 POST 재제출 경고가 뜨지 않는가
- 세션 폐기 실패는 기존 오류 목록으로 표시되는가
- 현재 로그인한 관리자 계정 세션 폐기 시도는 기존 오류로 표시되는가
- status filter가 유지되는가
- 모바일 폭에서 토스트가 화면 밖으로 넘치지 않는가
- 다크 모드 색상이 읽히는가
- `prefers-reduced-motion`에서 불필요한 움직임이 줄어드는가

## 이후 확장

1차 구현이 안정되면 다음 안내를 순차적으로 전환한다.

- 관리자 설정 저장 성공
- 모듈 상태 저장 성공
- 메뉴 저장 성공
- 배너/팝업/알림 삭제 성공

전환 기준:

- 짧고 독립적인 성공 안내는 toast 후보
- 폼 오류, 상세한 검증 결과, 복구 안내가 필요한 메시지는 본문 유지
- 사용자가 결과를 오래 읽어야 하는 메시지는 본문 유지
