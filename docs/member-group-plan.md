# 회원 그룹과 모듈 조건 연동 계획

이 문서는 `member` 모듈에 회원 그룹 기능을 추가하고, 설치된 모듈이 제공하는 조건을 기반으로 자동 그룹 부여를 처리하는 방향을 정리한다.

## 1. 핵심 결정

회원 그룹은 `member` 모듈이 소유한다.

- 그룹 정의, 수동 배정, 자동 배정 결과, 배정 이력은 `member` 모듈 테이블에 둔다.
- 커뮤니티, 커머스, 포인트 같은 도메인 모듈은 자기 도메인 조건 후보만 계약 파일로 제공한다.
- 도메인 모듈은 `toy_member_*` 그룹 테이블을 직접 변경하지 않는다.
- 코어는 회원 그룹을 알지 않는다.
- 관리자 role인 `owner`, `admin`, `manager`와 회원 그룹은 별개다.

회원 그룹은 "관리자 권한"이 아니라 "사이트 회원 접근/혜택 정책"이다.

v1의 그룹 모델은 다중 소속이다. "자동 이동"은 단일 등급 값을 덮어쓰는 방식이 아니라 조건을 만족한 그룹 membership을 부여하고, 필요하면 자동 membership만 회수하는 방식으로 해석한다. 브론즈/실버/골드처럼 서로 배타적인 등급 체계가 필요하면 후속 버전에서 `group_set` 또는 `exclusive_group` 개념을 추가한다.

예:

```text
community 모듈:
- 자유게시판에 공개 게시글 5개 이상 작성한 회원

member 모듈:
- 위 조건을 만족하면 `regular_member` 그룹 자동 부여

community 모듈:
- 특정 게시판의 read_policy를 `group`으로 두고 `regular_member` 그룹만 열람 허용
```

## 2. 현재 모듈 영향 방식과 맞추는 원칙

Toycore는 숨은 event bus, service provider, 자동 boot hook으로 모듈 간 영향을 연결하지 않는다. 현재 모듈 간 영향은 다음 방식으로 처리한다.

- 제공 모듈이 계약 파일을 둔다.
- 소비 모듈이 필요한 관리자 화면 또는 처리 시점에 계약 파일을 명시적으로 읽는다.
- 사용자 요청에서 매번 모든 계약 파일을 스캔하지 않는다.
- 최종 정책 판단은 소비 모듈이 다시 검증한다.

회원 그룹 자동 조건도 같은 방식을 따른다.

```text
조건 제공:
community/member-group-rules.php

조건 소비:
member 모듈의 그룹 자동화 관리자 화면과 평가 helper

접근 제어 소비:
community 모듈의 게시판 권한 helper가 member 공개 helper로 그룹 가입 여부 확인
```

즉, community가 "조건 후보"를 제공하고 member가 "그룹 부여"를 수행한다. 이후 community는 member의 그룹 조회 helper를 사용해 게시판 접근을 판단한다.

## 3. 신규 계약 파일 후보

새 계약 파일 후보는 다음 이름으로 둔다.

```text
member-group-rules.php
```

역할:

- 설치된 모듈이 회원 그룹 자동 부여에 사용할 수 있는 조건 후보를 선언한다.
- 조건의 설정 UI에 필요한 parameter schema를 제공한다.
- 특정 회원이 조건을 만족하는지 평가하는 callable을 제공한다.

이 파일은 새 계약 파일이므로 구현 단계에서 다음도 함께 갱신해야 한다.

- `core/helpers/settings.php`의 `toy_module_known_contract_files()`
- `docs/module-guide.md`의 계약 파일 설명
- `.tools/bin/check.php` 또는 관련 정적 검사
- `TOY_MODULE_CONTRACT_VERSION` 변경 여부 검토

기존 모듈을 깨지 않는 선택 계약으로 시작하되, 코어의 계약 파일 검증 규칙이 바뀌는 변경이므로 릴리스 시 결정 로그에 명시한다.

## 4. 계약 반환 구조 초안

