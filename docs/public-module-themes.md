# 공개 모듈 테마 기준

이 문서는 마일스톤 34와 GitHub issue #385에서 정리한 공개 화면 테마, 레이아웃, 스킨의 저장 위치와 책임 경계를 설명한다.

외부 package 디렉터리, 외부 manifest, 외부 package asset handler는 사용하지 않는다. 공개 화면의 DOM을 바꾸는 테마는 각 화면을 소유한 core 또는 모듈 내부 디렉터리에 둔다.

## 저장 위치

초기화면과 공개 모듈의 DOM 교체형 테마는 다음 위치를 사용한다.

```text
core/views/theme/{theme_key}/home.php

modules/content/theme/{theme_key}/home.php
modules/content/theme/{theme_key}/group.php
modules/content/theme/{theme_key}/content.php
modules/content/theme/{theme_key}/ui-kit.php

modules/community/theme/{theme_key}/home.php
modules/community/theme/{theme_key}/group.php
modules/community/theme/{theme_key}/list.php
modules/community/theme/{theme_key}/post.php
modules/community/theme/{theme_key}/form.php
modules/community/theme/{theme_key}/search.php
modules/community/theme/{theme_key}/ui-kit.php

modules/quiz/theme/{theme_key}/home.php
modules/quiz/theme/{theme_key}/view.php
modules/quiz/theme/{theme_key}/result.php
modules/quiz/theme/{theme_key}/ui-kit.php

modules/survey/theme/{theme_key}/home.php
modules/survey/theme/{theme_key}/view.php
modules/survey/theme/{theme_key}/complete.php
modules/survey/theme/{theme_key}/ui-kit.php
```

모듈 공개 화면의 layout shell과 정적 asset도 같은 theme 디렉터리가 소유한다.

```text
assets/theme/{theme_key}.css
modules/{module_key}/theme/{theme_key}/layout.php
modules/{module_key}/theme/{theme_key}/assets/reset.css
modules/{module_key}/theme/{theme_key}/assets/common.css
modules/{module_key}/theme/{theme_key}/assets/ui-kit-layout.css
modules/{module_key}/theme/{theme_key}/assets/layout.css
modules/{module_key}/theme/{theme_key}/assets/module.css
modules/{module_key}/theme/{theme_key}/assets/theme.css
```

`theme_key`는 lowercase letters, digits, `_`만 사용하는 로컬 key다. `basic`은 배포판 기본 theme이며, 기존 `default` 값은 호환을 위해 `basic`으로 해석한다. `sample`은 DOM과 스타일 차이를 확인하기 위한 샘플 theme다.

## 책임 경계

- `theme_key`: 화면 소유 모듈의 사용자 공개 화면 DOM과 theme별 asset 선택.
- `layout_key`: 공개 화면 shell 선택. header, footer, 메뉴 슬롯, layout shell CSS/JS를 포함한다.
- `skin_key`: 커뮤니티/퀴즈/설문처럼 모듈 내부 기능 단위의 출력 템플릿 선택.

관리자 화면은 이 공개 theme 체계의 적용 대상이 아니다. 관리자 shell과 관리자 theme은 admin 모듈이 별도로 소유한다.

콘텐츠는 v1에서 skin 대상이 아니며 `theme_key`와 `layout_key`로만 공개 화면을 바꾼다. 커뮤니티, 퀴즈, 설문은 내부 theme view가 없을 때에만 기존 내장 skin view로 fallback한다.

## 레이아웃과 asset 순서

선택된 layout provider와 화면 소유 모듈은 서로 다를 수 있다. 예를 들어 커뮤니티 화면이 `content.basic` layout을 선택하면 layout shell과 layout stylesheet는 content provider theme가 소유하고, 게시글 본문 DOM과 module/theme asset은 community theme가 소유한다.

서비스 모듈의 공개 layout context는 화면 소유 모듈을 나타내는 `module_home_url`, `module_label`, `module_menu_label`을 함께 전달한다. 따라서 layout shell의 CSS class, layout script, logo provider key, banner layout slot은 선택된 provider namespace를 유지하되, header에 표시되는 모듈명과 모듈 홈 링크, 메뉴 접근성 label은 현재 화면 소유 모듈 기준으로 렌더링한다.

기본 호출 순서는 다음과 같다.

1. 화면 소유 모듈 theme `assets/reset.css`
2. 화면 소유 모듈 theme `assets/common.css`
3. 선택된 layout provider theme `layout.php`
4. 선택된 layout provider theme `assets/layout.css`
5. 화면 소유 모듈 theme `assets/module.css`
6. 화면 소유 모듈 theme `assets/theme.css`
7. layout provider `assets/layout.js`
8. 화면 소유 모듈 `assets/module.js`

모듈 UI-KIT 화면에서는 같은 theme의 `assets/ui-kit-layout.css`를 추가한다.

## 레이아웃 지원 범위

공개 layout option은 `site`, `content`, `community`, `quiz`, `survey` 도메인과 `content.home`, `community.post`, `quiz.result`, `survey.complete` 같은 화면 target을 선언할 수 있다.

콘텐츠, 커뮤니티, 퀴즈, 설문 환경설정의 단일 `layout_key`는 해당 모듈의 필수 공개 화면 전체에 적용된다. 따라서 후보 layout은 그 모듈의 필수 target 전체를 지원해야 한다. 번들 `content.basic`, `community.basic`, `quiz.basic`, `survey.basic`은 네 공개 모듈 target 전체를 지원하므로 각 모듈 설정에서 다른 모듈 layout도 선택할 수 있다.

현재 화면 target을 지원하지 않는 layout은 공개 렌더링에서 `common.basic`으로 fallback한다. `common.basic`은 terminal fallback이며 다시 다른 layout으로 재귀 fallback하지 않는다.

## 배포 보호

정적 웹 접근은 공개 asset 디렉터리만 허용한다. `modules/{module_key}/theme/{theme_key}/assets/` 아래 CSS/JS/image/font 등 공개 asset은 허용하고, PHP view 파일과 모듈 내부 파일은 직접 열리지 않아야 한다.

Apache 환경은 저장소의 `.htaccess`를 사용한다. nginx 환경은 `docs/deployment/nginx-saanraan.conf` 또는 `docs/deployment/nginx-saanraan-subdirectory.conf`의 module theme asset 허용 규칙을 반영한다.

## 검증

코드 변경 뒤 기본 점검을 실행한다.

```sh
php .tools/bin/check.php
```

공개 theme와 layout 경계를 직접 확인할 때는 다음 정적 검사를 함께 실행한다.

```sh
php .tools/bin/check-skin-theme-ui.php
```

로컬 또는 staging base URL이 있으면 `/content/ui-kit`, `/community/ui-kit`, `/quiz/ui-kit`, `/survey/ui-kit`에서 선택 theme와 선택 layout provider 조합이 실제 asset과 DOM marker로 나타나는지 확인한다.
