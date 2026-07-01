# 설치 초기화 기준

이 문서는 설치 완료 상태를 되돌려 새 설치 화면으로 돌아가기 위한 기준을 정리한다. QA 더미 데이터를 비우고 다시 채우는 사이트 초기화와 다르며, 더미 데이터 기준은 [사이트 초기화와 더미 데이터 기준](site-reset-and-fixtures.md)에 둔다.

## 현재 표면

설치 초기화는 고위험 작업이므로 먼저 CLI preview만 제공한다.

```sh
php .tools/bin/install-reset.php --preview
php .tools/bin/install-reset.php --preview --json
```

기본 preview는 read-only로 동작한다. 실행은 CLI에서만 제공하고 확인 문구를 요구한다.

```sh
php .tools/bin/install-reset.php --execute --confirm=초기화 --batch-size=50
```

실행 모드는 production-looking 경고가 있으면 기본 중단한다. disposable production-like staging에서 꼭 실행해야 하는 경우에만 `--allow-production-looking`을 붙인다. remote storage reference가 있으면 별도 확인 없이 중단하며, 등록 key 삭제를 승인할 때만 `--confirm-remote-storage`를 붙인다.

## Preview 범위

Preview는 `config/config.php`와 `storage/installed.lock`의 존재와 읽기 가능 여부를 표시한다. 두 파일이 모두 없으면 이미 초기 설치 상태로 본다. `config/config.php`가 있지만 현재 CLI 사용자에게 읽히지 않으면 DB 접속 정보를 확인할 수 없으므로 실패로 종료한다.

DB 후보는 repository SQL에서 만든 allowlist와 현재 DB introspection 결과의 교집합만 사용한다. allowlist는 `database/core/install.sql`, `database/core/updates/*.sql`, `modules/*/install.sql`, `modules/*/updates/*.sql`의 `CREATE TABLE` 선언에서 `sr_` table 또는 `{{SR_TABLE_PREFIX}}` table만 수집한다. 현재 설치 설정에 안전한 table prefix가 있으면 그 prefix로 변환한다.

Preview는 다음 값을 보여준다.

- allowlist table 수
- 현재 DB의 prefixed table 수
- allowlist와 DB introspection 교집합에 있는 target table 수
- target table row 수 합계
- prefixed table이지만 allowlist에 없어 삭제 후보에서 제외된 table 수
- target table에서 찾은 storage reference column 수
- safe/unsafe storage reference 수
- local/remote storage reference 수
- 현재 로컬 storage에 실제 존재하는 파일 수와 byte 합계

Storage preview는 target table의 `storage_key` 또는 `*_storage_key` column을 introspection으로 찾고, 같은 접두사의 `storage_driver` 또는 `*_storage_driver`가 있으면 driver로 사용한다. driver가 없으면 configured default storage driver를 쓴다. 기본값은 column당 최대 5,000개 reference만 sample해서 local 파일 존재와 byte를 확인하며, 더 많은 reference가 있으면 `truncated`로 표시한다. remote storage는 count만 표시하고 object head/list/delete는 실행하지 않는다.

Preview는 config env, site base URL, DB 이름, S3 bucket에서 production-looking 신호도 표시한다. 이 값은 read-only preview의 경고이며, 실행 모드에서는 기본 중단 조건이다.

## 실행 순서

실행은 `storage/install-reset.lock`을 만들어 중복 실행을 막는다. 같은 명령을 반복 실행할 수 있도록 batch 단위로 진행한다.

1. confirmation phrase가 `초기화`인지 확인한다.
2. production-looking 경고와 unsafe/remote storage reference를 확인한다.
3. storage reference를 batch 크기만큼 삭제한다. 남은 reference가 있으면 DB와 설치 상태 파일은 건드리지 않고 종료한다.
4. 현재 DB introspection과 allowlist의 교집합 table을 batch 크기만큼 drop한다. 남은 table이 있으면 설치 상태 파일은 건드리지 않고 종료한다.
5. DB/storage batch가 모두 끝난 뒤 `storage/update-failed.json`, `storage/installed.lock`, `config/config.php`, `config/config-*.tmp.php`를 제거한다.

## 안전 기준

설치 초기화 실행 모드를 추가할 때는 다음 기준을 모두 지킨다.

- DB 삭제 대상은 allowlist와 DB introspection의 교집합으로만 정한다.
- 기본값으로 non-`sr_` table 또는 현재 설정의 안전한 table prefix 밖 table을 삭제하지 않는다.
- `config/config.php`와 `storage/installed.lock`은 DB와 storage 정리가 끝난 뒤 마지막에 제거한다.
- remote storage는 등록된 driver/key와 별도 preview/confirmation 없이 삭제하지 않는다.
- web request 하나로 무제한 삭제를 실행하지 않고 bounded batch와 retry 기준을 둔다.
- production-looking 환경은 기본 거부하고, 별도 flag와 owner reauth 없이는 실행하지 않는다.

## 후속 구현

다음 작업 단위에서 execution mode를 추가할 때는 confirmation phrase, reset lock, batch size, 실패 요약, idempotent retry를 함께 구현한다. 실행 뒤에는 `sr_is_installed()`가 false가 되고 HTTP root가 설치 화면으로 돌아와야 한다.
