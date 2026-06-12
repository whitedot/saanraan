# HTML Purifier 포함 배포 경로

이 디렉터리는 산란 rich text sanitizer가 HTML Purifier를 감지하는 공식 포함 배포 경로다. 코어는 런타임 Composer 실행을 요구하지 않으며, 배포물에 이 디렉터리의 `vendor/`를 포함해 `sr_sanitize_rich_text_html()`이 HTML Purifier를 선행 정화 엔진으로 사용하게 한다.

## 배포 기준

- 릴리스 준비 환경에서 이 디렉터리 기준으로 `composer install --no-dev --prefer-dist`를 실행한다.
- 배포물에는 `modules/htmlpurifier/vendor/autoload.php` 또는 `modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php`가 포함되어야 한다.
- 배포물에는 [DEPENDENCY.md](DEPENDENCY.md)의 license/source/version 기록이 포함되어야 한다.
- 배포물에는 `modules/htmlpurifier/DEPENDENCY.md`, `vendor/ezyang/htmlpurifier/LICENSE`, `vendor/ezyang/htmlpurifier/VERSION`이 포함되어야 한다.
- 운영 서버에서 Composer를 실행하지 않는다.
- Purifier cache는 vendor 내부가 아니라 `storage/cache/htmlpurifier`를 사용한다.

## fallback

개발 중 vendor가 아직 없거나 손상된 경우 산란은 내부 DOM sanitizer fallback을 사용한다. fallback 경로는 보조 경로이며, 운영 배포에서는 HTML Purifier 포함을 기준으로 한다.

## 검증

```sh
php .tools/bin/check-rich-text-sanitizer.php
php .tools/bin/check.php
```

HTML Purifier를 포함한 릴리스 후보는 실제 vendored autoload 상태에서 sanitizer fixture를 한 번 더 실행하고, fallback 상태의 fixture 결과도 함께 기록한다.
