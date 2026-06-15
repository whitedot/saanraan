# Content Module

`content`는 콘텐츠 메인, 콘텐츠 그룹 목록, 단일 콘텐츠를 공개 URL로 노출하는 선택 모듈이다.

## 범위

GitHub 이슈 #9의 1차 범위는 구현 완료 기준으로 정리한다.

- 관리자 콘텐츠 목록, 생성, 수정, 숨김 처리
- 관리자 콘텐츠 그룹 목록, 생성, 수정
- 관리자 콘텐츠 목록의 상태/검색 조건/검색어 필터와 상태 요약
- `draft`, `published`, `hidden` 상태
- `/content/{slug}` 기반 공개 URL
- `/content` 기반 콘텐츠 메인 공개 목록
- `/content/group?key={group_key}` 기반 콘텐츠 그룹 공개 목록
- 제목, 요약, plain text 본문, SEO 제목/설명
- 콘텐츠 커버 이미지 URL과 업로드 이미지
- plain text 본문 저장과 escape 출력
- `menu-links.php` 기반 사이트 메뉴 후보
- `sitemap.php` 기반 sitemap 후보
- `extension-points.php` 기반 배너/팝업레이어 노출 위치
- 콘텐츠별 공용 배너와 공용 팝업레이어 직접 선택
- 활성화된 포인트, 적립금, 예치금 기반 유료 열람과 복합 차감
- 최초 1회 차감과 매 열람 차감 정책
- 콘텐츠 다운로드 파일 업로드와 파일별 다운로드 과금
- 완료 버튼 포인트/금액 처리 1회 지급/차감
- 콘텐츠 변경 감사 로그
- 유료 열람, 다운로드, 완료 버튼 처리 로그 개인정보 사본 제공

## 검증 기준

- slug 중복과 예약어 차단
- `draft`, `hidden` 공개 접근 차단. 단, `/admin/content` 조회 권한이 있는 관리자는 `draft`/`scheduled` 콘텐츠를 공개 URL로 미리보기할 수 있고, 관리자 미리보기는 조회수를 올리지 않음
- `published` 공개 접근과 SEO title/description 출력
- 커버 이미지가 있으면 콘텐츠 홈, 그룹 목록, 상세, OG 이미지에 반영하고 없으면 공개 목록에서 회색 플레이스홀더를 표시
- 사이트 메뉴 후보와 sitemap 후보 포함
- 콘텐츠 그룹이 사이트 메뉴 연결 대상과 sitemap 후보에 포함
- 관리자 POST action의 로그인, 권한, CSRF 검증
- 콘텐츠 생성, 수정, 숨김 감사 로그
- 공용 배너/팝업레이어 직접 선택 출력
- `content.view` 노출 위치 기반 배너/팝업레이어 규칙 출력
- 유료 열람 활성화 시 로그인 요구, 잔액 확인, 항목 차감 후 본문 출력. 반복 열람/다운로드 차감은 CSRF가 붙은 확인 POST 후 짧은 1회성 GET 접근권으로 연결한다. 관리자 미리보기는 열람 차감, 다운로드, 완료 버튼 처리와 조회수 증가를 실행하지 않음
- 복합 차감 항목은 선택한 항목마다 차감 금액을 따로 저장하고 각 항목 잔액을 개별 확인
- 최초 1회 차감 정책은 접근권 테이블을 기준으로 같은 회원/콘텐츠/항목 조합을 중복 차감하지 않음
- 다운로드 과금은 파일별 최초 1회 또는 매 다운로드 정책을 따름
- 완료 버튼 포인트/금액 처리는 회원별 1회만 처리하고 지급/차감 원장을 남김
- 포인트/금액 로그와 접근권 기록은 `privacy-export.php`로 개인정보 사본에 포함
- 회원 탈퇴/익명화 시 콘텐츠 접근권의 회원 연결은 `privacy-cleanup.php`에서 제거

## 보류

- 루트 permalink `/{slug}`
- HTML 본문 저장
- 콘텐츠 빌더와 블록 편집기
- 콘텐츠별 레이아웃
- 예약 발행과 승인 workflow
- 유료 열람/다운로드/완료 버튼 포인트/금액 처리 환불 자동화
- 기간제 접근권과 콘텐츠 묶음 구매

## URL

콘텐츠 메인은 `/content`, 그룹 목록은 `/content/group?key={group_key}`, 단일 콘텐츠 공개 URL은 `/content/{slug}` 형식이다. slug는 3-120자의 소문자 영문, 숫자, 하이픈만 허용하며 예약어는 사용할 수 없다.

## UI Kit

콘텐츠 모듈 전용 UI-KIT 미리보기는 `/content/ui-kit` 사용자 화면에서 현재 콘텐츠 공개 레이아웃 설정을 적용해 제공한다. 모듈 서브메뉴에는 노출하지 않고 관리자 UI-KIT의 사용자 화면 링크에서 접근한다. 샘플은 `modules/content/views/ui-kit-samples/`, 보조 스타일은 `modules/content/assets/ui-kit.css`가 소유한다. 공개 콘텐츠 제목, 요약, 본문, 메타, 캡션은 `--type-*-size`, `--type-*-line-height`, `--text-strong`, `--text-muted`, `--text-subtle` 역할 토큰을 기준으로 맞춘다.

## 노출 위치

`content.view` point는 `before_content`, `after_content` content slot을 제공한다. 배너와 팝업레이어 모듈은 이 위치를 대상으로 `all` 또는 콘텐츠 ID 기반 `exact` 규칙을 저장할 수 있다.
