# 페이지 모듈 구현 계획

이 문서는 산란에서 단일 페이지를 작성하고 공개하는 `page` 모듈의 구현 계획이다.

문서 수명:

- 페이지 모듈을 구현하기 전까지 계획 문서로 보관한다.
- 실제 구현과 검증이 완료되면 이 문서는 삭제한다.
- 구현 후 계속 유지해야 하는 기준은 `docs/module-guide.md`, `docs/core-decisions.md`, 페이지 모듈 README 중 필요한 곳으로만 옮긴다.

## 기본 방향

페이지는 코어가 아니라 선택 모듈에서 처리한다.

권장 모듈명은 `page`다.

- `core`: 요청 진입, DB 연결, 보안 helper, public layout, output slot 기반만 제공한다.
- `page`: 페이지 데이터, 공개 URL, 관리자 화면, sitemap 후보, 사이트 메뉴 후보를 소유한다.
- `site_menu`: 운영자가 메뉴에 어떤 페이지를 노출할지만 결정한다.
- `seo`: `page` 모듈이 제공하는 sitemap 후보를 읽어 sitemap을 생성한다.
- `ckeditor` 같은 에디터 플러그인: 설치된 경우에만 페이지 본문 입력 UI를 보강한다.

이 구조는 코어가 페이지 본문, URL 정책, 게시 상태, SEO 판단을 직접 소유하지 않게 한다.

## 1차 구현 범위

포함한다:

- 페이지 목록
- 페이지 생성, 수정, 삭제
- 공개/비공개/draft 상태
- slug 기반 공개 URL
- 제목, 요약, 본문
- 메뉴 후보 제공
- sitemap 후보 제공
- 관리자 감사 로그
- 작성자/수정자 기록
- 서버 side XSS 방어 기준

포함하지 않는다:

- 페이지 빌더
- 블록 편집기
- 다단계 승인 workflow
- 예약 발행
- 페이지별 레이아웃 선택
- 페이지별 권한 정책
- 댓글, 좋아요, 첨부파일
- 버전 비교 UI
- 코어 공통 permalink router

## URL 정책

1차 공개 URL은 충돌을 줄이기 위해 prefix를 둔다.

```text
/pages/{slug}
```

예:

```text
/pages/about
/pages/terms
/pages/privacy-policy
```

`/{slug}` 형태의 루트 페이지 URL은 1차에서 도입하지 않는다.

이유:

- 기존 모듈 route와 충돌할 수 있다.
- 코어가 catch-all router처럼 똑똑해지는 압력이 생긴다.
- 명시적 `paths.php` 흐름과 충돌 검증을 단순하게 유지할 수 있다.

루트 URL이 꼭 필요해지면 이후 단계에서 별도 `permalink` 선택 모듈이나 page 모듈 내부의 제한된 allowlist 방식으로 검토한다.

## 처리 흐름

관리자 작성 흐름:

```text
관리자
-> 페이지 목록
-> 새 페이지
-> 제목, slug, 요약, 본문, 상태 입력
-> 저장
-> 공개 URL 확인
```

공개 요청 흐름:

```text
GET /pages/{slug}
-> page paths.php에서 action include
-> slug 정규화
-> 공개 상태 확인
-> SEO 값 구성
-> public layout으로 출력
```

## 모듈 구조

예상 구조:

```text
modules/page/
- module.php
- helpers.php
- paths.php
- admin-menu.php
- menu-links.php
- sitemap.php
- install.sql
- actions/
  - view.php
  - admin-pages.php
  - admin-page-new.php
  - admin-page-edit.php
  - admin-page-save.php
  - admin-page-delete.php
- views/
  - page.php
  - admin-pages.php
- skins/
  - basic/
    - page.php
- updates/
```

관리자 메뉴 자산 분류는 `module.php`의 `admin` 메타데이터로 처리한다.

권장 분류:

```php
'admin' => [
    'category' => 'site',
    'category_label' => '사이트 구성',
    'module_label' => '페이지',
    'menu_order' => 30,
],
```

## 데이터 저장 계획

`page` 모듈이 자기 테이블을 소유한다.

예상 테이블:

- `sr_pages`
- `sr_page_revisions`

`sr_pages`:

- `id`: 페이지 ID
- `slug`: 공개 URL slug
- `title`: 페이지 제목
- `summary`: 짧은 설명
- `body_text`: 본문
- `body_format`: `plain`, `html`
- `status`: `draft`, `published`, `hidden`
- `seo_title`: SEO 제목 override
- `seo_description`: SEO 설명 override
- `created_by`: 작성 관리자 account_id
- `updated_by`: 마지막 수정 관리자 account_id
- `published_at`: 공개 시각
- `created_at`
- `updated_at`

`sr_page_revisions`:

- `id`
- `page_id`
- `title`
- `summary`
- `body_text`
- `body_format`
- `status`
- `created_by`
- `created_at`

1차 revision은 복원 UI보다 변경 이력 보관과 사고 대응을 목적으로 둔다. 복원 UI는 이후 단계로 미룬다.

## slug 규칙

slug는 페이지 모듈 안에서 검증한다.

허용:

```text
\A[a-z0-9][a-z0-9-]{1,118}[a-z0-9]\z
```

제약:

- 전체 길이 3-120자
- 소문자 영문, 숫자, 하이픈만 허용
- 앞뒤 하이픈 금지
- 중복 slug 금지
- `admin`, `login`, `logout`, `account`, `community`, `pages` 같은 예약어는 금지

