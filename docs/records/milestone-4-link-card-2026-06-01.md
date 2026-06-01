# 마일스톤 4 링크 카드 구현 기록

## 범위

- 공통 링크 카드 토큰은 `{{sr_link_card module="community" entity_type="post" entity_id="1" variant="compact" label="선택 제목" slot="body"}}` 형식을 사용한다.
- 토큰 파싱, 대상 검증, 요청 단위 resolver 배치 호출, 공개 렌더링은 `core/helpers/link-card.php`의 좁은 helper가 담당한다.
- 링크 참조 저장은 배치 모듈이 소유한다. 콘텐츠는 `sr_content_link_refs`, 커뮤니티는 `sr_community_link_refs`에 저장한다.
- 콘텐츠 본문은 커뮤니티 게시글 링크 카드를 저장하고 공개 화면에서 렌더링할 수 있다.
- 커뮤니티 게시글은 콘텐츠 링크 카드를 저장하고 공개 화면에서 렌더링할 수 있다.
- 커머스 상품 링크 카드는 계약상 `commerce:product`로 남겨 두되, 현재 저장소에 커머스 상품 모듈이 없으므로 실제 저장 검증은 resolver가 생긴 뒤 통과한다.

## 정책

- 클라이언트가 보낸 hidden 참조 목록이나 CKEditor widget HTML은 신뢰하지 않는다. 저장 직전 최종 본문에서 토큰을 다시 추출한다.
- 대상이 삭제, 비공개, 권한 제한, 모듈 비활성, resolver 부재 상태이면 공개 화면에서 깨진 링크 카드로 표시한다.
- 작성자가 본문에서 토큰을 제거하면 배치 모듈의 참조 행도 reconciliation에서 제거한다.
- 콘텐츠와 커뮤니티는 자기 도메인 resolver만 제공한다. 코어는 도메인 SQL을 직접 알지 않는다.

## CKEditor 스파이크

이번 구현은 CKEditor 5 widget UI 전체가 아니라 직렬화 경계를 먼저 검증하는 vertical slice다. CKEditor가 활성화된 HTML 본문에서도 저장 데이터의 단일 진실원은 토큰 문자열이며, 시각 widget HTML은 서버 저장 검증에서 신뢰하지 않는다. 전체 삽입/편집 모달은 후속 작업에서 `sr_link_card` 토큰을 생성하는 UI로 붙인다.
