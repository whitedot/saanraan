# Rich Text Sanitizer 정책

이 문서는 saanraan이 `body_format=html` 본문을 저장하거나 출력할 때 허용하는 HTML 경계를 정리한다. 목적은 CKEditor나 브라우저가 만든 HTML을 신뢰하지 않고, 서버 allowlist를 기준으로 같은 결과를 만들도록 유지하는 것이다.

## 적용 대상

현재 기준 대상:

- 공통 rich text sanitizer: `sr_sanitize_rich_text_html()`
- 커뮤니티 게시글 sanitizer: `sr_community_sanitize_post_html()`
- CKEditor 플러그인으로 강화된 textarea의 HTML 저장
- 콘텐츠, 커뮤니티 게시글, 팝업레이어처럼 기존 HTML 본문을 복사해 새 레코드를 만드는 경로
- 임베드 매니저 marker가 포함된 본문 HTML

공통 rich text sanitizer는 `script`, `style`, `iframe`, `object`, `embed`, `form`, `meta` 같은 hard-drop 컨테이너를 먼저 제거한다. 그다음 HTML Purifier가 있으면 1차 정화를 실행하고, 이후 내부 allowlist canonicalizer를 한 번 더 통과한다. HTML Purifier가 없으면 내부 DOM 기반 fallback을 사용한다. 커뮤니티 게시글 sanitizer는 모듈 경계용 wrapper만 유지하고 실제 정화는 공통 sanitizer에 위임한다. 두 경로 모두 같은 fixture를 통과해야 한다.

## HTML Purifier 설정 경계

HTML Purifier adapter는 내부 canonicalizer를 대체하지 않는다. 공통 sanitizer는 hard-drop 컨테이너를 먼저 제거한 뒤 Purifier를 1차 정화와 HTML 파싱 보강으로 사용하고, 최종 저장/출력 형태는 산란 allowlist canonicalizer가 다시 결정한다.

현재 Purifier 설정 기준:

- `HTML.Allowed`는 산란 rich text allowlist와 같은 태그/속성 경계로 제한한다.
- `Attr.AllowedClasses`는 `sr-embed-manager-marker`만 허용한다.
- `URI.AllowedSchemes`는 `http`, `https`만 허용한다. 최종 canonicalizer는 이미지 `src`에서 외부 `http://`를 다시 제거한다.
- `HTML.Nofollow`는 켜고 `HTML.TargetBlank`는 끈다.
- `Cache.SerializerPath`는 `storage/cache/htmlpurifier` 아래만 사용한다.
- cache 디렉터리를 사용할 수 없으면 `Cache.DefinitionImpl`을 비활성화하고 vendor 내부 쓰기를 요구하지 않는다.

## 허용 태그와 속성

서버 allowlist는 다음 태그와 속성만 허용한다.

| 태그 | 허용 속성 | 용도 |
| --- | --- | --- |
| `p` | 없음 | 문단 |
| `br` | 없음 | 줄바꿈 |
| `strong` | 없음 | 굵게 |
| `em` | 없음 | 기울임 |
| `u` | 없음 | 밑줄 |
| `s` | 없음 | 취소선 |
| `blockquote` | 없음 | 인용 |
| `ul`, `ol`, `li` | 없음 | 목록 |
| `a` | `href` | 링크 |
| `h2`, `h3` | 없음 | 본문 안 제목 |
| `span` | `class`, `data-sr-embed-manager-ref`, `data-sr-embed-manager-target-module`, `data-sr-embed-manager-target-type`, `data-sr-embed-manager-target-id`, `data-sr-embed-manager-variant`, `data-sr-embed-manager-label` | 임베드 매니저 marker |
| `img` | `src`, `alt`, `width`, `height` | 본문 이미지 |

허용되지 않은 태그는 태그 자체를 제거하고 가능한 경우 내부 텍스트만 남긴다. `script`, `style`, `iframe`, `object`, `embed`, `form`, `meta`는 자식 내용까지 제거한다.

## 속성 검증

속성은 태그별 allowlist에 있어도 다음 조건을 통과해야 한다.

