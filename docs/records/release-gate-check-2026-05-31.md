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

## 검증

```text
php .tools/bin/check.php
saanraan checks completed.
```

전체 PHP 문법 검사는 별도로 실행해 통과를 확인했다.

개발용 라우터로 PHP 내장 서버를 `127.0.0.1:8081`에서 실행한 뒤 HTTP 스모크도 확인했다. `8080`은 이미 사용 중이어서 사용하지 않았다.

```text
SR_SMOKE_BASE_URL=http://127.0.0.1:8081 php .tools/bin/smoke-http.php
saanraan HTTP smoke checks completed.

SR_SMOKE_BASE_URL=http://127.0.0.1:8081 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php
saanraan HTTP smoke checks completed.
```

## 남은 확인

1.0 릴리스 후보 판정에는 정적 점검만으로 충분하지 않다. 새 설치, 관리자 주요 화면, 선택 모듈 설치/업데이트, 공개/회원/커뮤니티/콘텐츠/자산 흐름의 HTTP 및 브라우저 수동 점검 기록이 추가로 필요하다.
