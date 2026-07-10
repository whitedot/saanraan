# 외부 의존성 배치 기준

이 문서는 산란이 외부 라이브러리와 vendored asset을 어떻게 포함하고 검증할지 정리한다. 목표는 공유호스팅 배포 가능성을 유지하면서, 보안상 검증된 라이브러리를 쓸 수 있는 경로를 열어 두는 것이다.

## 기본 원칙

- 루트 `LICENSE`의 MIT 조건은 산란이 직접 작성한 코드에 적용한다. vendored 제3자 파일은 원 저작자의 라이선스를 유지하며, 루트 MIT가 이를 재허가하거나 대체하지 않는다.
- 배포물의 제3자 구성요소 요약은 루트 `THIRD_PARTY_NOTICES.md`에 두고, 상세 버전·출처·무결성·갱신 절차는 각 모듈의 `README.md` 또는 `DEPENDENCY.md`에 둔다.
- 런타임 서버에 Composer 실행을 요구하지 않는다.
- 릴리스 zip은 배포에 필요한 파일을 이미 포함하거나, 해당 기능을 선택 의존으로 명확히 표시한다.
- 외부 라이브러리나 asset을 vendoring하면 버전, 출처, 라이선스, 갱신 절차를 같은 디렉터리의 README 또는 이 문서에 남긴다.
- vendor 내부 쓰기 권한을 요구하지 않는다. cache, log, temporary 파일은 `storage/` 아래에 둔다.
- 외부 의존성이 없어도 기본 설치, 회원, 관리자, 공개 화면이 치명적으로 깨지지 않아야 한다.
- 보안 라이브러리가 없는 fallback 경로는 보조 경로이며, 같은 payload fixture나 smoke 기준으로 계속 검증한다.

## 현재 vendored asset

| 대상 | 위치 | 성격 | 기준 |
| --- | --- | --- | --- |
| CKEditor 5 browser distribution | `modules/ckeditor/vendor/ckeditor5/` | CKEditor 플러그인의 직접 호스팅 asset | `modules/ckeditor/vendor/ckeditor5/README.md`에 버전, 출처, 라이선스를 기록하고, 릴리스 preflight에서 필수 asset과 라이선스 파일 포함 여부를 확인한다. |
| HTML Purifier | `modules/htmlpurifier/vendor/ezyang/htmlpurifier/` | rich text sanitizer 권장 선행 정화 라이브러리 | `modules/htmlpurifier/DEPENDENCY.md`에 버전, 출처, 라이선스를 기록하고, `.tools/bin/check-htmlpurifier-vendor-integrity.php`와 릴리스 preflight에서 vendor/metadata drift를 확인한다. |
| github-markdown-css 기반 stylesheet | `modules/markdown_editor/assets/github-markdown.css` | Markdown 본문 기본 stylesheet로 재구성한 정적 원본 | `modules/markdown_editor/DEPENDENCY.md`에 기준 버전, 출처, 라이선스와 산란 scope/token 변환 기준을 기록하고 `LICENSE.github-markdown-css`를 함께 배포한다. |

## HTML Purifier

HTML Purifier는 rich text HTML sanitizer의 권장 선행 정화 엔진이다. 다만 산란은 공유호스팅 배포를 지원하므로 런타임 Composer 필수 의존으로 만들지 않는다.

현재 코어 adapter는 다음 경로를 순서대로 감지한다.

```text
vendor/autoload.php
vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php
modules/htmlpurifier/vendor/autoload.php
modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php
```

1.0 기준 배치 결정:

1. 개발/릴리스 준비 환경에서 `modules/htmlpurifier/` 기준으로 `composer install --no-dev --prefer-dist`를 실행해 `composer.lock`에 고정된 `ezyang/htmlpurifier` 버전을 재현한다. 버전을 올릴 때만 `composer update ezyang/htmlpurifier --no-dev --prefer-dist`를 실행한다.
2. 기본 배포 zip에는 `modules/htmlpurifier/vendor/ezyang/htmlpurifier/`와 autoload 파일을 함께 포함한다.
3. 배포물에는 `modules/htmlpurifier/DEPENDENCY.md`, HTML Purifier의 라이선스 파일, 버전 파일을 포함한다.
4. Purifier cache는 vendor 내부가 아니라 `storage/cache/htmlpurifier`를 사용한다.
5. 루트 `vendor/` 감지는 개발/로컬 편의를 위한 보조 경로이며, 1.0 운영 배포 기준 경로는 `modules/htmlpurifier/`다.

현재 포함 기준:

- package: `ezyang/htmlpurifier`
- version: `v4.19.0`
- source: `https://github.com/ezyang/htmlpurifier.git`
- source reference: `b287d2a16aceffbf6e0295559b39662612b77fcf`
- license: `LGPL-2.1-or-later`
- local metadata: `modules/htmlpurifier/DEPENDENCY.md`
- local license file: `vendor/ezyang/htmlpurifier/LICENSE`
- local version file: `vendor/ezyang/htmlpurifier/VERSION`
- drift check: `.tools/bin/check-htmlpurifier-vendor-integrity.php`

운영 기준:

- HTML Purifier가 감지되면 `sr_sanitize_rich_text_html()`은 Purifier를 먼저 실행한 뒤 산란의 내부 allowlist canonicalizer를 다시 통과시킨다.
- HTML Purifier가 없으면 내부 DOM sanitizer fallback을 사용하지만, 운영 배포의 정상 상태는 Purifier 포함이다.
- 두 경로 모두 `.tools/bin/check-rich-text-sanitizer.php` payload fixture를 통과해야 한다.
- HTML Purifier를 포함한 릴리스 후보는 임시 Composer autoload 또는 실제 vendored autoload 상태에서 sanitizer fixture를 한 번 더 실행한다.

## 갱신 기준

외부 의존성을 추가하거나 버전을 바꿀 때는 다음을 확인한다.

- 라이브러리 이름, 버전, 출처 URL, 라이선스가 문서화되어 있는가
- vendored Composer metadata, `VERSION`, 런타임 클래스 버전, 라이선스 파일이 같은 버전을 가리키는가
- 배포 zip 포함/제외 기준이 배포 이슈나 릴리스 체크리스트에 반영되어 있는가
- 루트 `vendor/`는 기본 제외하고, 릴리스에 포함하기로 한 모듈 내부 vendor만 허용하는가
- 모듈 내부 vendor를 허용하더라도 `.env`, DB dump, SQLite/DB 파일, 백업/임시 파일, SSH key, package registry token 파일은 제외되는가
- cache/log/temp 쓰기 경로가 `storage/` 아래인가
- 기능 비활성 또는 라이브러리 부재 시 fallback이 명확한가
- 관련 정적 점검 또는 smoke가 `php .tools/bin/check.php`에 연결되어 있는가
- 릴리스 preflight 출력에 배포 asset의 버전, 라이선스 파일, dry-run manifest 포함 여부가 드러나는가

## 1.0 전 보강 대상

- 릴리스 후보에서 Purifier 로드 상태와 fallback 상태의 sanitizer fixture 결과를 함께 기록한다.
- 릴리스 후보에서 CKEditor self-hosted asset 버전, 라이선스 파일, dry-run manifest 포함 여부를 함께 기록한다.
