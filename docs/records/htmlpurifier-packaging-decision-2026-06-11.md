# HTML Purifier 배포 방식 결정 - 2026-06-11

## 결정

HTML Purifier는 1.0 기준에서 루트 필수 Composer 의존성이 아니라 `modules/htmlpurifier/vendor/`에 포함해 배포한다. 코어 adapter는 루트 vendor 경로도 개발/로컬 편의를 위해 감지하지만, 릴리스 후보와 운영 배포의 기준 경로는 `modules/htmlpurifier/vendor/`로 둔다.

## 이유

- 산란은 저가형 공유호스팅을 지원하므로 운영 서버에서 Composer 실행을 필수로 만들지 않는다.
- CKEditor처럼 기능 소유 영역 가까이에 vendored asset과 license/source/version 기록을 두는 패턴이 이미 있다.
- rich text HTML을 운영하는 기본 배포물은 HTML Purifier를 포함하고, 개발 중 vendor가 없을 때만 내부 fallback sanitizer로 동작한다.
- 코어 API는 `sr_sanitize_rich_text_html()`로 유지해 콘텐츠, 커뮤니티, 알림, 팝업레이어가 배포 방식 차이를 알 필요가 없게 한다.

## 구현 기준

- `modules/htmlpurifier/composer.json`과 `composer.lock`은 `ezyang/htmlpurifier` 버전을 고정하는 준비 파일이다.
- 릴리스 준비자는 `modules/htmlpurifier/`에서 Composer install을 실행한 뒤 `vendor/`, 라이선스 파일, 버전 파일을 포함한 배포물을 만든다.
- HTML Purifier가 감지되면 코어 sanitizer가 Purifier를 먼저 실행하고, 내부 allowlist canonicalizer를 다시 통과시킨다.
- HTML Purifier가 없으면 내부 DOM sanitizer fallback을 사용한다.
- 두 경로 모두 `.tools/bin/check-rich-text-sanitizer.php`를 통과해야 한다.

## 남은 일

- 릴리스 패키지 빌드 절차에서 `modules/htmlpurifier/vendor/` 포함 여부를 검사한다.
- HTML Purifier license/source/version 파일을 릴리스 검증 기록에 함께 기록한다.
- Purifier 포함 상태와 fallback 상태의 sanitizer fixture 결과를 릴리스 검증 기록에 함께 남긴다.
