# 릴리스 게이트 점검 기록 - 2026-05-31

## 점검 배경

프로젝트 진행 상황 평가 중 전체 PHP 문법 검사는 통과했지만 `.tools/bin/check.php`가 커뮤니티 릴리스 점검에서 실패했다.

실패 항목:

```text
Community admin settings policy must contain: sr_community_update_level_min_scores($pdo, $minScoresById)
```

## 조치

실제 구현은 `modules/community/actions/admin-settings.php`에서 `sr_community_update_level_min_scores($pdo, $minScoresById, $settings)`를 호출하고, 함수는 `modules/community/helpers/levels.php`에 존재한다. 실패 원인은 기능 누락이 아니라 릴리스 점검 스크립트가 정확한 2인자 호출 문자열만 찾는 기준이었다.

`.tools/bin/check-community-release.php`의 검사 문자열을 `sr_community_update_level_min_scores($pdo, $minScoresById`로 조정해 3번째 인자 전달을 허용하면서도 해당 helper 호출 여부는 계속 확인하게 했다.

추가 릴리스 스캔에서 콘텐츠 자산 deadlock 재시도 점검이 `sr_content_charge_view_access_once()`와 `sr_content_charge_file_download_once()`의 현재 시그니처를 허용하지 않아 실패했다. 점검 스크립트를 정규식 기반으로 조정해 `accountId` 이후의 선택 인자를 허용하되, 재시도 wrapper와 retryable 예외 처리 호출 수는 계속 확인한다.

같은 스캔에서 콘텐츠 완료 버튼의 0원 그룹 정책 경로가 로그 placeholder 생성과 완료 처리를 트랜잭션 경계 없이 수행하는 것을 확인했다. `sr_content_run_asset_action_once()`의 0원 처리 경로도 다른 콘텐츠 자산 처리와 동일하게 트랜잭션으로 감싸고, retryable 트랜잭션 예외는 상위 `sr_content_asset_retry_operation()`으로 다시 던지도록 보강했다.

로컬 HTTP 스모크 중 `storage/logs/error.log` 권한이 현재 CLI 실행 사용자와 맞지 않아 로그 쓰기 실패가 fatal로 전파되고 원래 오류를 가리는 것도 확인했다. `sr_log_exception()`은 로그 디렉터리 생성 또는 파일 쓰기에 실패해도 에러 렌더링을 깨뜨리지 않고 PHP 기본 `error_log()`로 최소 폴백 메시지를 남기도록 조정했다.

## 검증

```text
php .tools/bin/check.php
saanraan checks completed.
```

전체 PHP 문법 검사는 별도로 실행해 통과를 확인했다.

추가 점검에서도 다음 검증을 통과했다.

```text
php .tools/bin/check-asset-deadlock-retry.php
asset deadlock retry checks completed.

php .tools/bin/check.php
saanraan checks completed.
```

개발용 라우터로 PHP 내장 서버를 `127.0.0.1:8081`에서 실행한 뒤 HTTP 스모크도 확인했다. `8080`은 이미 사용 중이어서 사용하지 않았다.

```text
SR_SMOKE_BASE_URL=http://127.0.0.1:8081 php .tools/bin/smoke-http.php
saanraan HTTP smoke checks completed.

SR_SMOKE_BASE_URL=http://127.0.0.1:8081 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php
saanraan HTTP smoke checks completed.
```

이후 같은 로컬 작업 디렉터리에서 HTTP 스모크를 재실행했을 때는 모든 동적 경로가 500을 반환했다. 서버 로그상 원인은 코드 문법 오류가 아니라 무시된 로컬 설정 파일 `config/config.php`의 DB 접속 실패였다.

```text
PDOException: SQLSTATE[HY000] [1045] Access denied for user 'lab'@'localhost'
```

이 실패는 현재 로컬 DB 자격 증명과 저장소 코드 검증을 분리해 기록한다. 릴리스 후보 판정 전에는 유효한 DB 설정 또는 신규 설치 환경에서 HTTP 스모크를 다시 실행해야 한다.

## 남은 확인

1.0 릴리스 후보 판정에는 정적 점검만으로 충분하지 않다. 새 설치, 관리자 주요 화면, 선택 모듈 설치/업데이트, 공개/회원/커뮤니티/콘텐츠/자산 흐름의 HTTP 및 브라우저 수동 점검 기록이 추가로 필요하다.
