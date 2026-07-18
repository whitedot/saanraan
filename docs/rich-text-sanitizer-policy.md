# Rich Text Sanitizer 정책

이 문서는 saanraan이 `body_format=html` 본문을 저장하거나 출력할 때 허용하는 HTML 경계를 정리한다. 목적은 CKEditor나 브라우저가 만든 HTML을 신뢰하지 않고, 서버 allowlist를 기준으로 같은 결과를 만들도록 유지하는 것이다.

## 적용 대상

현재 기준 대상:

- 공통 rich text sanitizer: `sr_sanitize_rich_text_html()`
- 커뮤니티 게시글 sanitizer: `sr_community_sanitize_post_html()`
- 커뮤니티 게시판 설명 sanitizer: `sr_community_sanitize_board_description_html()`
- CKEditor 플러그인으로 강화된 textarea의 HTML 저장
- 콘텐츠, 커뮤니티 게시글, 팝업레이어처럼 기존 HTML 본문을 복사해 새 레코드를 만드는 경로
- URL 임베드 helper가 공개 렌더링 시점에 해석할 안전한 URL 또는 링크가 포함된 본문 HTML

공통 rich text sanitizer는 `script`, `style`, `iframe`, `object`, `embed`, `form`, `meta` 같은 hard-drop 컨테이너를 먼저 제거한다. 그다음 HTML Purifier가 있으면 1차 정화를 실행하고, 이후 내부 allowlist canonicalizer를 한 번 더 통과한다. HTML Purifier가 없으면 내부 DOM 기반 fallback을 사용한다. 커뮤니티 게시글 sanitizer는 모듈 경계용 wrapper만 유지하고 실제 정화는 공통 sanitizer에 위임한다. 두 경로 모두 같은 fixture를 통과해야 한다.

## HTML Purifier 설정 경계

HTML Purifier adapter는 내부 canonicalizer를 대체하지 않는다. 공통 sanitizer는 hard-drop 컨테이너를 먼저 제거한 뒤 Purifier를 1차 정화와 HTML 파싱 보강으로 사용하고, 최종 저장/출력 형태는 산란 allowlist canonicalizer가 다시 결정한다.

현재 Purifier 설정 기준:

- `HTML.Allowed`는 산란 rich text allowlist와 같은 태그/속성 경계로 제한한다.
- `HTML.DefinitionID`는 산란 rich text 전용 ID를 사용하고, `HTML.DefinitionRev`는 allowlist 변경 때 올려 serializer cache가 예전 정의를 재사용하지 않게 한다.
- `URI.AllowedSchemes`는 `http`, `https`만 허용한다. 최종 canonicalizer는 이미지 `src`에서 외부 `http://`를 다시 제거한다.
- `HTML.Nofollow`는 켜고 `HTML.TargetBlank`는 끈다.
- `Cache.SerializerPath`는 `storage/cache/htmlpurifier` 아래만 사용한다.
- cache 디렉터리를 사용할 수 없으면 `Cache.DefinitionImpl`을 비활성화하고 vendor 내부 쓰기를 요구하지 않는다.

## 허용 태그와 속성

서버 allowlist는 다음 태그와 속성만 허용한다.

| 태그 | 허용 속성 | 용도 |
| --- | --- | --- |
| `p` | 제한된 `style` | 문단, 정렬, 들여쓰기 |
| `br` | 없음 | 줄바꿈 |
| `strong` | 없음 | 굵게 |
| `em` | 없음 | 기울임 |
| `u` | 없음 | 밑줄 |
| `s` | 없음 | 취소선 |
| `span` | 제한된 `style` | 글자 크기, 글자색, 배경색 |
| `blockquote` | 없음 | 인용 |
| `ul`, `ol`, `li` | 없음 | 목록 |
| `a` | `href` | 링크 |
| `h1`, `h2`, `h3`, `h4` | 제한된 `style` | 본문 안 제목, CKEditor 제목 3, 정렬, 들여쓰기 |
| `img` | `src`, `alt`, `width`, `height` | 본문 이미지 |
| `figure` | 제한된 `class` | CKEditor 이미지·표 묶음 |
| `figcaption` | 없음 | 이미지·표 캡션 |
| `table`, `thead`, `tbody`, `tr` | 없음 | 표 구조 |
| `th`, `td` | `colspan`, `rowspan` | 표 셀과 셀 병합 |
| `hr` | 없음 | 문단 구분선 |

허용되지 않은 태그는 태그 자체를 제거하고 가능한 경우 내부 텍스트만 남긴다. `script`, `style`, `iframe`, `object`, `embed`, `form`, `meta`는 자식 내용까지 제거한다.

## 속성 검증

속성은 태그별 allowlist에 있어도 다음 조건을 통과해야 한다.

