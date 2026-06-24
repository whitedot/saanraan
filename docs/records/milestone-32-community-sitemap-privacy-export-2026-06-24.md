# 마일스톤 32 커뮤니티 sitemap/privacy export 정확성 기록

## Sitemap

커뮤니티 sitemap은 익명 public crawler 권한 프로필을 기준으로 공개 후보를 좁힌다.

- 게시판 URL 후보는 `sr_community_boards.status = 'enabled'`와 `read_policy = 'public'`을 SQL에서 먼저 적용한 뒤 `LIMIT 1000`을 적용한다.
- 게시글 URL 후보는 `sr_community_posts.status = 'published'`, 게시판 `status = 'enabled'`, 게시판 `read_policy = 'public'`을 SQL에서 먼저 적용한 뒤 `LIMIT 1000`을 적용한다.
- SQL prefilter는 익명 public 권한에서 PHP `sr_community_account_can_read_board($pdo, $board, null)`이 허용할 수 있는 공개 게시판만 남기는 조건이다.
- PHP 최종 권한 게이트는 유지한다. SQL 조건이 공개 후보를 채우더라도 최종 노출은 PHP 권한 판정이 결정한다.

이 순서 때문에 최신 1000개 row가 회원전용/그룹전용 게시글로 소모되는 과소 노출을 줄이면서, 권한 없는 글을 sitemap에 싣지 않는다.

## Privacy Export

커뮤니티 개인정보 export는 섹션별 `LIMIT 1000` 부하 방어를 유지하되, 조용한 누락으로 보이지 않게 제한 메타데이터를 함께 반환한다.

- 각 제한 섹션은 내부적으로 1001건까지 조회해 1000건만 반환한다.
- 1001번째 row가 있으면 `_limits.{section}.has_more = true`와 `policy = request_follow_up_export`를 반환한다.
- 제한 안에 들어온 섹션은 `_limits.{section}.has_more = false`와 `policy = complete_within_section_limit`를 반환한다.
- 이 정책은 #8의 개인정보 계약 매트릭스에 있는 community `export_cleanup` 정의와 충돌하지 않는다.
- #362 cleanup 항목과 별도 개인정보 컬럼 정의를 만들지 않고, export 대상은 기존 community privacy export allowlist와 게시판 추가 필드의 `export_policy_snapshot = 'include'`를 따른다.

완전한 대형 계정 export를 한 요청에서 무제한 수행하지 않는다. 운영자는 `_limits`에서 `has_more = true`인 섹션을 확인해 후속 batch/page export 구현 또는 수동 대응이 필요한 계정을 식별한다.

## 검증

- `modules/community/sitemap.php`의 public prefilter가 `LIMIT 1000` 이전에 적용된다.
- `.tools/bin/check-privacy-export-runtime.php`는 커뮤니티 `level_logs` fixture를 1000건 초과로 만들어 `_limits.level_logs.has_more = true`와 반환 상한 1000건을 확인한다.
- 같은 fixture는 일반 섹션인 `messages`가 제한 안에 있으면 `_limits.messages.has_more = false`를 확인한다.
