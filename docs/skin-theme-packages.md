# 스킨·테마 패키지 기준

이 문서는 마일스톤 34와 GitHub issue #379에서 확정한 외부 공개 테마와 모듈 스킨 패키지의 저장 위치, manifest 계약, 검증 기준을 정리한다. 패키지는 배포판 모듈을 직접 수정하지 않고 공개 화면 표시 구조를 확장하기 위한 기능이다.

## 저장 위치

외부 테마와 스킨은 저장소 루트의 `sr-packages/` 아래에 둔다.

```text
sr-packages/
- themes/
  - vendor.theme/
    - theme.json
    - layout.php
    - assets/
- skins/
  - community/
    - vendor_package/
      - skin.json
      - list.php
      - post.php
      - form.php
  - quiz/
    - vendor_package/
      - skin.json
  - survey/
    - vendor_package/
      - skin.json
```

`vendor.theme`은 외부 공개 테마 key가 되고, `vendor_package`는 모듈 스킨 key가 된다. 테마 key는 `common.*`, `core.*`, `common.basic`을 사용할 수 없다. 스킨 key는 설치된 모듈 아래 디렉터리명과 같은 `vendor_package` 형식이어야 한다.

## 신뢰 모델

스킨·테마 패키지는 sandbox가 아니라 애플리케이션 권한으로 include되는 특권 PHP 코드다. Manifest 검증은 경로, 파일 형식, 계약 버전, asset allowlist를 확인하지만 악성 PHP를 안전하게 실행해 주지는 않는다.

패키지 설치, 교체, 삭제는 arbitrary PHP 배포와 같은 수준으로 다룬다. 출처가 확인된 패키지만 배치하고, 운영 중 사용되는 패키지를 제거하기 전에는 `/admin/packages`의 참조 요약과 사이트/모듈 설정을 확인해 다른 기본값으로 바꾼다. 보안상 가능하면 `sr-packages/`를 웹 문서 루트 밖에 두는 배포 구조를 우선하고, 문서 루트 안에 둘 때는 `.htaccess` 또는 nginx 보호 규칙으로 직접 접근을 막는다.

## 테마 manifest

테마 패키지는 `sr-packages/themes/{vendor.theme}/theme.json`을 가진다.

```json
{
  "manifest_version": "1.0",
  "type": "theme",
  "key": "vendor.theme",
  "label": "Vendor Theme",
  "provider_label": "Vendor",
  "version": "1.0.0",
  "saanraan": {
    "min_version": "2026.06.001"
  },
  "theme_contract": "1.0",
  "supports": ["site", "content", "community", "quiz", "survey"],
  "views": {
    "home": "home.php"
  },
  "assets": {
    "theme_css": "assets/theme.css",
    "theme_js": "assets/theme.js",
    "preview": "assets/preview.png"
  }
}
```

필수 항목은 `manifest_version`, `type`, `key`, `saanraan.min_version`, `theme_contract`, `supports`다. `manifest_version`은 `1` 또는 `1.0`, `theme_contract`는 `1.0`만 허용한다. `supports`는 `site`, `content`, `community`, `quiz`, `survey` 도메인 단위만 허용하며 개별 route 단위 allowlist는 v1 계약에 넣지 않는다. 외부 테마 manifest에는 `provider_module_key`를 사용할 수 없다. `views.home`은 선택 항목이며 사이트 초기화면이 `/`일 때 레이아웃 기본 home view보다 먼저 사용된다.

테마는 `sr_public_theme_options()`에 정규화된 공개 테마 option으로 합쳐진다. 정규화된 option은 `source_type=external_theme`, `source_key`, `asset_owner=package`, `asset_owner_key`, `supports_domains`, `theme_contract`, `views`, `asset_ids`, `assets`를 가진다. 테마는 레이아웃 파일을 제공하지 않으며, 선택된 public layout shell과 모듈 body/skin 사이에서 CSS/JS와 선택적 초기화면 home view만 담당한다.

