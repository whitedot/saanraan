# 이슈 #210 CKEditor 업로드 파일 관리 정책 점검 기록

## 점검 결론

이슈 #210의 방향은 현재 산란의 core boundary와 모듈 책임 구조에 맞다. CKEditor를 독립 파일 도메인이 아니라 편집기 플러그인으로 두고, 파일의 의미와 보존 정책을 콘텐츠/커뮤니티 같은 화면 소유 모듈에 남기는 방향은 `docs/core-decisions.md`의 업로드 primitive 원칙과 일치한다.

다만 이슈 본문은 검토 목록과 후보가 많고, 완료 기준에서 요구하는 정책 결론이 체크되지 않은 상태였다. 특히 이후 보완 과정에서 본문 이미지는 DB 참조 테이블 없이 처리해야 한다는 조건이 확정되었다. 따라서 #210의 정책 게이트는 "편집기는 업로드 adapter만 제공하고, 소유 모듈은 정화된 HTML과 자신이 정한 저장 경로만으로 파일 수명을 관리한다"로 닫는다.

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
- DB를 쓰지 않을 때 임시 업로드, 저장 완료, 제거, 삭제 실패를 어떤 저장 경로와 정리 규칙으로 구분할지
- storage 저장 성공 후 DB 기록 실패, 콘텐츠 저장 rollback, storage delete 실패 같은 실패 순서의 운영 기준
- cron 없는 공유호스팅에서 임시 파일 정리를 어떻게 실행할지
- CKEditor 모듈의 adapter 책임과 소유 모듈 upload endpoint 책임의 경계

## 확정 정책

### 책임 경계

- 코어는 새 공통 파일 테이블이나 파일 관리자 화면을 만들지 않는다.
- 코어는 업로드 검증, 안전한 파일명, storage put/delete/head, storage key 검증까지만 제공한다.
- CKEditor 모듈은 upload adapter 연결과 클라이언트 설정 전달만 맡는다.
- 파일 권한, 보존/삭제 정책, 관리자 조회/재시도 화면은 소유 모듈이 맡는다.
- 콘텐츠에서 검증한 계약을 커뮤니티와 팝업레이어에도 같은 경계로 적용한다.

### 저장 구조

- 콘텐츠 본문 임베드 이미지는 기존 다운로드 파일 모델과 분리한다.
- 1차 구현은 본문 이미지 전용 DB 테이블을 만들지 않는다.
- `sr_content_files`와 `sr_content_file_links`는 다운로드 과금, 다운로드 로그, 파일 제목/숨김 상태를 소유하므로 본문 렌더링 이미지와 합치지 않는다.
- 콘텐츠 모듈은 저장 전 이미지를 `storage/content/body/tmp/{upload_token}/{file}`에 두고, 저장된 콘텐츠 이미지는 `storage/content/body/{content_id}/{file}`에 둔다.
- 커뮤니티는 같은 원칙으로 `storage/community/body/tmp/{upload_token}`와 shard가 포함된 `storage/community/body/{shard1}/{shard2}/{post_id}` 모듈 소유 경로를 사용한다. 기존 `sr_community_attachments`는 게시글 첨부/다운로드 의미가 있으므로 본문 임베드와 기본 분리한다.
- 팝업레이어는 `storage/popup_layer/body/tmp/{upload_token}`와 `storage/popup_layer/body/{popup_layer_id}` 모듈 소유 경로를 사용한다.
- 관리자 설정형 rich textarea는 레코드 ID가 없으므로 공통 저장소를 자동 적용하지 않는다. 실제 설정 필드가 생길 때 소유 모듈이 안정적인 setting subject key와 삭제 정책을 먼저 정의한다.
- 본문 이미지의 1차 구현은 로컬 저장소를 기준으로 한다. S3 같은 외부 저장소 지원은 DB 없는 삭제 보장 방식을 별도로 설계한 뒤 확장한다.

### 참조 식별

- 서버 기준 식별자는 정화된 본문 HTML에 남은 소유 모듈 프록시 URL과, 그 URL에서 계산되는 모듈 소유 저장 경로다.
- upload endpoint 응답은 렌더링 URL만 필수로 반환한다.
- hidden 참조 목록은 필요하더라도 보조 입력이며, 삭제 판단의 기준으로 쓰지 않는다.
- 저장 POST는 정화된 본문 HTML에서 허용된 업로드 URL을 파싱하고, 임시 경로의 파일을 콘텐츠별 경로로 옮긴 뒤 HTML URL을 저장 경로용 프록시 URL로 바꾼다.
- 클라이언트가 보낸 hidden 참조 목록만으로 보존을 허용하지 않는다.

### 상태 모델

DB 상태 컬럼 대신 저장 경로와 정리 시점으로 상태를 구분한다.