- `href`: 안전한 내부 상대 URL 또는 `http://`, `https://` URL만 허용한다.
- `src`: 안전한 내부 상대 URL 또는 `https://` URL만 허용한다. 외부 `http://` 이미지와 data URL 이미지는 허용하지 않는다.
- `width`, `height`: 1부터 9999까지의 양의 정수 문자열만 허용한다.
- `alt`: 최대 160자로 자른다.
- `figure.class`: `image` 또는 `table`만 허용한다. class가 남지 않는 `figure`는 wrapper를 제거하고 내부의 안전한 내용만 남긴다.
- `th`, `td`의 `colspan`, `rowspan`: 1부터 99까지의 양의 정수만 허용한다.
- `p`, `h1`, `h2`, `h3`, `h4`의 `style`: `text-align`과 `margin-left`만 허용한다. 정렬은 `left`, `center`, `right`, `justify`, 들여쓰기는 CKEditor 단계값인 `40px`부터 `200px`까지만 허용한다.
- `span.style`: 설정된 팔레트의 `color`, `background-color`, `font-size`와 `12px`, `14px`, `18px`, `24px`, `32px` 글자 크기만 허용한다. 다른 CSS 속성과 값은 제거한다.
- `a`: 링크 속성이 남은 경우 `rel="nofollow noopener noreferrer"`를 서버가 추가한다.
- `a`: 안전한 `href`가 남지 않으면 링크 태그를 제거하고 내부 텍스트만 남긴다.
이벤트 handler, 허용 목록 밖 inline style, `target`, 임의 class, 임의 data 속성은 허용하지 않는다. URL 임베드 helper는 저장 HTML에 전용 marker나 data 속성을 남기지 않고, 저장된 URL 또는 링크를 공개 렌더링 시점에 서버 resolver로 해석한다.

## 명시적 차단 기준

다음 입력은 sanitizer 결과에 남으면 안 된다.

- `<script>`, `<iframe>`, `<form>`, `<input>`, `<svg>`, `<math>`
- `xlink:href`, `srcdoc`, `<object>`, `<embed>`, `<meta http-equiv="refresh">`
- `onclick`, `onerror`, `onmouseover` 같은 event handler
- 허용된 CKEditor 표현 범위를 벗어난 `style` 속성과 CSS 값
- `javascript:` URL. 대소문자 혼합이나 HTML entity/제어문자로 쪼갠 protocol 우회도 차단 기준에 포함한다.
- `data:image` URL
- 외부 `http://` 이미지
- 링크 `target` 속성

## CKEditor 정상 HTML fixture

CKEditor가 만드는 문단, 제목, 인용, 목록, 링크, 본문 이미지, 이미지 캡션, 표, 가로선과 제한된 글자 표현은 서버 allowlist 안에서 보존되어야 한다. CKEditor 내부 class는 `figure.image`와 `figure.table`만 보존하고, 목록 보조 `data-*`, 허용 목록 밖 inline style, 링크 `target`, 클라이언트가 보낸 `rel` 값은 저장 신뢰 대상이 아니므로 제거한다. 링크 `rel`은 서버가 `nofollow noopener noreferrer`로 다시 작성한다.

`.tools/bin/check-rich-text-sanitizer.php`는 공통 sanitizer와 커뮤니티 게시글 sanitizer 양쪽에서 XSS payload, namespace/URL 우회 payload, 다음 CKEditor식 fixture가 같은 canonical HTML로 정화되는지 확인한다. 또한 커뮤니티 wrapper가 `sr_sanitize_rich_text_html()`을 호출해 hard-drop 컨테이너 제거, Purifier 1차 정화, fallback canonicalizer 경로를 공유하는지도 확인한다. 게시판 설명 fixture는 넓은 구조·표현 태그와 관리자 inline style이 보존되고 스크립트·이벤트 속성·실행형 URL과 CSS 값이 제거되는지와 저장·출력 경로 marker를 함께 확인한다.

- `h1`, `h2`, `p`, `strong`, `em`, `u`, `s`
- `blockquote` 안의 `p`
- `ul`, `ol`, `li`
- `a[href]`
- `img[src|alt|width|height]`
- `span[style]`의 제한된 글자 크기·색상
- `p`, `h1`, `h2`, `h3`, `h4`의 제한된 정렬·들여쓰기
- `figure.image`, `figure.table`, `figcaption`
- `table`, `thead`, `tbody`, `tr`, `th[colspan|rowspan]`, `td[colspan|rowspan]`
- `hr`

같은 점검은 `sr_body_text_html()`의 `html`/`plain` 렌더링 fixture와 콘텐츠, 커뮤니티, 팝업 레이어, 알림 모듈의 HTML 본문 저장/출력 경로도 함께 확인한다. 즉 HTML 본문을 허용하는 번들 모듈은 저장 시 sanitizer를 통과하고, 출력 시 공통 body renderer 또는 모듈 전용 rich text renderer를 거쳐야 한다. 복사 경로는 기존 저장값을 그대로 신뢰하지 않고 새 레코드에 쓰기 전과 본문 이미지/URL 임베드 캐시 동기화 전 최종 본문을 다시 sanitizer에 통과시켜야 한다.

