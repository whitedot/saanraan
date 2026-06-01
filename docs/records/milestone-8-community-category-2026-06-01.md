# 마일스톤 8 콘텐츠·커뮤니티 운영 정합성 구현 기록

## 범위

- #118 커뮤니티 게시판 카테고리 수직 슬라이스를 구현했다.
- `sr_community_categories`와 `sr_community_posts.category_id`를 추가했다.
- 게시판 편집 화면에서 카테고리 생성, 수정, 삭제와 `category_required` 설정을 다룬다.
- 공개 게시판 목록은 `?category={category_key}` 필터를 지원하고, 무효/비활성 key는 HTTP 200의 소프트 오류와 `noindex, follow`로 처리한다.
- 게시글 작성/수정은 서버에서 게시판 소속, 활성 상태, 필수 선택을 검증한다.
- 게시글 목록, 상세, 내 스크랩 목록, 관리자 게시글 목록에 카테고리 표시를 추가했다.
- 콘텐츠 시리즈는 `sr_content_series`, `sr_content_series_items`로 모듈 내부에 추가했다.
- `/admin/content/series`에서 콘텐츠 시리즈를 만들고 수정하며, 콘텐츠 편집 화면에서 시리즈 회차를 연결한다.
- 커뮤니티 시리즈는 `sr_community_series`, `sr_community_series_items`로 모듈 내부에 추가했다.
- `/community/series`에서 회원이 자신의 시리즈를 만들고, 글 작성/수정 화면에서 기존 시리즈 선택 또는 새 시리즈 생성을 지원한다.
- `/admin/community/series`에서 운영자가 커뮤니티 시리즈의 상태, 공개 범위, 운영 메모를 조정한다.
- 공개 콘텐츠와 공개 게시글은 본문 다음에 active 시리즈 항목 내비게이션을 렌더링한다.
- 시리즈 공개 범위는 `public`, `member`, `private`로 제한한다. 커뮤니티 private 시리즈는 소유자만 볼 수 있고, 콘텐츠 private 시리즈는 공개 출력에서 제외한다.
- 리액션은 마일스톤 8에서 새 테이블과 UI를 추가하지 않기로 결정했다. 현재 사용자 반응 표면은 커뮤니티 스크랩과 콘텐츠 완료 버튼으로 유지한다.

## Privacy 판단

- 카테고리 자체는 게시판 분류 데이터이며 account 참조를 갖지 않는다.
- 회원 privacy export의 작성 게시글에는 `category_id`, `category_key`, `category_title`을 포함한다.
- 커뮤니티 privacy export에는 본인이 소유한 시리즈와 본인 게시글이 연결된 시리즈 항목을 포함한다.
- 콘텐츠 privacy export에는 본인이 생성/수정한 콘텐츠 시리즈와 본인이 생성한 시리즈 항목을 포함한다.
- 쪽지 export는 상대방 account id를 직접 제공하지 않고 방향과 마스킹된 상대 역할만 제공한다.
- 신고 export는 피신고자가 본인인 경우에만 `reported_account_id`를 유지하고, 제3자 피신고자는 `masked_counterparty`로 표시한다.

## 점검

- `find . -name '*.php' -not -path './.git/*' -not -path './modules/ckeditor/vendor/*' -print0 | xargs -0 -n 1 php -l`
- `php .tools/bin/check.php`

## 후속 확인

- 카테고리 필터와 시리즈 내비게이션의 `EXPLAIN`, 브라우저 수동 점검은 실제 DB 적용 환경에서 기록해야 한다.
- GitHub Wiki는 이 작업 트리에 체크아웃되어 있지 않아 저장소 문서(`docs/implementation-snapshot.md`, `docs/smoke-test.md`)만 함께 갱신했다. Wiki 동기화가 필요한 배포 절차에서는 이 기록을 기준으로 반영한다.
