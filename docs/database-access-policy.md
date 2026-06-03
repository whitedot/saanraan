# DB 접근 정책

산란은 ORM이나 query builder를 기본 전제로 두지 않습니다. 대신 절차형 PHP 코드에서 PDO를 직접 사용하되, SQL 작성 규칙을 명시해 SQL injection 위험과 모듈 경계 침범을 줄입니다.

## 기본 원칙

- DB 연결은 코어가 만든 `PDO` 인스턴스를 action/helper 함수에 명시적으로 전달합니다.
- 사용자 입력, 요청 값, 설정 값, 토큰, IP, 정렬 조건에서 파생된 값은 SQL 문자열에 직접 이어 붙이지 않습니다.
- 동적 값은 `PDO::prepare()`와 named placeholder를 사용해 바인딩합니다.
- `PDO::query()`는 외부 값이 전혀 섞이지 않는 고정 SQL에만 사용합니다.
- 설치/업데이트 SQL 파일은 코어 SQL 실행 helper로만 실행하고, 일반 기능 코드에서 `PDO::exec()`를 직접 사용하지 않습니다.
- 테이블명, 컬럼명, 정렬 방향처럼 placeholder로 바인딩할 수 없는 식별자는 허용 목록에서 선택한 값만 사용합니다.
- SQL 오류 상세는 운영 화면에 그대로 출력하지 않고 로그나 복구 marker로 제한합니다.

## 허용되는 패턴

고정 SQL 조회:

```php
<?php

$stmt = $pdo->query('SELECT module_key FROM sr_modules ORDER BY id ASC');
```

동적 값 조회:

```php
<?php

$stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $accountId]);
```

식별자 선택:

```php
<?php

$allowedSorts = [
    'created_at' => 'created_at',
    'email' => 'email',
];
$sortColumn = $allowedSorts[$requestedSort] ?? 'created_at';
$stmt = $pdo->query('SELECT id, email FROM sr_member_accounts ORDER BY ' . $sortColumn . ' DESC');
```

## 금지되는 패턴

```php
<?php

$pdo->query("SELECT * FROM sr_member_accounts WHERE email = '" . $email . "'");
$pdo->exec("DELETE FROM " . $_GET['table']);
```

## 모듈 경계

- 모듈은 자기 테이블과 공개 helper/계약 파일을 우선 사용합니다.
- 다른 모듈의 내부 테이블을 직접 조인해야 한다면 먼저 해당 모듈의 공개 helper나 계약 파일로 대체할 수 있는지 검토합니다.
- 회원 연계 데이터는 `account_id` 같은 안정적인 식별자로 연결하되, 코어나 `member` 테이블에 도메인 컬럼을 추가하지 않습니다.
- 설치/업데이트 SQL은 각 모듈이 소유한 테이블과 인덱스를 다루고, 다른 모듈의 도메인 테이블 변경은 피합니다.
- 설치 prefix가 `sr_`가 아닐 수 있으므로 업데이트 SQL의 일반 식별자 위치에는 논리 테이블명 `sr_*`를 쓰고, 문자열 리터럴 안의 테이블명이나 `INFORMATION_SCHEMA`의 `TABLE_NAME` 비교에는 `{{SR_TABLE_PREFIX}}` placeholder를 사용합니다.