| 상태 | 의미 | 전환 기준 |
| --- | --- | --- |
| 임시 | 업로드는 성공했지만 저장된 본문에 연결되지 않음 | `storage/content/body/tmp/{upload_token}` 아래에 존재 |
| 저장됨 | 저장된 콘텐츠/게시글 본문에서 참조됨 | `storage/content/body/{content_id}` 아래에 존재하고 정화된 HTML에 같은 파일 URL이 남음 |
| 미참조 | 본문 참조가 사라졌거나 임시 TTL이 지남 | 저장 시 HTML에 없거나 TTL 만료 |
| 삭제 실패 | 삭제 시도가 실패함 | 기존 cleanup failure 테이블에 실패 기록 |

임시 TTL 기본값은 24시간으로 둔다. 공유호스팅에서 cron이 없을 수 있으므로 1차 구현은 제한된 opportunistic cleanup을 함께 제공하고, CLI 정리 도구는 후속으로 둘 수 있다.

### 삭제

- 삭제 시 소유 모듈은 해당 콘텐츠/게시글/팝업레이어의 본문 이미지 디렉터리 전체를 삭제한다.
- 콘텐츠별 저장 경로를 쓰므로 같은 물리 파일을 여러 본문에서 공유하지 않는다. 다른 글에 이미지를 복사해 넣는 경우에도 해당 글 저장 시 자기 경로로 복사되거나, 남의 콘텐츠 경로 URL이면 그 콘텐츠의 접근 정책을 따라야 한다.

### 접근 정책

- 공개 콘텐츠의 본문 이미지는 공개 URL로 제공할 수 있다.
- 비공개, 회원 제한, 유료 열람 콘텐츠의 본문 이미지는 공개 URL 직접 접근을 기본 허용하지 않는다.
- 1차 구현은 소유 모듈의 프록시 URL을 우선한다. 프록시 action은 콘텐츠 접근 정책을 확인한 뒤 storage file을 응답한다.
- S3 signed URL은 저장소 driver 확장 시 선택 경로로 남기되 1차 구현의 필수 범위로 두지 않는다.

### 실패 복구

- storage put이 실패하면 upload endpoint가 실패한다.
- 콘텐츠 저장 transaction이 rollback되면 새로 연결하려던 파일은 임시 경로에 남기고 TTL 정리 대상으로 둔다.
- 콘텐츠 저장 중 임시 파일 이동이나 HTML URL 갱신이 실패하면 콘텐츠 저장도 실패시키는 것을 1차 기준으로 삼는다.
- storage delete 실패는 기존 cleanup failure 테이블에 source type과 storage key를 기록한다.
- 동시 요청은 콘텐츠별 저장 경로와 업로드 token으로 충돌 범위를 줄이고, 저장 시점에는 정화된 HTML을 기준으로 최종 보존 파일만 남긴다.

### CKEditor adapter

- CKEditor 모듈은 textarea별 upload endpoint 설정을 읽어 adapter를 붙인다.
- 소유 모듈은 upload endpoint, 권한, CSRF, 업로드 token, 용량, 확장자/MIME 정책, 응답 payload를 제공한다.
- CKEditor 모듈은 콘텐츠/커뮤니티 테이블을 직접 알지 않는다.
- upload response는 최소 `url`을 포함한다.

## 구현 분할

1. 콘텐츠 관리자 CKEditor upload endpoint와 프록시 image endpoint 추가
2. 콘텐츠 저장/수정에서 정화된 HTML 기준 임시 파일 이동과 미참조 파일 정리
3. 콘텐츠 삭제와 본문 이미지 디렉터리 정리 연결
4. 콘텐츠 본문 파일 실패 기록과 opportunistic cleanup 연결
5. DB 없는 경로 기반 정책 문서화
6. CKEditor textarea별 upload adapter 설정 연결
7. 콘텐츠 smoke/failure 테스트와 문서 업데이트
8. 커뮤니티, 팝업레이어, 관리자 설정형 textarea 적용 범위를 구현 상태에 맞춰 대조

## 구현 전 체크

- 콘텐츠 모듈의 HTML sanitizer 기준을 먼저 확인하고, sanitizer가 제거한 이미지가 보존 경로로 이동되지 않게 한다.
- `.tools/bin/check.php`에 새 endpoint 권한/CSRF/계약 검사가 필요한지 확인한다.
- 모듈 개발 가이드, 관리자 화면 필드 가이드, 보안/개인정보 가이드, 스모크 테스트 기준, 배포/트러블슈팅 문서 반영 범위를 구현 이슈에 포함한다.

## 이번 작업의 처리

이번 점검에서는 정책 결론 보완을 완료했고, 이후 사용자 보완에 따라 DB 없는 경로 기반 구현으로 조정했다. #210의 핵심 기준은 편집기 모듈 독립성, 소유 모듈의 저장 경로 책임, 정화된 HTML 기준의 보존/삭제 판단이다.
