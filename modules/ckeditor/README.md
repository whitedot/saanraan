# CKEditor 플러그인

CKEditor 플러그인은 선택된 textarea에 CKEditor 5를 붙인다. 콘텐츠, 커뮤니티, 관리자 화면은 각자 설정에서 에디터 적용 대상을 결정하고 HTML 저장/출력 정책을 소유한다.

## 에셋 설치

기본 설정은 직접 호스팅이다. 저장소에는 CKEditor 5 `48.3.0` 브라우저 배포 파일을 `modules/ckeditor/vendor/ckeditor5/` 아래 포함한다. CKEditor 5 v44 이상은 `licenseKey` 설정이 필요하며, 직접 호스팅은 GPL 조건 준수 또는 self-hosting 라이선스가 필요하다. 산란 루트의 MIT 라이선스는 이 제3자 배포 파일의 GPL 또는 상용 라이선스 조건을 대체하지 않는다.

직접 호스팅에서 사용하는 파일 경로는 다음과 같다.

```text
modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js
modules/ckeditor/vendor/ckeditor5/ckeditor5.css
```

CKEditor 버전을 교체할 때는 위 두 파일과 `vendor/ckeditor5/README.md`의 버전, npm 무결성 값, 배포 파일 SHA-256, 출처를 함께 갱신한다.

CDN 모드는 관리자 사이드바의 `플러그인 > CKEditor > 설정`에서 선택할 수 있다. 실제 설정 URL은 `/admin/ckeditor/settings`이다. CDN 모드는 `https://cdn.ckeditor.com`을 사용하며, `GPL` 라이선스 키는 직접 호스팅 방식에서만 허용한다.

참고 공식 문서:

- https://ckeditor.com/docs/ckeditor5/latest/getting-started/installation/self-hosted/quick-start.html
- https://ckeditor.com/docs/ckeditor5/latest/getting-started/installation/cloud/quick-start.html
- https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-key-and-activation.html

## 저장 정책

CKEditor 초기화가 성공한 경우에만 form에 `body_format=html`이 추가된다. 에셋 로딩에 실패하거나 플러그인이 비활성화되면 기존 textarea가 그대로 제출되고 화면 소유 모듈은 `plain` 형식으로 저장한다.

기본 `standard` 툴바는 일반적인 웹 문서 편집 범위인 제목, 글자 크기와 제한된 색상, 기본 강조, 정렬, 링크, 이미지 삽입, 표, 가로선, 인용, 목록, 들여쓰기, 서식 제거를 제공한다. 전체 선택과 찾기/바꾸기는 툴바에 제공하지 않는다. 이전 설정에 저장된 `content_basic`, `community_post_basic`, `admin_basic` 값은 설정 조회 시 `standard`로 정규화한다. 이미지 버튼은 모든 CKEditor 화면에 표시하고 URL 삽입을 제공한다. 화면 소유 모듈이 upload endpoint를 넘긴 경우 같은 이미지 삽입 메뉴에 로컬 파일 업로드도 함께 제공한다. 이미지 도구는 대체 텍스트와 캡션을 제공하고, 표 도구는 행·열·셀 병합과 캡션을 제공한다.

툴바는 `shouldNotGroupWhenFull: true`를 명시해 더보기 버튼에 의존하지 않고 화면 폭이 부족할 때 여러 줄로 접힌다. 에디터 UI의 구조, 간격, 모서리와 그림자는 vendored CKEditor 5 공식 `ckeditor5.css`를 그대로 사용한다. 플러그인 stylesheet는 에디터와 툴바의 `min-width: 0`, `max-width: 100%` 경계, 프로젝트 라이트·다크 토큰 연결과 저장 본문 출력 호환 스타일만 추가한다. 콘텐츠 상세와 커뮤니티 게시글·댓글의 CKEditor 저장 본문도 `.sr-ckeditor[data-sr-editor-output] .ck-content` 구조와 이 두 stylesheet를 그대로 사용한다. 따라서 표, 목록, 정렬, 들여쓰기, 글자 크기·색상, 이미지 정렬·캡션처럼 에디터에서 추가된 표현이 공개 본문에서도 같은 공식 content style 규칙을 따른다. 직접 HTML 에디터의 저장 본문에는 이 CKEditor 전용 스타일을 적용하지 않는다.

글자 크기, 글자색, 배경색, 정렬, 들여쓰기, 이미지 캡션, 표 출력은 공통 rich text sanitizer가 허용하는 제한된 값과 구조만 저장된다. 에디터 설정을 넓힐 때는 `docs/rich-text-sanitizer-policy.md`, 공개 본문 스타일, sanitizer fixture를 함께 갱신해야 한다.

콘텐츠, 커뮤니티, 팝업레이어 모듈은 각자 필요한 textarea에서 본문 이미지 upload endpoint를 `data-sr-editor-upload-*` 속성으로 넘긴다. CKEditor 플러그인은 adapter 연결만 담당하고, 업로드 권한, CSRF, 저장소 key, 파일 상태, 프록시 접근 정책은 화면 소유 모듈이 소유한다. 관리자 공통 에디터 설정은 upload endpoint를 자동으로 붙이지 않으며, 설정형 rich textarea가 필요하면 해당 설정을 소유한 모듈이 안정적인 subject key와 삭제 정책을 먼저 정의해야 한다.