## 스킨 manifest

스킨 패키지는 `sr-packages/skins/{module_key}/{vendor_package}/skin.json`을 가진다. v1 대상 모듈과 필수 view는 다음과 같다.

| 모듈 | `saanraan.module_contract` | 필수 view |
| --- | --- | --- |
| `community` | `1.0` | `list`, `post`, `form` |
| `quiz` | `1.0` | `home`, `view`, `result` |
| `survey` | `1.0` | `home`, `view`, `complete` |

`content` 모듈 스킨 패키지는 v1 대상이 아니다.

```json
{
  "manifest_version": "1.0",
  "type": "skin",
  "key": "vendor_package",
  "module": "quiz",
  "label": "Vendor Quiz Skin",
  "version": "1.0.0",
  "saanraan": {
    "min_version": "2026.06.001",
    "module_contract": "1.0"
  },
  "views": {
    "home": "views/home.php",
    "view": "views/view.php",
    "result": "views/result.php"
  },
  "assets": {
    "skin_css": "assets/skin.css"
  }
}
```

스킨 view는 manifest의 view key로 매핑한다. 파일명은 view key와 같을 필요가 없지만, 경로는 패키지 내부의 상대 PHP 파일이어야 한다. 유효한 외부 스킨은 각 모듈의 내장 스킨 선택지에 합쳐지고, 잘못된 key나 누락 view는 기존 내장 `basic` fallback 기준을 따른다.

## 경로와 asset

View 경로와 asset 경로는 패키지 상대 경로만 허용한다. 절대 경로, `..`, dotfile, 역슬래시, control character, URL scheme은 거부된다. 패키지 안에는 숨김 파일과 `phtml`, `phar`, `sql`, shell/batch/exe 파일을 둘 수 없다.

Asset은 manifest에 선언된 id로만 노출된다. 허용 확장자는 `css`, `js`, `jpg`, `jpeg`, `png`, `gif`, `webp`다. 실제 URL은 `/sr-package-asset?type=theme|skin&key=...&module=...&asset=...&v=...` 형식으로 생성되며, 요청자는 raw file path를 넘길 수 없다. `v`가 manifest 검증 시 계산된 cache buster와 일치할 때만 long cache를 쓰고, 그렇지 않으면 재검증 경로로 응답한다.

## Fallback과 상태 점검

공개 route는 `site`, `content`, `community`, `quiz`, `survey` 도메인으로 분류된다. 선택된 레이아웃이 없는 경우, 유효하지 않은 경우, 현재 화면의 도메인을 지원하지 않는 경우에는 `common.basic`으로 fallback한다. `common.basic`은 terminal fallback이며 다시 다른 레이아웃으로 재귀 fallback하지 않는다.

사이트 설정 화면은 사이트 기본 공개 테마를, 콘텐츠/커뮤니티/퀴즈/설문 환경설정은 각 모듈 공개 테마를 별도 `theme_key`로 저장한다. `/admin/packages`는 외부 테마와 스킨 manifest 검증 결과, asset 목록, 미리보기 asset, 오류 그룹, 참조 요약, 레이아웃 fallback health를 read-only로 보여준다. 실제 적용은 사이트 설정 또는 각 모듈 환경설정의 테마/스킨 선택에서 한다.

## 검증

코드 변경 뒤 기본 점검을 실행한다.

```sh
php .tools/bin/check.php
```

스킨·테마 UI 계약을 직접 확인할 때는 다음 정적 검사를 함께 실행한다.

```sh
php .tools/bin/check-skin-theme-ui.php
```

로컬 또는 staging base URL이 있으면 `/sr-package-asset`이 raw path 없이 선언 asset만 응답하는지 확인한다. 샘플 패키지가 없는 환경에서는 관리자 `/admin/packages`가 빈 목록과 fallback health를 오류 없이 표시하는지 확인한다.