`member-group-rules.php`는 배열을 반환한다. 각 항목은 하나의 조건 후보다.

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    [
        'rule_key' => 'community.board.post_count_at_least',
        'label' => '특정 게시판 게시글 수 이상',
        'description' => '선택한 게시판에 공개 게시글을 지정 수 이상 작성한 회원입니다.',
        'params' => [
            [
                'key' => 'board_id',
                'label' => '게시판',
                'type' => 'subject',
                'selector' => [
                    'mode' => 'options',
                    'provider' => 'toy_community_member_group_rule_board_options',
                ],
            ],
            [
                'key' => 'min_count',
                'label' => '최소 게시글 수',
                'type' => 'int',
                'min' => 1,
                'max' => 1000,
                'default' => 5,
            ],
        ],
        'evaluator' => 'toy_community_member_group_rule_post_count_at_least',
    ],
];
```

evaluator callable 형식:

```php
function (PDO $pdo, int $accountId, array $params): array
```

반환값:

```php
[
    'matched' => true,
    'metric' => 7,
    'summary' => '게시글 7개',
]
```

규칙:

- callable은 제공 모듈의 공개 helper에 둔다.
- callable은 자기 모듈 테이블을 조회한다.
- callable은 회원 그룹 테이블을 변경하지 않는다.
- member는 반환값을 검증하고 그룹 부여/회수만 처리한다.
- 본문, 이메일, token, hash 같은 민감 값은 summary나 로그에 넣지 않는다.

## 5. 회원 그룹 데이터 모델 초안

### 5-1. `toy_member_groups`

```text
id BIGINT UNSIGNED PK
group_key VARCHAR(60) UNIQUE
title VARCHAR(120)
description TEXT NULL
status VARCHAR(30) DEFAULT 'enabled'
is_system TINYINT(1) DEFAULT 0
sort_order INT DEFAULT 0
created_at DATETIME
updated_at DATETIME
```

`is_system`은 기본 그룹처럼 삭제나 key 변경을 제한해야 하는 그룹에 사용한다.

### 5-2. `toy_member_group_memberships`

```text
id BIGINT UNSIGNED PK
group_id BIGINT UNSIGNED
account_id BIGINT UNSIGNED
assignment_type VARCHAR(30) DEFAULT 'manual'
source_module_key VARCHAR(60) NOT NULL DEFAULT ''
source_rule_key VARCHAR(120) NOT NULL DEFAULT ''
status VARCHAR(30) DEFAULT 'active'
granted_at DATETIME
expires_at DATETIME NULL
revoked_at DATETIME NULL
created_by_account_id BIGINT UNSIGNED NULL
updated_at DATETIME
```

`assignment_type`:

```text
manual
auto
```

인덱스:

```text
group_id, status, account_id
account_id, status, group_id
source_module_key, source_rule_key, status
```

같은 그룹의 수동 배정과 자동 배정은 분리한다. 어떤 회원이 그룹에 속하는지는 active membership이 하나라도 있는지로 판단한다. 자동 조건이 해제되어도 수동 배정은 유지된다.

### 5-3. `toy_member_group_rules`

관리자가 설정한 자동 그룹 부여 규칙이다.

```text
id BIGINT UNSIGNED PK
group_id BIGINT UNSIGNED
source_module_key VARCHAR(60)
rule_key VARCHAR(120)
rule_params_json TEXT
evaluation_policy VARCHAR(30) DEFAULT 'grant_only'
status VARCHAR(30) DEFAULT 'enabled'
last_evaluated_at DATETIME NULL
created_at DATETIME
updated_at DATETIME
```

`evaluation_policy`:

```text
grant_only: 조건을 한 번 만족하면 자동 그룹을 유지한다.
sync: 조건을 더 이상 만족하지 않으면 자동 그룹을 revoked로 바꾼다.
```

첫 구현 기본값은 `grant_only`가 안전하다. 게시글이 숨김/삭제되어 조건이 깨졌을 때 자동으로 권한을 회수하는 정책은 운영 충격이 있으므로 관리자가 `sync`를 명시 선택하게 한다.

### 5-4. `toy_member_group_membership_logs`

감사와 운영 추적을 위해 별도 이력 테이블을 둔다.

```text
id BIGINT UNSIGNED PK
group_id BIGINT UNSIGNED
account_id BIGINT UNSIGNED
membership_id BIGINT UNSIGNED NULL
event_type VARCHAR(60)
source_module_key VARCHAR(60) NOT NULL DEFAULT ''
source_rule_key VARCHAR(120) NOT NULL DEFAULT ''
actor_account_id BIGINT UNSIGNED NULL
message VARCHAR(255) NOT NULL DEFAULT ''
metadata_json TEXT NULL
created_at DATETIME
```

## 6. member 모듈 helper 계획

공개 helper 후보:

```text
toy_member_group_exists($pdo, $groupKey)
toy_member_group_by_key($pdo, $groupKey)
toy_member_account_group_keys($pdo, $accountId)
toy_member_account_in_group($pdo, $accountId, $groupKey)
toy_member_account_in_any_group($pdo, $accountId, $groupKeys)
toy_member_group_rule_definitions($pdo)
toy_member_group_evaluate_account($pdo, $accountId, $filters = [])
toy_member_group_grant_manual($pdo, $accountId, $groupId, $actorAccountId)
toy_member_group_revoke_manual($pdo, $accountId, $groupId, $actorAccountId)
```

`toy_member_group_rule_definitions()`는 활성 모듈의 `member-group-rules.php`를 안전 로더로 읽고, 반환 구조를 member 쪽에서 다시 검증한다.

`toy_member_group_evaluate_account()`는 저장된 `toy_member_group_rules`를 기준으로 evaluator를 호출한다. `$filters`로 특정 `source_module_key`만 평가할 수 있게 하여 community 글 작성, 게시글 상태 변경, 작성자 삭제 직후 community 규칙만 재평가할 수 있게 한다.

## 7. 평가 시점

저가형 웹호스팅을 고려해 background worker를 필수로 두지 않는다.

기본 평가 시점:

- 로그인 성공 직후
- 회원 계정 화면 진입 시
- 도메인 모듈이 조건에 영향을 주는 상태 변경을 끝낸 직후 명시 호출
- 관리자 회원 그룹 화면의 수동 재평가 버튼
- 관리자 전체 재평가 화면에서 제한된 batch 실행

community 예:

```text
1. 회원이 게시글 작성에 성공하거나 게시글 상태가 변경되어 published 집계가 달라질 수 있음
2. community action이 toy_member_group_evaluate_account($pdo, $accountId, ['source_module_key' => 'community']) 호출
3. member가 enabled 자동 규칙 중 community 규칙만 평가
4. 조건 만족 시 member가 membership active 처리
5. 이후 community 게시판 접근 helper는 toy_member_account_in_any_group()으로 확인
```

이 호출은 숨은 event가 아니라 action 파일에 보이는 명시적 후처리다.

## 8. 관리자 화면 계획

member 관리자 화면:

```text
/admin/member-groups
```

기능:

- 그룹 생성/수정/비활성화
- 회원 수동 배정/해제
- 자동 규칙 생성/수정/비활성화
- 설치된 모듈이 제공하는 조건 후보 목록 표시
- 특정 회원 재평가
- 그룹 전체 batch 재평가
- 배정 이력 확인

권한:

```text
GET /admin/member-groups: owner, admin, manager
POST /admin/member-groups: owner, admin
POST /admin/member-groups/recalculate: owner, admin
```

`manager`는 조회만 허용한다. 그룹은 접근 권한에 영향을 주므로 변경은 `owner`, `admin`으로 제한한다.

## 9. community 연동 계획

community는 member group 기능을 직접 소유하지 않는다. 대신 두 방향으로만 연결한다.

### 9-1. 조건 제공

community는 `member-group-rules.php`로 다음 조건 후보를 제공한다.

```text
community.board.post_count_at_least
- 특정 게시판에 published 게시글 N개 이상

