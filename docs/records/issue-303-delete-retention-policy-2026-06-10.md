# 이슈 #303 삭제 글 댓글 보존 정책 점검

이 문서는 이슈 #303의 정책 검토 결과를 기록한다.

## 결론

현 시점에서는 삭제된 글과 댓글을 즉시 물리 삭제하지 않는다. `status = deleted` 또는 `deleted_at`으로 삭제 상태를 표현하는 대상은 공개 화면에서 제외하되 관리자 화면에서는 상태 필터와 배지로 운영자가 확인할 수 있게 둔다.

물리 삭제나 상위 도메인 삭제에 따른 하위 데이터 정리는 각 모듈이 자기 정책으로 소유한다. 삭제 정책을 바꾸려면 다음 항목을 함께 검토한다.

- 감사 로그와 운영 증빙
- 신고, 알림, 자산 처리 로그
- 개인정보 사본 제공과 탈퇴/익명화 cleanup
- 본문 이미지, 첨부 파일, 저장소 정리 실패 재시도
- 댓글 스레드의 자식 댓글 처리와 tombstone 표시
- 관리자 목록에서 삭제, 숨김, 보관, 운영 보존 용어 구분

## 문서 반영

- `docs/core-decisions.md`: 코어가 삭제 보존 기간이나 휴지통 정책을 소유하지 않고, 모듈이 삭제 보존과 물리 삭제 정책을 소유한다는 결정을 추가했다.
- `docs/admin-ui-guide.md`: 삭제 상태가 관리자 목록에 남을 때 상태 필터와 배지로 식별하고, 물리 삭제와 상태 전환 안내를 구분하도록 했다.
- `docs/security-model.md`: 삭제된 글과 댓글도 개인정보 사본 제공과 cleanup 검토 대상에서 제외하지 않는다는 기준을 추가했다.

## 구현 변경

- `modules/admin/views/retention.php`: 데이터 정리 화면에 삭제된 글·댓글이 운영 보존 데이터이며 자동 정리 대상이 아니라는 안내를 추가했다.
- `modules/admin/lang/ko.php`: 데이터 정리 화면 안내 문구를 번역 키로 추가했다.
- `modules/community/helpers/posts.php`: 커뮤니티 게시글/댓글이 `deleted` 상태로 전환될 때 제목, 본문, 작성자 표시 snapshot, SEO/OG 원문, 본문 임베드 참조를 제거하도록 했다.
- `modules/community/helpers/attachments.php`: 커뮤니티 게시글 삭제 시 첨부 저장소 파일을 삭제하고 원본 파일명과 저장소 참조를 마스킹하도록 했다. 저장소 삭제 실패는 기존 정리 실패 테이블에 기록한다.
- `modules/community/actions/admin-posts.php`, `modules/community/views/admin-posts.php`: 원문 제거가 끝난 삭제 게시글/댓글은 공개·숨김 상태로 복구할 수 없도록 서버 검증과 관리자 행 액션을 맞췄다.
- `modules/community/module.php`, `core/actions/install.php`: 커뮤니티 모듈 파일 전용 버전과 신규 설치 기본 버전을 `2026.06.016`으로 올렸다.
- `modules/community/install.sql`, `modules/community/updates/2026.06.017.sql`: 커뮤니티 게시글/댓글에 숨김 시각, 만료 시각, 사유, 운영 메모, 처리자, 숨김 전 상태 컬럼을 추가했다.
- `modules/community/actions/admin-posts.php`, `modules/community/views/admin-posts.php`: 관리자 단건 숨김 처리에 7일/15일/30일/90일/영구 기간과 사유/메모 입력 모달을 추가했다. 일괄 숨김은 기본 30일 운영 검토 메타데이터를 저장한다.
- `modules/community/helpers/posts.php`: 숨김 메타데이터 컬럼이 적용된 환경에서는 상태 변경 시 숨김 메타데이터를 저장하고, DB 업데이트 전 환경에서는 기존 상태 변경으로 fallback한다.
- `modules/community/module.php`, `core/actions/install.php`: 커뮤니티 모듈 DB 업데이트 버전과 신규 설치 기본 버전을 `2026.06.017`로 올렸다.
- `modules/content/helpers.php`, `modules/content/actions/admin-content-delete.php`: 콘텐츠 관리자 삭제를 숨김 처리에서 `deleted` 상태 전환과 원문 제거 처리로 바꿨다. 제목, 요약, 본문, SEO, 커버 이미지, 본문 이미지, 임베드/링크 참조, revision 원문, 회원 제출 원문, 다운로드 로그 snapshot, 다운로드 첨부 파일 원본명과 저장소 참조를 마스킹하거나 정리한다.
- `modules/content/helpers/comments.php`, `modules/content/actions/comment-delete.php`: 콘텐츠 댓글 삭제 시 상태만 바꾸지 않고 댓글 본문과 작성자 표시 snapshot을 제거한다.
- `modules/content/actions/admin-content-save.php`, `modules/content/actions/admin-contents.php`, `modules/content/views/admin-contents.php`: 원문 제거가 끝난 삭제 콘텐츠는 저장/일괄 상태 변경으로 공개·초안·숨김 상태로 복구할 수 없도록 서버 검증과 관리자 행 액션을 맞췄다.
- `modules/content/module.php`, `core/actions/install.php`: 콘텐츠 모듈 파일 전용 버전과 신규 설치 기본 버전을 `2026.06.018`로 올렸다.
