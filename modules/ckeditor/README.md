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

커뮤니티 모듈은 HTML 저장 전에 허용 태그와 속성만 남긴다. 이미지 업로드 adapter는 아직 제공하지 않으며, 본문 이미지 업로드는 커뮤니티 모듈이 파일 권한, 저장, 공개 URL, 보존 정책을 소유하는 별도 action으로 추가해야 한다.