community.total.post_count_at_least
- 전체 공개 게시글 N개 이상

community.board.comment_count_at_least
- 특정 게시판 게시글에 published 댓글 N개 이상
```

v1에서는 첫 조건인 `community.board.post_count_at_least`만 구현하고, 나머지는 후속 후보로 둔다.

### 9-2. 게시판 접근 정책 소비

community 게시판 설정은 member 그룹을 선택적으로 참조한다.

```text
read_policy: public | member | group
write_policy: member | group | admin
comment_policy: member | group | disabled
```

`*_policy = group`이면 게시판 설정에 저장된 허용 group key 목록을 확인한다.

규칙:

- 그룹 존재 여부와 활성 상태는 member helper로 확인한다.
- 그룹 조건 평가는 community 접근 요청에서 직접 수행하지 않는다.
- 접근 요청에서는 저장된 membership만 조회한다.
- 그룹 설정이 깨졌거나 group key가 없으면 fail closed로 접근을 거부하고 관리자 화면에 설정 오류를 표시한다.

## 10. 개인정보와 탈퇴

회원 그룹 데이터는 회원 개인정보 export에 포함한다.

포함:

- 회원이 속한 그룹 key/title
- 수동/자동 배정 여부
- 자동 배정 source module/rule key
- granted/revoked 시간

제외:

- 다른 회원의 그룹 membership
- evaluator 내부 metric 원본 중 개인정보가 섞일 수 있는 상세 데이터
- 운영자 내부 메모 중 민감 값

회원 탈퇴/익명화 시:

- account_id 기반 membership은 유지할 수 있다.
- 표시 이름은 member 익명화 fallback을 따른다.
- 자동 평가 대상에서는 withdrawn/anonymized 계정을 제외한다.

## 11. 구현 단계

### Phase G1. 수동 회원 그룹

- member 그룹 테이블 추가
- `/admin/member-groups` 기본 화면
- 그룹 생성/수정/비활성화
- 회원 수동 배정/해제
- `toy_member_account_in_group()` helper 제공

완료 기준:

- community 없이도 member 그룹 관리 가능
- 관리자 role과 회원 그룹이 섞이지 않음
- 개인정보 export에 그룹 데이터 포함

### Phase G2. 자동 규칙 계약

- `member-group-rules.php`를 알려진 계약 파일에 추가
- member가 활성 모듈의 조건 후보를 안전 로더로 읽음
- 조건 parameter schema 검증
- 자동 규칙 저장 테이블과 관리자 UI 추가

완료 기준:

- 깨진 조건 계약 파일이 있어도 member 그룹 화면이 500으로 죽지 않음
- 조건 후보는 관리자 화면에서만 탐색
- 사용자 요청에서 전체 계약 파일 스캔 없음

### Phase G3. 자동 평가

- evaluator callable 실행
- `grant_only`와 `sync` 정책 처리
- 특정 회원/특정 source module 재평가
- batch 재평가
- 감사 로그와 membership log 기록

완료 기준:

- 조건 만족 시 자동 membership 생성
- `sync` 규칙에서 조건 불만족 시 자동 membership만 revoked 처리
- 수동 membership은 자동 평가로 제거되지 않음

### Phase G4. community 조건 제공과 게시판 권한

- community가 `member-group-rules.php` 제공
- 게시글 작성/상태 변경 후 community 규칙 재평가 명시 호출
- 게시판 설정에 group 기반 read/write/comment 정책 추가
- public 조회/작성/댓글 action에서 group 정책 확인

완료 기준:

- "특정 게시판 published 게시글 5개 이상 작성하면 특정 그룹 획득" 시나리오 동작
- 해당 그룹만 열람 가능한 게시판 접근 제어 동작
- 그룹 조건 계약 없이 community 기본 게시판은 계속 동작

## 12. 리스크와 대응

| 리스크 | 대응 |
| --- | --- |
| 모듈 간 숨은 결합 증가 | 조건은 계약 파일, 결과 소유는 member, 접근 소비는 public helper로 제한 |
| 평가 비용 증가 | 로그인/상태 변경/관리자 재평가 시점으로 제한하고 요청마다 전체 규칙 평가 금지 |
| 조건 해제 시 권한 박탈 혼란 | 기본은 `grant_only`, 필요한 규칙만 `sync` 선택 |
| 수동 배정이 자동 평가로 지워짐 | manual과 auto membership을 분리 |
| community가 member 내부 테이블에 의존 | community는 member 공개 helper만 사용 |
| 계약 파일 실패로 관리자 화면 장애 | `toy_load_module_contract_file()` 사용, 실패한 모듈 조건만 제외 |

## 13. 완료 정의

- 회원 그룹 테이블은 `toy_member_*` 안에 있고 core 테이블은 넓히지 않는다.
- 도메인 모듈은 그룹 조건 후보만 제공하고 membership을 직접 변경하지 않는다.
- 자동 조건은 설치된 활성 모듈의 계약 파일에서 읽는다.
- 그룹 접근 제어는 member 공개 helper로만 판단한다.
- 관리자 화면에서 수동 배정과 자동 규칙을 구분해 볼 수 있다.
- 개인정보 export와 탈퇴/익명화 정책이 정의되어 있다.
- background worker 없이도 주요 시나리오가 동작한다.
