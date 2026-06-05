# 이슈 #210 CKEditor 업로드 파일 관리 정책 점검 기록

## 점검 결론

이슈 #210의 방향은 현재 산란의 core boundary와 모듈 책임 구조에 맞다. CKEditor를 독립 파일 도메인이 아니라 편집기 플러그인으로 두고, 파일의 의미와 보존 정책을 콘텐츠/커뮤니티 같은 화면 소유 모듈에 남기는 방향은 `docs/core-decisions.md`의 업로드 primitive 원칙과 일치한다.

다만 이슈 본문은 검토 목록과 후보가 많고, 완료 기준에서 요구하는 정책 결론이 체크되지 않은 상태였다. 따라서 바로 코드 구현으로 들어가면 DB 구조, 접근 정책, 실패 복구, 정리 실행 방식이 구현 중 흔들릴 수 있다. #210은 먼저 아래 결론으로 정책 게이트를 닫은 뒤, 코드 작업은 콘텐츠 1차 구현 이슈와 커뮤니티 후속 구현 이슈로 분리하는 것이 맞다.

## 현재 구현과의 정합성

- 코어는 `core/helpers/upload.php`, `core/helpers/storage.php`로 업로드 검증과 저장소 primitive만 제공한다.
- CKEditor 모듈은 에셋 로딩, toolbar preset, textarea HTML 편집 보조만 제공하며 본문 이미지 upload adapter는 아직 제공하지 않는다.
- 콘텐츠 모듈은 다운로드 파일용 `sr_content_files`, `sr_content_file_links`를 이미 소유한다.
- 커뮤니티 모듈은 게시글 첨부용 `sr_community_attachments`를 이미 소유한다.
- 콘텐츠/커뮤니티는 저장소 삭제 실패를 각각 `sr_content_storage_cleanup_failures`, `sr_community_storage_cleanup_failures`에 남기고 관리자 재시도를 제공한다.
- 이슈 #210의 "공통 파일 테이블을 코어에 두지 않는다"는 전제는 현재 구현과 맞다.

## 보완 필요 사항

이슈 본문 기준으로 다음 항목은 후보만 있고 결론이 부족했다.

- 본문 임베드 파일을 기존 다운로드/첨부 파일 테이블과 통합할지 분리할지
- 본문 HTML 안의 공개 URL, hidden 참조 목록, upload id, storage reference 중 무엇을 서버 기준 식별자로 삼을지
- 공개 콘텐츠와 비공개/유료 콘텐츠의 본문 이미지 접근 방식을 어떻게 나눌지
- `temporary`, `attached`, `orphan_candidate`, `delete_pending`, `deleted`, `delete_failed` 상태를 어느 테이블에 어떤 컬럼으로 둘지
- storage 저장 성공 후 DB 기록 실패, 콘텐츠 저장 rollback, storage delete 실패 같은 실패 순서의 운영 기준
- cron 없는 공유호스팅에서 임시 파일 정리를 어떻게 실행할지
- CKEditor 모듈의 adapter 책임과 소유 모듈 upload endpoint 책임의 경계

## 확정 정책

### 책임 경계

- 코어는 새 공통 파일 테이블이나 파일 관리자 화면을 만들지 않는다.
- 코어는 업로드 검증, 안전한 파일명, storage put/delete/head, storage key 검증까지만 제공한다.
- CKEditor 모듈은 upload adapter 연결과 클라이언트 설정 전달만 맡는다.
- 파일 row, 파일 상태, 파일 권한, 보존/삭제 정책, 관리자 조회/재시도 화면은 소유 모듈이 맡는다.
- 콘텐츠 1차 구현에서 검증한 계약을 커뮤니티 후속 구현에 적용한다.

### DB 구조

- 콘텐츠 본문 임베드 이미지는 기존 다운로드 파일 모델과 분리한다.
- 1차 구현은 콘텐츠 모듈에 `sr_content_body_files`, `sr_content_body_file_refs`를 추가하는 방향으로 잡는다.
- `sr_content_files`와 `sr_content_file_links`는 다운로드 과금, 다운로드 로그, 파일 제목/숨김 상태를 소유하므로 본문 렌더링 이미지와 합치지 않는다.
- 커뮤니티 후속 구현은 `sr_community_body_files`, `sr_community_body_file_refs`를 우선 검토한다. 기존 `sr_community_attachments`는 게시글 첨부/다운로드 의미가 있으므로 본문 임베드와 기본 분리한다.
- 테이블명은 프로젝트 prefix `sr_`와 모듈 소유 prefix를 사용한다.

### 참조 식별

- 서버 기준 식별자는 `storage_driver` + `storage_key`와 파일 row id다.
- upload endpoint 응답은 렌더링 URL과 함께 파일 row id 또는 upload token을 반환한다.
- 본문 HTML의 공개 URL과 hidden 참조 목록은 보조 입력이다.
- 저장 POST는 정화된 본문 HTML에서 허용된 업로드 URL을 파싱하고, hidden 참조 목록과 DB 상태를 대조한다.
- 클라이언트가 보낸 hidden 참조 목록만으로 `attached` 전환을 허용하지 않는다.

### 상태 모델

소유 모듈 파일 테이블은 다음 상태를 사용한다.

