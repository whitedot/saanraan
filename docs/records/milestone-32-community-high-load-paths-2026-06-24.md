# 마일스톤 32 커뮤니티 고부하 작업 경로 기록

## 분류

| 작업 | 분류 | 기준 |
| --- | --- | --- |
| 게시판 settings-only copy | 즉시 제한형 | 게시판 row, 설정 row, 카테고리만 복사하고 콘텐츠/파일을 복사하지 않는다. |
| 게시판 full copy | 작업 테이블형 | 게시글, 댓글, 첨부, 본문 이미지, 선택 시 시리즈를 `sr_community_board_copy_jobs`와 `sr_community_board_copy_job_maps`로 나누어 처리한다. |
| 게시판 삭제 | 하이브리드 작업 테이블형 | 작은 게시판은 즉시 삭제하고, 기준을 넘는 게시판은 `sr_community_board_delete_jobs`와 `sr_community_board_delete_job_maps`로 나누어 처리한다. |
| view_count merge | 보류 | #360의 누적, 병합, 재조정 원칙을 따르기 전에는 영속 accumulator를 만들지 않는다. |
| 개인정보 cleanup | 기존 계약형 유지 | #8 개인정보 계약 매트릭스와 `privacy-cleanup.php` allowlist를 따른다. 대형 계정 batch cleanup은 별도 후속 이슈가 필요하다. |

## 게시판 Full Copy

게시글/댓글/첨부파일 포함 복사는 규모와 관계없이 작업 테이블형으로 통일한다.

- 관리자 복사 화면에서 `full` 모드를 제출하면 즉시 `sr_community_board_copy_job_create()`로 작업을 만든다.
- `sr_community_copy_board()`는 `full` 모드 동기 실행을 거부한다.
- `prepare` 단계는 source type별로 아직 map에 없는 원본 ID를 요청당 최대 500개씩 추가한다.
- `posts`, `comments`, `attachments` 단계는 기존처럼 요청당 처리 상한을 사용한다.
- `verify` 단계는 복사된 attachment map을 요청당 최대 100개씩 storage head로 확인하고 `verified` 상태로 넘긴다.
- `cleanup` 단계는 생성된 storage map을 요청당 최대 100개씩 정리하고, 남은 파일이 있으면 cleanup 단계에 머문다.
- `cleanup` 실패 map은 생성 storage driver/key를 보존해 같은 대상 파일을 다시 정리할 수 있어야 한다.
- 작업 처리 중에는 `lock_token`을 매 단계와 map update에 확인한다.

## 게시판 삭제

게시판 삭제는 외부 운영 참조가 있으면 계속 차단한다. 삭제 확인 문구가 일치하고 다음 기준 중 하나를 넘으면 즉시 삭제 대신 삭제 작업을 만든다.

- 게시글 500건 이상
- 댓글 5,000건 이상
- 첨부 100건 이상
- 시리즈 500건 이상

삭제 작업은 생성 시 게시판을 `disabled`로 전환해 공개 접근을 먼저 막고, `/admin/community/board-delete-jobs`에서 요청당 제한된 수량으로 이어서 실행한다.

- `prepare` 단계는 게시판을 다시 비활성화하고 file cache를 stale 처리한다.
- `reports`, `entitlements`, `series_refs`, `scraps`, `comments`, `attachments`, `posts`, `series`, `board_meta`, `storage`, `complete` 순서로 진행한다.
- `attachments` 단계는 첨부 row를 삭제하기 전에 storage driver/key를 `sr_community_board_delete_job_maps`에 보존한다.
- `posts` 단계는 게시글 row 삭제 전에 본문 이미지 정리 helper를 호출한다.
- `storage` 단계는 보존된 첨부 storage map을 요청당 최대 100개씩 삭제한다. 실패 항목은 `cleanup_required` 상태로 남기고 저장소 정리 실패 기록에도 남긴다.
- 작은 게시판은 기존 `sr_community_delete_board()` 즉시 삭제 경로를 유지하며, 게시글 1건처럼 소량 대상은 부하 등급을 `낮음`으로 표시한다.

## View Count

이번 마일스톤에서는 view_count deferred merge를 채택하지 않는다.

- 현재 인기 정렬과 조회순 측정은 `sr_community_posts.view_count` 즉시 증가 값을 기준으로 유지한다.
- 영속 accumulator, 병합 트리거, drift 재조정, cron 없는 공유호스팅 실행 방식은 #360의 counter/summary 계약 확장 없이는 새로 만들지 않는다.
- 따라서 #359 인기 정렬과 #361 조회순 측정에 새 staleness는 생기지 않는다.

## Privacy Cleanup

커뮤니티 cleanup 대상은 #8 개인정보 계약 매트릭스의 community `export_cleanup` 정의와 `modules/community/privacy-cleanup.php`의 기존 allowlist를 따른다.

- #363 privacy export와 별도 개인정보 컬럼 정의를 만들지 않는다.
- 현재 cleanup은 계약 fixture로 대상 계정 row만 익명화되는지 확인한다.
- 대량 활동 계정 cleanup을 작업 테이블형으로 전환하려면 개인정보 요청 진행 상태, 실패 재시도, 감사 metadata를 함께 설계하는 별도 이슈가 필요하다.

## 검증

- `.tools/bin/check-community-board-copy-limits.php`는 작은 full-copy도 작업 경로를 만들 수 있음을 확인한다.
- `.tools/bin/check-community-board-copy-job-lock.php`는 `prepare`와 `cleanup`의 limit-aware 시그니처, lock token map update marker, cleanup 실패 시 storage 참조 보존 marker를 확인한다.
- 게시판 삭제 작업은 `php .tools/bin/check.php`의 install/update parity, 운영 상태 점검, HTTP smoke에서 route와 schema drift를 확인한다.
- `php .tools/bin/check.php`와 HTTP smoke에서 관리자 복사 화면, 작업 화면, 운영 점검 fixture를 함께 확인한다.
