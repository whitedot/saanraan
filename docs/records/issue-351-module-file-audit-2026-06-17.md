# 이슈 351 모듈 파일 잔존 검토

검토일: 2026-06-17

## 범위

- `modules/` 아래 PHP, CSS, JavaScript, SQL, Markdown, JSON 파일을 대상으로 파일명 직접 참조와 모듈 요청 흐름의 동적 로딩 규칙을 대조했다.
- `modules/htmlpurifier/vendor/`, `modules/ckeditor/vendor/`는 패키지 벤더 영역이므로 개별 파일 삭제 후보로 보지 않고 패키지 보존 근거만 확인했다.
- 이 이슈의 기본 범위에 맞춰 파일 삭제는 수행하지 않고, 삭제 후보와 유지 근거를 기록한다.

## 확인한 로딩 규칙

- 모듈 루트 계약 파일인 `module.php`, `paths.php`, `install.sql`, `admin-menu.php`, `helpers.php`, `menu-links.php`, `dashboard.php`, `privacy-export.php`, `privacy-cleanup.php` 등은 코어와 관리자 화면이 모듈 키와 파일명 규칙으로 읽는다.
- 라우트 대상 `actions/*.php`와 화면 `views/*.php`, `skins/*/*.php`, `layouts/*/*.php`는 각 모듈의 `paths.php`, action include, layout context에서 명시적으로 연결된다.
- 모듈 update SQL은 `core/helpers/schema-updates.php`의 `glob($directory . '/*.sql')` 흐름과 `core/helpers/sql.php`의 설치 버전 기록 흐름에서 디렉터리 단위로 읽는다. 파일명이 코드에 직접 나오지 않아도 유지 대상이다.
- 관리자와 공개 UI kit 샘플 파일은 각 `views/ui-kit.php`가 `$sampleKey`로 `views/ui-kit-samples/{sampleKey}.php`를 조합해 include한다. 파일명이 직접 검색되지 않아도 유지 대상이다.
- 공개 layout/module CSS와 JavaScript는 `sr_public_layout_begin()` context, module helper, layout template에서 명시 경로로 추가된다.

## 유지 필요 파일

| 구분 | 파일군 | 판단 |
| --- | --- | --- |
| 모듈 계약 파일 | `modules/*/{module.php,paths.php,install.sql}` 등 | 코어 설치, 업데이트, 라우팅, 관리자 모듈 목록에서 규칙 기반으로 사용한다. |
| 업데이트 SQL | `modules/*/updates/*.sql` | `sr_schema_update_files()`와 설치 버전 동기화 흐름에서 glob으로 사용한다. |
| UI kit 샘플 | `modules/{admin,content,community,quiz,survey}/views/ui-kit-samples/*.php` | 각 모듈 `views/ui-kit.php`가 `$sampleKey` 기반으로 include한다. |
| 벤더 패키지 | `modules/htmlpurifier/vendor/`, `modules/ckeditor/vendor/` | 패키지 무결성 및 self-hosted asset smoke 대상이다. 개별 파일 정리는 패키지 갱신 이슈에서 다루는 편이 안전하다. |
| 자산/템플릿 | `assets/*.css`, `assets/*.js`, `skins/*/*.php`, `layouts/*/*.php` | module helper, layout context, skin action에서 명시 로드된다. |

## 삭제 가능 후보

| 파일 | 근거 | 권장 후속 |
| --- | --- | --- |
| `modules/community/views/partials.php` | 파일 내용이 `declare(strict_types=1);`뿐이며 함수, HTML, side effect가 없다. `rg "partials"`와 `rg "partials.php"`에서 include/require/경로 조합 사용처가 없다. 같은 이름의 partial 파일도 다른 모듈에 없다. | 별도 삭제 작업에서 제거하고 `php .tools/bin/check.php` 및 HTTP smoke test로 커뮤니티 공개/관리자 화면을 확인한다. |

## 판단 보류

- 없음. 단, 벤더 패키지 내부 파일은 패키지 무결성 단위로 유지 판단했으므로, 개별 벤더 파일 정리는 이 검토의 삭제 후보로 분리하지 않았다.

## 검증 명령

- `find modules -type f ! -path '*/vendor/*'`
- `rg -n "updates|glob\\(|scandir\\(|version_compare" core modules/admin modules/*/module.php modules/*/paths.php modules/*/helpers.php -g '*.php'`
- `rg -n "ui-kit-samples|sampleKey|partials\\.php|partials" modules/admin/views modules/community modules/content modules/quiz modules/survey -g '*.php'`
- `php .tools/bin/check.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:8050 php .tools/bin/smoke-http.php`