- `href`: 안전한 내부 상대 URL 또는 `http://`, `https://` URL만 허용한다.
- `src`: 안전한 내부 상대 URL 또는 `https://` URL만 허용한다. 외부 `http://` 이미지와 data URL 이미지는 허용하지 않는다.
- `width`, `height`: 1부터 9999까지의 양의 정수 문자열만 허용한다.
- `alt`: 최대 160자로 자른다.
- `a`: 링크 속성이 남은 경우 `rel="nofollow noopener noreferrer"`를 서버가 추가한다.
- `a`: 안전한 `href`가 남지 않으면 링크 태그를 제거하고 내부 텍스트만 남긴다.
- `class`: `span`의 `sr-embed-manager-marker`만 허용한다.
- `data-sr-embed-manager-ref`: `em_`으로 시작하는 소문자/숫자/밑줄 6-70자 suffix만 허용한다.
- `data-sr-embed-manager-target-module`, `data-sr-embed-manager-target-type`, `data-sr-embed-manager-variant`: 소문자로 시작하는 소문자/숫자/밑줄 key만 허용한다.
- `data-sr-embed-manager-target-id`: 양의 정수 문자열만 허용한다.
- `data-sr-embed-manager-label`: 연속 공백을 한 칸으로 정규화하고 최대 120자로 자른다.

이벤트 handler, inline style, `target`, 임의 class, 임의 data 속성은 허용하지 않는다.

## 명시적 차단 기준

다음 입력은 sanitizer 결과에 남으면 안 된다.

- `<script>`, `<iframe>`, `<form>`, `<input>`, `<svg>`, `<math>`
- `xlink:href`, `srcdoc`, `<object>`, `<embed>`, `<meta http-equiv="refresh">`
- `onclick`, `onerror`, `onmouseover` 같은 event handler
- `style` 속성
- `javascript:` URL. 대소문자 혼합이나 HTML entity/제어문자로 쪼갠 protocol 우회도 차단 기준에 포함한다.
- `data:image` URL
- 외부 `http://` 이미지
- 링크 `target` 속성

## CKEditor 정상 HTML fixture

CKEditor가 만드는 문단, 제목, 인용, 목록, 링크, 본문 이미지는 서버 allowlist 안에서 보존되어야 한다. 다만 CKEditor 내부 class, 목록 보조 `data-*`, inline style, 링크 `target`, 클라이언트가 보낸 `rel` 값은 저장 신뢰 대상이 아니므로 제거한다. 링크 `rel`은 서버가 `nofollow noopener noreferrer`로 다시 작성한다.

`.tools/bin/check-rich-text-sanitizer.php`는 공통 sanitizer와 커뮤니티 게시글 sanitizer 양쪽에서 XSS payload, namespace/URL 우회 payload, 다음 CKEditor식 fixture가 같은 canonical HTML로 정화되는지 확인한다. 또한 커뮤니티 wrapper가 `sr_sanitize_rich_text_html()`을 호출해 hard-drop 컨테이너 제거, Purifier 1차 정화, fallback canonicalizer 경로를 공유하는지도 확인한다.

- `h2`, `p`, `strong`, `em`, `u`, `s`
- `blockquote` 안의 `p`
- `ul`, `ol`, `li`
- `a[href]`
- `img[src|alt|width|height]`

같은 점검은 `sr_body_text_html()`의 `html`/`plain` 렌더링 fixture와 콘텐츠, 커뮤니티, 팝업 레이어, 알림 모듈의 HTML 본문 저장/출력 marker도 함께 확인한다. 즉 HTML 본문을 허용하는 번들 모듈은 저장 시 sanitizer를 통과하고, 출력 시 공통 body renderer 또는 모듈 전용 rich text renderer를 거쳐야 한다. 복사 경로는 기존 저장값을 그대로 신뢰하지 않고 새 레코드에 쓰기 전과 본문 이미지/임베드 참조 재작성 후 최종 본문을 다시 sanitizer에 통과시켜야 한다.

## 임베드 marker

임베드 매니저 marker는 HTML sanitizer만으로 신뢰하지 않는다. sanitizer는 marker 모양과 속성 형식만 제한하고, 저장 action은 활성 모듈의 `embed-manager-targets.php` 계약으로 대상 모듈, 대상 type, 대상 ID, variant, ref ownership을 다시 검증해야 한다.

## 검증

기본 검증:

```bash
php .tools/bin/check-rich-text-sanitizer.php
php .tools/bin/check-htmlpurifier-runtime.php
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
