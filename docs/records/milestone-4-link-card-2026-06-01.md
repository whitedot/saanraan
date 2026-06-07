# 마일스톤 4 링크 카드 구현 기록

> 2026-06-08 후속 결정: 이 문서는 마일스톤 4 당시의 구현 기록이다. 현재 1.0 방향은 링크 카드 토큰을 신규 저장에서 거부하고, 검색 삽입 결과를 일반 HTML 또는 텍스트 링크로 저장하는 정책이다. legacy token 감지/거부와 제한된 refs 정리 helper는 `content_embed` 모듈의 호환 범위로 이동했다. 새 `content_embed` marker + refs 모델은 기존 1.0 정책의 변경 후보이며, refs는 삭제 차단을 자동 강제하는 hidden 원장이 아니라 렌더링과 점검을 위한 명시적 참조다.

## 범위

- 공통 링크 카드 토큰은 `{{sr_link_card module="community" entity_type="post" entity_id="1" variant="compact" label="선택 제목" slot="body"}}` 형식을 사용한다.
- 토큰 파싱, 대상 검증, 요청 단위 resolver 배치 호출, 공개 렌더링은 당시 `core/helpers/link-card.php`의 좁은 helper가 담당했다. 현재 legacy 토큰 거부 helper는 `modules/content_embed/helpers.php`에 있다.
- 링크 참조 저장은 배치 모듈이 소유한다. 콘텐츠는 `sr_content_link_refs`, 커뮤니티는 `sr_community_link_refs`에 저장한다.
- 콘텐츠 본문은 커뮤니티 게시글 링크 카드를 저장하고 공개 화면에서 렌더링할 수 있다.
- 커뮤니티 게시글은 콘텐츠 링크 카드를 저장하고 공개 화면에서 렌더링할 수 있다.
- 콘텐츠 관리 폼은 커뮤니티 게시글을 검색해 `community:post` 링크 카드 토큰을 삽입할 수 있다.
- 커뮤니티 작성/수정 폼은 콘텐츠를 검색해 `content:content` 링크 카드 토큰을 삽입할 수 있다.
- 커머스 상품 링크 카드는 계약상 `commerce:product`로 남겨 두되, 현재 저장소에 커머스 상품 모듈이 없으므로 실제 저장 검증은 resolver가 생긴 뒤 통과한다.

## 정책

- 클라이언트가 보낸 hidden 참조 목록이나 CKEditor widget HTML은 신뢰하지 않는다. 저장 직전 최종 본문에서 토큰을 다시 추출한다.
- 대상이 삭제, 비공개, 권한 제한, 모듈 비활성, resolver 부재 상태이면 공개 화면에서 깨진 링크 카드로 표시한다.
- 작성자가 본문에서 토큰을 제거하면 배치 모듈의 참조 행도 reconciliation에서 제거한다.
- 콘텐츠와 커뮤니티는 자기 도메인 resolver만 제공한다. 코어는 도메인 SQL을 직접 알지 않는다.

## CKEditor 스파이크

이번 구현은 CKEditor 5 widget UI 전체가 아니라 직렬화 경계를 먼저 검증하는 vertical slice다. CKEditor가 활성화된 HTML 본문에서도 저장 데이터의 단일 진실원은 토큰 문자열이며, 시각 widget HTML은 서버 저장 검증에서 신뢰하지 않는다. `.tools/bin/check-link-card.php`는 HTML fixture 안의 토큰 재추출, rich text sanitizer 이후 토큰 유지, 중복 토큰 병합, 가짜 widget HTML 무시를 확인한다. 검색 삽입 UI는 토큰을 생성해 CKEditor 선택 위치 또는 일반 textarea 선택 위치에 삽입한다.

## 관리자 정리 흐름

- 콘텐츠 관리자는 `/admin/content/link-refs`에서 콘텐츠 본문에 배치된 링크 카드와 깨진 대상을 확인하고 원본 콘텐츠 편집 화면으로 이동해 제거, 교체, 유지 판단을 한다.
- 커뮤니티 관리자는 `/admin/community/link-refs`에서 게시글 본문에 배치된 링크 카드와 깨진 대상을 확인하고 원본 게시글 편집 화면으로 이동해 제거, 교체, 유지 판단을 한다.
- 정리 화면은 참조 행을 단독 삭제하지 않는다. 저장 진실원은 본문 토큰이므로 운영자는 본문을 수정해 다음 저장 reconciliation에서 참조를 갱신한다.