| 상태 | 의미 | 전환 기준 |
| --- | --- | --- |
| `temporary` | 업로드는 성공했지만 저장된 본문에 연결되지 않음 | upload endpoint 성공 직후 |
| `attached` | 저장된 콘텐츠/게시글 본문에서 참조됨 | 소유 모듈 저장 성공 후 서버 대조 |
| `orphan_candidate` | 본문 참조가 사라졌거나 임시 TTL이 지남 | 저장 취소, 수정 저장 후 제거, TTL 만료 |
| `delete_pending` | 실제 storage delete 대상 | 참조 수 0 확인과 삭제 유예 조건 충족 |
| `deleted` | DB 기준 삭제 완료 또는 파일 없음 확인 | storage delete 성공 또는 missing 보정 |
| `delete_failed` | storage delete 실패 | 권한 오류, S3 오류, 파일 잠금, key 불일치 |

`temporary` TTL 기본값은 24시간으로 둔다. 공유호스팅에서 cron이 없을 수 있으므로 1차 구현은 관리자 수동 정리와 제한된 opportunistic cleanup을 함께 제공하고, CLI 정리 도구는 후속으로 둘 수 있다.

### 휴지통과 삭제

- 휴지통 이동은 본문 파일 상태를 `attached`로 유지한다.
- 휴지통 복구는 파일 상태를 별도로 바꾸지 않는다.
- 영구 삭제 또는 삭제 유예 기간 종료 후 정화된 본문 참조와 ref table 참조 수를 다시 확인하고 참조 수 0 파일만 `delete_pending`으로 전환한다.
- 같은 storage key가 여러 본문에서 참조될 수 있으므로 단일 콘텐츠 삭제만으로 물리 파일을 삭제하지 않는다.

### 접근 정책

- 공개 콘텐츠의 본문 이미지는 공개 URL로 제공할 수 있다.
- 비공개, 회원 제한, 유료 열람 콘텐츠의 본문 이미지는 공개 URL 직접 접근을 기본 허용하지 않는다.
- 1차 구현은 소유 모듈의 프록시 URL을 우선한다. 프록시 action은 콘텐츠 접근 정책을 확인한 뒤 storage file을 응답한다.
- S3 signed URL은 저장소 driver 확장 시 선택 경로로 남기되 1차 구현의 필수 범위로 두지 않는다.

### 실패 복구

- storage put은 성공했지만 DB insert가 실패하면 즉시 storage delete를 시도하고, 실패하면 기존 cleanup failure 테이블에 기록한다.
- DB row 생성 전 storage put이 실패하면 파일 row를 만들지 않는다.
- 콘텐츠 저장 transaction이 rollback되면 새로 연결하려던 파일은 `temporary`로 남기고 TTL 정리 대상으로 둔다.
- 콘텐츠 저장은 성공했지만 파일 참조 동기화가 실패하면 관리자에게 저장 후 경고를 남기는 방향보다 콘텐츠 저장 transaction 안에서 함께 실패시키는 것을 1차 기준으로 삼는다.
- storage delete 실패는 파일 row를 `delete_failed`로 두고 마지막 오류, 마지막 시도 시각, 시도 횟수를 저장한다.
- storage delete는 성공했지만 DB 갱신이 실패하면 다음 정리 작업에서 storage head 결과로 `deleted` 보정할 수 있어야 한다.
- 동시 요청은 파일 row id 기준 조건부 update와 ref table count 재조회로 보호한다.

### CKEditor adapter

- CKEditor 모듈은 textarea별 upload endpoint 설정을 읽어 adapter를 붙인다.
- 소유 모듈은 upload endpoint, 권한, CSRF, 용량, 확장자/MIME 정책, 응답 payload를 제공한다.
- CKEditor 모듈은 콘텐츠/커뮤니티 테이블을 직접 알지 않는다.
- upload response는 최소 `url`, `file_id`, `storage_driver`, `storage_key`를 포함한다.

## 구현 분할

1. 콘텐츠 본문 파일 테이블과 update SQL 추가
2. 콘텐츠 관리자 CKEditor upload endpoint와 프록시 image endpoint 추가
3. 콘텐츠 저장/수정에서 정화된 HTML 기준 파일 참조 동기화
4. 콘텐츠 휴지통/영구 삭제와 본문 파일 상태 전이 연결
5. 콘텐츠 본문 파일 정리/재시도 관리자 기능 추가
6. CKEditor textarea별 upload adapter 설정 연결
7. 콘텐츠 smoke/failure 테스트와 문서 업데이트
8. 커뮤니티 후속 구현 이슈로 확장

## 구현 전 체크

- 콘텐츠 모듈의 HTML sanitizer 기준을 먼저 확인하고, sanitizer가 제거한 이미지가 `attached`로 전환되지 않게 한다.
- `.tools/bin/check.php`에 새 endpoint 권한/CSRF/계약 검사가 필요한지 확인한다.
- DB 명세, 모듈 개발 가이드, 관리자 화면 필드 가이드, 보안/개인정보 가이드, 스모크 테스트 기준, 배포/트러블슈팅 문서 반영 범위를 구현 이슈에 포함한다.

## 이번 작업의 처리

이번 점검에서는 정책 결론 보완을 완료했다. #210 자체는 바로 코드 구현하기보다 정책 확정 기록으로 닫고, 실제 코드는 위 구현 분할의 1차 콘텐츠 구현부터 별도 작업으로 진행하는 것이 안전하다.