공개 path는 `/pages/` prefix 뒤에 slug를 붙인다.

## 본문 형식과 XSS 방어

1차 기본값은 plain text다.

```text
body_format = plain
```

HTML 본문은 CKEditor 같은 에디터 플러그인과 sanitizer 정책이 준비된 뒤 허용한다.

1차 plain text 출력:

- 저장 시 원문 text를 보관한다.
- 출력 시 `sr_e()`로 escape한다.
- 줄바꿈은 view에서 안전하게 변환한다.

HTML을 도입할 때 필요한 기준:

- 허용 태그/속성 allowlist
- `javascript:` URL 차단
- inline event handler 차단
- 이미지 URL 정책
- 저장 전 또는 출력 전 sanitizer
- 보안 체크리스트 반영

## 관리자 화면

예상 화면:

- `/admin/pages`
- `/admin/pages/new`
- `/admin/pages/{id}/edit`

목록 항목:

- 제목
- slug
- 상태
- 공개 URL
- 작성자
- 수정일
- 공개일

입력 항목:

- 제목
- slug
- 요약
- 본문
- 상태
- SEO 제목
- SEO 설명

상태 동작:

- `draft`: 관리자에서만 확인 가능
- `published`: 공개 URL 응답
- `hidden`: 기존 공개 URL도 404 또는 비공개 안내

삭제는 1차에서 hard delete 대신 `hidden` 전환을 기본으로 검토한다. 실제 hard delete가 필요하면 재인증과 감사 로그를 요구한다.

## 사이트 메뉴 연동

`page` 모듈은 `menu-links.php`를 제공한다.

역할:

- 공개 상태의 페이지를 사이트 메뉴 후보로 제공한다.
- 후보 label은 페이지 제목을 사용한다.
- 후보 URL은 `/pages/{slug}`를 사용한다.

주의:

- `page` 모듈이 메뉴 항목을 자동 생성하지 않는다.
- 최종 메뉴 구성은 `site_menu` 관리자 화면에서 운영자가 결정한다.
- 페이지가 숨김 처리되면 메뉴 후보에서 제외한다.

## SEO와 sitemap

`page` 모듈은 `sitemap.php`를 제공한다.

포함 조건:

- `status = published`
- slug가 유효함
- 공개 URL이 존재함

SEO 값:

- 기본 title은 페이지 제목
- 기본 description은 요약
- `seo_title`, `seo_description`이 있으면 우선 사용
- canonical은 `/pages/{slug}`

SEO 판단은 page 모듈 안에서 구성하고, core는 출력 helper만 제공한다.

## 권한과 보안

관리자 action은 다음 기준을 따른다.

- 로그인 확인
- 관리자 role 확인
- POST action CSRF 검증
- 상태 변경 감사 로그
- slug 중복과 예약어 검증
- 출력 escape

권장 권한:

- 1차는 기존 관리자 role 기준으로 시작한다.
- 페이지 전용 권한이 필요해지면 `page.manage` 같은 모듈 소유 권한으로 확장한다.

## 개인정보와 보관

페이지 모듈은 회원 개인정보를 직접 수집하지 않는다.

개인정보와 연관될 수 있는 값:

- 작성자/수정자 account_id
- 감사 로그

처리 기준:

- `privacy-export.php`는 1차에서 제공하지 않는다.
- 작성자/수정자 account_id는 관리자 운영 이력으로 보관한다.
- 공개 본문에 개인정보가 입력되는 것은 운영 콘텐츠 문제이며, page 모듈은 입력값을 자동으로 개인정보로 분류하지 않는다.

## 검증 계획

수동 검증:

- 페이지 생성
- slug 중복 차단
- 예약어 slug 차단
- draft 페이지 공개 접근 차단
- published 페이지 공개 접근
- hidden 페이지 공개 접근 차단
- 메뉴 후보 노출 확인
- sitemap 포함 확인
- SEO title/description 출력 확인
- 삭제 또는 숨김 처리 감사 로그 확인

정적 검증:

- 관리자 POST action의 CSRF 호출 확인
- 관리자 action의 로그인/권한 호출 확인
- 직접 `exit`/`die` 사용 여부 확인
- 사용자 입력 출력 escape 확인

## 구현 순서

1. `modules/page` 기본 구조와 `module.php` 작성
2. `install.sql`과 기본 테이블 작성
3. `paths.php`, 공개 view action 작성
4. 관리자 목록/작성/수정/저장 action 작성
5. `admin-menu.php`와 관리자 자산 분류 연결
6. `menu-links.php`로 사이트 메뉴 후보 제공
7. `sitemap.php`로 SEO sitemap 후보 제공
8. 감사 로그와 상태 변경 검증 보강
9. 스모크 테스트와 보안 체크리스트 검증
10. 구현 완료 후 이 계획 문서를 삭제하고 필요한 기준만 유지 문서로 옮긴다.

## 보류 결정

- 루트 permalink는 1차에서 제외한다.
- HTML 본문 저장은 sanitizer 기준이 준비된 뒤 허용한다.
- 페이지별 레이아웃 선택은 전역 public layout 원칙과 충돌하므로 제외한다.
- 첨부파일과 이미지 업로드는 `ckeditor` 또는 파일 소유 모듈과 함께 별도 검토한다.
