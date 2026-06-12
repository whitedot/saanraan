# HTML Purifier 의존성 기록

이 파일은 산란 배포물에 포함하는 HTML Purifier vendor의 출처와 라이선스를 고정한다.

| 항목 | 값 |
| --- | --- |
| Composer package | `ezyang/htmlpurifier` |
| 포함 버전 | `v4.19.0` |
| upstream source | `https://github.com/ezyang/htmlpurifier.git` |
| source reference | `b287d2a16aceffbf6e0295559b39662612b77fcf` |
| upstream homepage | `http://htmlpurifier.org/` |
| license | `LGPL-2.1-or-later` |
| local license file | `vendor/ezyang/htmlpurifier/LICENSE` |
| local version file | `vendor/ezyang/htmlpurifier/VERSION` |

## 갱신 절차

1. 같은 버전을 재현해 배포물을 만들 때는 이 디렉터리에서 `composer install --no-dev --prefer-dist`를 실행한다.
2. HTML Purifier 버전을 올릴 때만 `composer update ezyang/htmlpurifier --no-dev --prefer-dist`를 실행한다.
3. `composer.lock`, `vendor/ezyang/htmlpurifier/VERSION`, `vendor/ezyang/htmlpurifier/LICENSE`를 확인한다.
4. 이 파일의 버전, source reference, license 값을 갱신한다.
5. `php .tools/bin/check-htmlpurifier-vendor-integrity.php`, `php .tools/bin/check-dependency-policy.php`, `php .tools/bin/check-rich-text-sanitizer.php`를 실행한다.