## 커뮤니티 게시판 설명

커뮤니티 게시판 설명은 관리자 직접 HTML 입력 영역이므로 게시글 본문과 분리된 넓은 module allowlist를 사용한다. 일반 section·block·inline 태그, `h1`~`h6`, 목록, 표, 링크, 이미지와 `class`, `id`, `aria-*`, `data-*`, inline `style`을 보존한다. inline style은 속성 종류나 배치 값을 제한하지 않지만 CSS escape를 해석한 결과에 `expression()`, `javascript:`, `vbscript:`, `data:`, `@import`, `behavior`, `-moz-binding`이 있으면 style 전체를 제거한다. 링크와 이미지 URL은 안전한 상대 URL 또는 HTTP(S) 기준을 따르고 외부 이미지는 HTTPS만 허용한다. `script`, `style`, `iframe`, `object`, `embed`, `form`, `meta`, `link`, `base`, SVG/MathML, template과 `on*`, `srcdoc`, `http-equiv`, namespace 실행 속성은 제거한다. 공개 목록과 그룹 화면은 기존 저장값을 신뢰하지 않고 같은 전용 DOM sanitizer를 다시 통과시켜 출력한다. DOM 확장을 사용할 수 없는 환경에서는 inline style을 보존하지 않는 공통 sanitizer로 안전하게 후퇴한다.

## Markdown renderer

Markdown 입력은 코어 내장이 아니라 `markdown_editor` 플러그인의 `markdown-renderer.php` 계약이 제공한다. 새 Markdown 저장은 renderer 계약이 활성화된 경우에만 허용하고, 기존 `body_format=markdown` 저장값은 비활성 환경에서도 제한된 legacy fallback으로 읽기만 지원한다.

Markdown renderer v1은 raw HTML을 허용하지 않는다. Markdown 원문은 escape한 뒤 `full`, `inline`, `plain` 모드별로 HTML 또는 plain text를 만들고, 스타일은 단일 parser/style profile과 저장 stylesheet에서 생성한 URL 및 `profile_hash`로 추적한다. 운영자는 전체 stylesheet를 직접 편집할 수 있지만 모든 selector는 `.markdown-editor-body`로 시작해야 한다. 플러그인은 at-rule, 외부 URL, `expression`, `javascript:`, `behavior`, `-moz-binding` 같은 범위 이탈·실행형 표현을 저장 전에 거부한다.

## URL 임베드

URL 임베드는 저장 HTML에 전용 marker를 남기지 않는다. sanitizer는 안전한 링크와 bare URL 텍스트만 보존하고, 공개 렌더링 경로는 공통 URL 임베드 helper와 활성 모듈의 `url-embed-targets.php` 계약으로 standalone URL을 다시 해석해 canonical URL, 대상 type, 대상 ID, 공개 상태, 이미지 snapshot, renderer 출력을 검증해야 한다.

## 검증

기본 검증:

```bash
php .tools/bin/check-rich-text-sanitizer.php
php .tools/bin/check-htmlpurifier-runtime.php
php .tools/bin/check-markdown-editor-runtime.php
```

전체 점검:

```bash
php .tools/bin/check.php
```

HTML Purifier를 포함한 릴리스 후보는 실제 vendored autoload 또는 임시 Composer autoload 상태에서 `check-rich-text-sanitizer.php`와 `check-htmlpurifier-runtime.php`를 한 번 더 실행한다. `check-htmlpurifier-runtime.php`는 번들 autoload, 버전, `storage/cache/htmlpurifier` cache 경로, Purifier 설정 allowlist와 최종 canonicalizer 결과를 확인한다. HTML Purifier가 없는 공유호스팅 fallback 상태도 같은 sanitizer payload fixture를 통과해야 한다.

## 갱신 기준

- 허용 태그나 속성이 바뀌면 이 문서, CKEditor 정상 HTML fixture, `sr_rich_text_allowed_html_tags()`, `sr_community_allowed_post_html_tags()`, `.tools/bin/check-rich-text-sanitizer.php`를 같은 작업에서 갱신한다. 커뮤니티 allowlist는 공통 allowlist wrapper여야 한다.
- 새로운 rich text 저장 경로 또는 복사/복제 경로를 추가하면 공통 sanitizer를 통과하는지 확인한다.
- HTML 본문을 허용하는 새 모듈을 추가하면 `.tools/bin/check-rich-text-sanitizer.php`의 rich text flow marker에 저장/출력 경로를 추가한다.
- iframe, script, style, event handler, data URL 이미지를 허용하려는 변경은 보안 정책 변경으로 취급하고 [보안 제보와 처리 기준](security-response-policy.md)과 관련 GitHub 이슈를 함께 검토한다.
