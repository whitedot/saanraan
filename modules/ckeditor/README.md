# CKEditor 플러그인

CKEditor 플러그인은 선택된 textarea에 CKEditor 5를 붙인다. 콘텐츠, 커뮤니티, 관리자 화면은 각자 설정에서 에디터 적용 대상을 결정하고 HTML 저장/출력 정책을 소유한다.

## 에셋 설치

기본 설정은 직접 호스팅이다. 저장소에는 CKEditor 5 `48.1.0` 브라우저 배포 파일을 `modules/ckeditor/vendor/ckeditor5/` 아래 포함한다. CKEditor 5 v44 이상은 `licenseKey` 설정이 필요하며, 직접 호스팅은 GPL 조건 준수 또는 self-hosting 라이선스가 필요하다.

직접 호스팅에서 사용하는 파일 경로는 다음과 같다.

```text
modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js
modules/ckeditor/vendor/ckeditor5/ckeditor5.css
```

CKEditor 버전을 교체할 때는 위 두 파일과 `vendor/ckeditor5/README.md`의 버전/출처를 함께 갱신한다.

CDN 모드는 관리자 사이드바의 `플러그인 > CKEditor > 설정`에서 선택할 수 있다. 실제 설정 URL은 `/admin/ckeditor/settings`이다. CDN 모드는 `https://cdn.ckeditor.com`을 사용하며, `GPL` 라이선스 키는 직접 호스팅 방식에서만 허용한다.

참고 공식 문서:

- https://ckeditor.com/docs/ckeditor5/latest/getting-started/installation/self-hosted/quick-start.html
- https://ckeditor.com/docs/ckeditor5/latest/getting-started/installation/cloud/quick-start.html
- https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-key-and-activation.html

## 저장 정책

CKEditor 초기화가 성공한 경우에만 form에 `body_format=html`이 추가된다. 에셋 로딩에 실패하거나 플러그인이 비활성화되면 기존 textarea가 그대로 제출되고 화면 소유 모듈은 `plain` 형식으로 저장한다.

콘텐츠, 커뮤니티, 팝업레이어 모듈은 각자 필요한 textarea에서 본문 이미지 upload endpoint를 `data-sr-editor-upload-*` 속성으로 넘긴다. CKEditor 플러그인은 adapter 연결만 담당하고, 업로드 권한, CSRF, 저장소 key, 파일 상태, 프록시 접근 정책은 화면 소유 모듈이 소유한다. 관리자 공통 에디터 설정은 upload endpoint를 자동으로 붙이지 않으며, 설정형 rich textarea가 필요하면 해당 설정을 소유한 모듈이 안정적인 subject key와 삭제 정책을 먼저 정의해야 한다.
