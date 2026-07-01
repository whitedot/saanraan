# 설치 초기화 기준

이 문서는 설치 완료 상태를 되돌려 새 설치 화면으로 돌아가기 위한 기준을 정리한다. QA 더미 데이터를 비우고 다시 채우는 사이트 초기화와 다르며, 더미 데이터 기준은 [사이트 초기화와 더미 데이터 기준](site-reset-and-fixtures.md)에 둔다.

## 현재 표면

설치 초기화는 고위험 작업이므로 먼저 CLI preview만 제공한다.

```sh
php .tools/bin/install-reset.php --preview
php .tools/bin/install-reset.php --preview --json
```

CLI preview는 read-only로 동작한다. 실행 모드는 아직 구현하지 않는다. destructive 실행을 추가하기 전까지 이 명령은 DB table, 파일, 설정을 삭제하지 않는다.

## Preview 범위

Preview는 `config/config.php`와 `storage/installed.lock`의 존재와 읽기 가능 여부를 표시한다. 두 파일이 모두 없으면 이미 초기 설치 상태로 본다. `config/config.php`가 있지만 현재 CLI 사용자에게 읽히지 않으면 DB 접속 정보를 확인할 수 없으므로 실패로 종료한다.

DB 후보는 repository SQL에서 만든 allowlist와 현재 DB introspection 결과의 교집합만 사용한다. allowlist는 `database/core/install.sql`, `database/core/updates/*.sql`, `modules/*/install.sql`, `modules/*/updates/*.sql`의 `CREATE TABLE` 선언에서 `sr_` table 또는 `{{SR_TABLE_PREFIX}}` table만 수집한다. 현재 설치 설정에 안전한 table prefix가 있으면 그 prefix로 변환한다.

Preview는 다음 값을 보여준다.

- allowlist table 수
- 현재 DB의 prefixed table 수
- allowlist와 DB introspection 교집합에 있는 target table 수
- target table row 수 합계
- prefixed table이지만 allowlist에 없어 삭제 후보에서 제외된 table 수

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
