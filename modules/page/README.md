# Page Module

`page`는 단일 페이지를 작성하고 `/pages/{slug}` 공개 URL로 노출하는 선택 모듈이다.

## 범위

- 관리자 페이지 목록, 생성, 수정, 숨김 처리
- `draft`, `published`, `hidden` 상태
- plain text 본문 저장과 escape 출력
- `menu-links.php` 기반 사이트 메뉴 후보
- `sitemap.php` 기반 sitemap 후보
- `extension-points.php` 기반 배너/팝업레이어 출력 위치
- 페이지별 공용 배너와 공용 팝업레이어 직접 선택
- 페이지 변경 감사 로그

## 보류

- 루트 permalink `/{slug}`
- HTML 본문 저장
- 페이지 빌더와 블록 편집기
- 페이지별 레이아웃
- 예약 발행과 승인 workflow
- 첨부파일과 이미지 업로드

## URL

공개 URL은 `/pages/{slug}` 형식이다. slug는 3-120자의 소문자 영문, 숫자, 하이픈만 허용하며 예약어는 사용할 수 없다.

## 출력 위치

`page.view` point는 `before_content`, `after_content` content slot을 제공한다. 배너와 팝업레이어 모듈은 이 위치를 대상으로 `all` 또는 페이지 ID 기반 `exact` 규칙을 저장할 수 있다.
