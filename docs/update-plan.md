# 업데이트 및 스키마 버전 계획

Toycore는 프레임워크형 migration 클래스를 사용하지 않습니다. 대신 SQL 파일과 버전 테이블로 코어와 모듈의 설치/업데이트 상태를 추적합니다.

## 목표

- 현재 코어 스키마 버전 확인
- 현재 모듈 스키마 버전 확인
- 필요한 SQL 업데이트만 순차 실행
- 실패 시 어느 단계에서 실패했는지 확인 가능
- CLI 없이 관리자 화면에서 실행 가능

## 기본 테이블

코어 버전 기록:

```text
toy_schema_versions
```

권장 필드:

```text
id
scope
module_key
version
applied_at
```

예:

```text
scope = core
module_key = ''
version = 2026.04.001

scope = module
module_key = member
version = 2026.04.001
```

코어 버전 기록은 `module_key`에 `NULL`을 쓰지 않고 빈 문자열을 사용합니다. MySQL/MariaDB의 UNIQUE 인덱스에서 `NULL` 중복이 허용되는 문제를 피하기 위해 `scope`, `module_key`, `version`은 모두 비교 가능한 값으로 저장합니다.

## 파일 구조

코어 SQL:

```text
database/core/install.sql
database/core/updates/2026.04.001.sql
database/core/updates/2026.05.001.sql
```

모듈 SQL:

```text
modules/{module_key}/install.sql
modules/{module_key}/updates/2026.04.001.sql
modules/{module_key}/updates/2026.05.001.sql
```

## 업데이트 흐름

```text
1. 관리자 화면에서 업데이트 확인
2. 현재 core version 조회
3. database/core/updates 목록 확인
4. 아직 적용되지 않은 SQL만 순서대로 실행
5. 각 SQL 성공 시 toy_schema_versions 기록
6. 설치된 모듈별 업데이트 목록 확인
7. 모듈별 미적용 SQL 실행
8. 캐시 삭제
9. 완료 로그 기록
```

모듈 파일 업데이트와 DB 업데이트는 분리합니다. 새 모듈 파일을 zip, FTP, SFTP 등으로 `modules/{module_key}/`에 배치한 뒤, `/admin/updates`에서 미적용 SQL을 명시적으로 실행합니다. 설치 소스별 검증과 운영자 UX 보완 계획은 [모듈 설치 소스와 업데이트 보완 계획](module-update-and-source-plan.md)을 따릅니다.

## 실행 원칙

- SQL 파일명은 정렬 가능한 버전 형식 사용
- 이미 적용된 버전은 다시 실행하지 않음
- 업데이트 전 현재 버전을 화면에 표시
- 적용된 스키마 버전 목록을 관리자 화면에 표시
- 각 SQL 파일 실행 전후 감사 로그 기록
- 실패하면 이후 SQL 실행 중단
- 가능한 경우 DB 트랜잭션 사용
- 업데이트 파일 경로와 checksum을 적용 전에 검증
- 동시에 두 업데이트 요청이 실행되지 않도록 lock 사용
- lock 획득 후 pending update 목록을 다시 확인

## 저가형 웹호스팅 고려

모든 DB가 DDL 트랜잭션을 완전하게 지원하지 않을 수 있습니다. 따라서 업데이트 SQL은 가능한 한 작게 나눕니다.

권장:

- 한 파일에 너무 많은 변경을 넣지 않음
- 오래 걸리는 대량 데이터 변경은 피함
- 유니크 키를 추가하는 모듈 업데이트는 가능한 경우 기존 중복 데이터를 먼저 정리함
- 실패 시 수동 복구 안내를 남김
- 업데이트 전 DB 백업을 안내

## 업데이트 실패 후 재시도 가이드

현재 구현은 업데이트 실패 시 `storage/update-failed.json`에 실패한 scope, module key, version, checksum, 오류 요약을 기록합니다. 이 marker는 다음 업데이트 성공 시 삭제됩니다.

재시도 전 확인 순서:

```text
1. storage/update-failed.json 확인
2. 관리자 업데이트 화면의 적용된 스키마 버전 목록 확인
3. storage/logs/error.log와 감사 로그에서 실패한 version 확인
4. 실패한 SQL 파일이 배포 중 변경되지 않았는지 checksum 확인
5. DB 백업 상태 확인
```

재시도 기준:

- 실패한 version이 `toy_schema_versions`에 기록되지 않았다면 해당 SQL은 완료되지 않은 것으로 봅니다.
- DDL은 DB에 따라 rollback되지 않을 수 있으므로, 실패한 SQL이 일부 적용되었는지 직접 확인합니다.
- 일부 적용된 DDL이 있다면 같은 SQL을 그대로 재실행하기 전에 SQL을 idempotent하게 수정하거나 수동 복구 후 재시도합니다.
- checksum이 바뀐 SQL 파일은 같은 version으로 조용히 재적용하지 않습니다. 새 update version을 추가하거나 실패 원인을 문서화한 뒤 배포합니다.
- 비활성 모듈도 설치된 모듈이면 업데이트 대상에 포함될 수 있으므로, 비활성 상태라는 이유만으로 스키마 업데이트를 무시하지 않습니다.

재시도 중 지켜야 할 것:

- 업데이트 전 DB 백업을 다시 확인합니다.
- 실패 후 다음 update 파일을 수동으로 건너뛰지 않습니다.
- `toy_schema_versions`에 성공하지 않은 version을 임의로 넣지 않습니다.
- 복구가 끝나기 전 실패 marker와 로그를 삭제하지 않습니다.

## 관리자 화면

`admin` 모듈에서 업데이트 화면을 제공합니다.

표시 항목:

- 사용 가능한 코어 업데이트
- 설치된 모듈별 사용 가능한 업데이트
- 적용 대상 SQL 파일
- 적용 결과
- 실패 시 감사 로그

## 캐시 무효화

업데이트 후 다음 캐시를 삭제합니다.

```text
사이트 설정 캐시
활성 모듈 목록 캐시
번역 병합 캐시
모듈별 공개 콘텐츠 캐시
```

## 금지하는 방향

- PHP 클래스 기반 migration 필수화
- Artisan 같은 CLI 명령 필수화
- 적용된 SQL을 다시 실행
- 실패 후에도 다음 업데이트 계속 실행
- DB 백업 안내 없이 구조 변경 실행
