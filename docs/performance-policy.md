# 성능과 캐시 기준

이 문서는 saanraan이 공유호스팅 환경에서 성능을 어떻게 다루는지 정리한다. 목표는 대형 트래픽을 약속하는 것이 아니라, 작은 PHP 실행 기반에서 병목을 예측 가능하게 만들고 보안/개인정보 경계를 깨는 캐시를 피하는 것이다.

운영 상태 점검 기준은 [운영 상태 점검 기준](operational-status.md)과 함께 본다. 성능 수동 점검 결과는 [검증 상태와 증거 기준](verification-status.md)과 `docs/records/`의 릴리스 검증 기록에 연결한다.

## 기본 방향

- 상시 worker, Redis, queue daemon, 별도 cache server를 1.0 기본 요구사항으로 두지 않는다.
- 요청 단위 메모리 캐시는 허용하되, 로그인/권한/CSRF/개인정보가 섞인 HTML 응답을 파일 캐시하지 않는다.
- 도메인별 성능 정책은 owning module이 소유한다. 코어는 DB 연결, 설정 조회, 모듈 계약 로딩, 출력 helper 같은 실행 기반만 보조한다.
- 최적화는 먼저 쿼리 범위, 인덱스, 페이지네이션, 상태 필터를 좁히는 방향으로 한다.
- 파일 캐시를 추가하려면 `storage/cache/` 아래에 두고, [배포 보호 기준](deployment-protection.md)에 따라 웹 직접 접근이 차단되는지 확인한다. 공개 이미지 썸네일처럼 직접 브라우저 캐시가 필요한 예외는 파일명 패턴과 MIME을 제한한 하위 경로만 허용한다.

## 허용하는 캐시

| 종류 | 기준 |
| --- | --- |
| 요청 단위 메모리 캐시 | 설정, 모듈 계약, 테이블 존재 여부처럼 한 요청 안에서 반복 조회되는 값에 사용한다. 요청이 끝나면 폐기된다. |
| 정적 asset 브라우저 캐시 | CSS, JavaScript, 이미지, 폰트처럼 공개 정적 파일에 사용한다. URL version 또는 파일 수정 시각으로 갱신 가능해야 한다. |
| 라이브러리 정의 캐시 | HTML Purifier 정의 캐시처럼 외부 라이브러리 성능 보조에 사용한다. 경로는 `storage/cache/htmlpurifier`처럼 vendor 밖이어야 한다. |
| 공개 업로드 이미지 응답 캐시 | 회원 아바타처럼 공개 경로로 제공되는 업로드 이미지는 권한/개인정보별 HTML과 분리하고, 파일명 또는 query version으로 갱신되게 한다. 로컬 파일 응답은 `Cache-Control`, `ETag`, `Last-Modified`를 보내고 조건부 요청이 맞으면 `304 Not Modified`로 끝낼 수 있다. |
| 공개 이미지 썸네일 캐시 | 원본이 공개로 노출 가능한 이미지일 때만 `storage/cache/thumbnails` 아래에 생성한다. 새 캐시는 `{module_key}/{hash-prefix}/{hash}_{variant}_{source_version}.{ext}` 형식을 사용하며, 배포 규칙은 생성 파일명 패턴의 JPEG/PNG/GIF/WebP만 직접 열 수 있게 제한해야 한다. 원본 교체 감지는 모듈 제공 `source_version`/checksum, S3 `VersionId`/ETag/LastModified, 로컬 mtime/size 순으로 산출한 source version이 담당한다. 호출자가 checksum/source version과 MIME을 제공하면 helper는 원본 파일 또는 S3 원본을 열기 전에 기존 cache file을 먼저 확인하고, cache hit이면 원본 검증/다운로드 없이 캐시 URL을 반환해야 한다. |
| 내부 URL 임베드 fragment 캐시 | `embed_manager`가 `storage/cache/embeds` 아래에 sanitized HTML fragment를 저장해 같은 공개 baseline 내부 URL 임베드를 다시 렌더링할 때 대상 계약 실행을 건너뛸 수 있다. 직접 공개 URL로 제공하지 않으며, 대상 모듈이 `fragment_cache_public`을 명시하고 익명 공개/비유료/비권한 조건과 target cache version을 제공할 때만 생성한다. |
| read-only 상태 점검 결과 기록 | `ops-status.php` 출력처럼 사람이 기록하는 운영 기록은 캐시가 아니라 점검 증거로 다룬다. |

## 금지하거나 보류하는 캐시

| 종류 | 이유 |
| --- | --- |
| 로그인 상태 HTML 파일 캐시 | 권한, CSRF token, 개인정보가 섞일 수 있다. |
| 관리자 화면 HTML 캐시 | 권한과 감사 흐름이 요청마다 달라질 수 있다. |
| 개인정보 export 결과 캐시 | 최신 cleanup/export 계약과 개인정보 보존 정책을 우회할 수 있다. |
| 자산 잔액/권리 상태 장기 캐시 | 중복 차감, 중복 지급, 접근권 오판으로 이어질 수 있다. |
| sitemap 전역 파일 캐시 | 공개/비공개 상태와 모듈 활성화 상태가 바뀌면 오염될 수 있다. 필요하면 모듈별 무효화 기준을 먼저 정의한다. |

## 공개 썸네일 파일 캐시 운영

관리자는 `/admin/storage-cache`에서 `storage/cache/thumbnails` 아래의 공개 썸네일 파일 캐시를 파일 스캔 방식으로 확인할 수 있다. 이 화면은 helper가 만든 파일명 패턴의 이미지 캐시만 대상으로 하며, 생성 내역은 파일 수정 시각 기준의 기간 필터와 module key 필터로 집계한다. DB 기록을 별도로 만들지 않기 때문에 기존 캐시 파일을 이관하거나 수동 배포한 뒤에도 실제 파일 트리를 기준으로 현황을 볼 수 있다.

기간별 정리는 현재 조회 조건에 맞는 썸네일 파일 캐시만 삭제하고, 원본 파일이나 모듈 데이터는 변경하지 않는다. 삭제 작업은 하단 sticky 액션에서 확인 모달을 열고, 관리자 `delete` 권한, CSRF, 확인 문구, 감사 로그를 요구한다. 캐시 정책의 핵심은 원본 변경 시 정확한 시점에 새 캐시 URL이 나오고, 더 이상 필요하지 않은 파생 파일을 운영자가 확인해 지울 수 있는 것이다. 따라서 모듈은 가능한 한 `source_version` 또는 checksum을 제공하고, 원본 교체/삭제 작업 뒤에는 `sr_thumbnail_delete_variants()`를 호출해 같은 원본 key의 기존 variant를 정리한다.

S3 원본 이미지는 cache miss 또는 checksum/source version이 없어 선조회가 불가능할 때 `HeadObject`로 크기와 version marker를 확인한 뒤 임시 파일로 내려받아 local `storage/cache/thumbnails`에 썸네일 캐시를 생성한다. S3 `VersionId`가 있으면 presigned `GetObject`에도 같은 version을 지정해 HEAD와 GET 사이의 원본 변경 경합을 줄인다. 썸네일 캐시 저장소 자체를 S3로 바꾸는 기능은 adapter 기반 list/delete 계약이 필요하므로 후속 범위로 둔다.

## 사이트 메뉴 캐시

`site_menu` 모듈은 공개 header/footer와 서비스 레이아웃에서 같은 메뉴가 한 요청 안에 반복 렌더링될 수 있으므로, enabled menu와 enabled item tree 데이터만 요청 단위 메모리 캐시한다. 현재 URL 활성 표시, `/login?next=...` 보정, 커뮤니티 게시판 context 판정, slot class, 최종 HTML은 렌더 단계에서 요청별로 계산하며 캐시하지 않는다.

관리자 화면의 메뉴 저장, 항목 저장, 정렬, 삭제는 초안 테이블만 변경하므로 공개 렌더링 cache를 무효화하지 않는다. `공개 반영`이 초안을 공개 메뉴 테이블로 교체하거나 신규 설치 seed가 같은 요청 안에서 후속 렌더링을 수행하는 경우를 대비해 `sr_site_menu_clear_runtime_cache()`로 요청 cache를 비울 수 있다. 파일 캐시나 공유 캐시를 추가하려면 초안 공개 반영, menu key 변경, 설치 seed의 무효화 기준을 별도 구현 범위에서 검증해야 한다.

## 반복 렌더링 계약 캐시

공개 레이아웃 후보, 로고 위치 후보, output slot renderer 목록처럼 한 요청 안에서 반복 조회되는 모듈 계약 목록은 요청 단위 메모리 캐시를 사용할 수 있다. 이 cache key에는 enabled/installed 모드, locale, `SR_MODULE_CONTRACT_VERSION`처럼 출력 후보에 영향을 주는 marker를 반영하고, cache value는 배열, 문자열, 숫자, bool 같은 직렬화 가능한 데이터만 담는다.

`sr_render_output_slot()`은 renderer 결과 HTML을 캐시하지 않는다. output slot cache는 `module_key`, `contract_name`, `contract_version`, `contract_file` metadata만 저장하고, 실제 계약 파일 로딩, callable 확인, renderer 실행은 요청 흐름에서 명시적으로 수행한다. 로그인 상태 HTML, 관리자 HTML, CSRF token, 개인정보, 권리 상태는 이 캐시 대상이 아니다.

## DB와 목록 성능

1. 관리자 목록은 기본적으로 페이지네이션을 사용한다.
2. 검색 조건, 상태 필터, 정렬 컬럼은 허용 목록으로 제한한다.
3. 대량 일괄 작업은 [관리자 UI 작성 기준](admin-ui-guide.md)의 즉시 제한형/작업 테이블형 기준을 따른다.
4. 금액성 원장, 알림, 개인정보, 감사 로그, 작업 queue처럼 증가하는 테이블은 조회 패턴에 맞는 인덱스를 설치 SQL과 update SQL에 함께 둔다.
5. 핵심 인덱스는 설치 SQL과 관련 이슈에 기록하고, 설치 SQL에서 marker가 빠지면 `.tools/bin/check-performance-baseline.php`가 실패해야 한다.
6. 관리자 CSV export는 타입별 행 상한을 두고, 감사 로그 metadata에 실제 적용 상한을 남긴다.
7. 주요 관리자 목록의 페이지네이션, 캐시 경로, 핵심 인덱스, sitemap/export 상한, 관리자 CSV export 상한은 관련 코드와 이슈에서 추적한다.
8. `php .tools/bin/check.php`는 SQL 파일 비어 있음, 모듈 계약, 일부 인덱스/정합성 marker를 확인하지만 실제 쿼리 실행 계획을 증명하지 않는다. 릴리스 후보에서는 느린 화면을 수동 점검 기록에 남긴다.

커뮤니티 게시글처럼 큰 테이블을 관리자 lookup에서 참조할 때는 offset pagination과 기본 count를 피하고, `id < cursor` 최신순 조회와 `LIMIT + 1` 방식의 `has_more` 계산을 기본으로 한다. 보드가 선택된 최신순/상태 조회는 `(board_id, status, id)` 계열 인덱스에 맞추고, 보드 없이 상태만 거는 최신순 조회를 허용하면 `(status, id)` 인덱스를 설치 SQL과 update SQL에 함께 둔다. 홈/위젯 인기글처럼 공개 baseline 전체에서 `view_count DESC, id DESC`를 쓰는 경로는 `(status, view_count, id)` 인덱스와 후보 게시글 선제 `LIMIT`을 기준으로 측정한다. 제목 `LIKE '%keyword%'` 검색은 보조 fallback으로만 두고, 텍스트 최소 길이와 강한 limit, 가능하면 보드 필터로 범위를 좁힌다.

커뮤니티 게시글 묶음 feed cache는 `sr_community_feed_cache` DB 테이블을 영속 저장소로 사용한다. 사용자 커뮤니티 홈의 everyone-discoverable 공개 게시판 baseline 최신글/인기글 feed는 context hash, 게시판 ID set, 정렬, 표시 수, locale, 정책 버전을 기준으로 snapshot을 저장한다. baseline 후보는 게시판별 `커뮤니티 홈에 표시`가 켜진 게시판으로 제한한다. 이 설정이 꺼진 게시판의 글, 댓글, 시리즈는 커뮤니티 홈 후보와 feed cache 입력 board set에서 제외한다. 게시글 row에는 `home_feed_candidate`를 저장해 홈 최신글/인기글 live query가 게시판 설정 join 없이 `home_feed_candidate = 1`로 후보를 자른다. 신규 글은 작성 시점의 게시판 홈 표시 설정을 복사하고, 관리자 게시판 설정에서 홈 표시 값을 바꾸면 해당 게시판 글의 후보값을 일괄 동기화한다. cache miss, 갱신 필요, 만료 상태에서는 live query를 실행하고 같은 요청에서 cache row를 갱신한다. cache value에는 최종 HTML, CSRF token, 계정별 권한 결과, 계정별 유료 접근권 상태, 본문 전체, 렌더된 썸네일 URL을 넣지 않는다. 저장 snapshot은 post id, board id, 제목, 작성자 account id, 조회수, 공개 댓글 수, 공개 홈 excerpt, 썸네일 source marker, 비밀글 표시, 생성/수정 시각처럼 공개 baseline 홈 카드 렌더링에 필요한 좁은 값만 담는다. 공개 홈 cache hit는 후보 재선정과 게시글 row hydrate를 건너뛰고 snapshot으로 카드 row를 구성하되, 작성자 label처럼 계정 상태를 반영해야 하는 값은 렌더 단계에서 resolve한다. 게시글 작성/수정/삭제/상태 변경, 댓글 작성, 게시판 홈 표시 설정 변경은 feed cache row를 갱신 필요 상태로 표시해야 한다.

본문 URL 임베드 fragment 캐시는 최신글 공개 baseline과 같은 원칙을 따른다. 콘텐츠 유료 열람, 커뮤니티 paid read/비밀글/비공개 게시판, 퀴즈 회원 그룹 제한, 설문 로그인/회원 그룹 제한처럼 viewer별 계약이 필요한 대상은 fragment cache value에 넣지 않는다. 대상 모듈은 공개 상태나 target cache version이 바뀌는 저장/삭제/상태 변경 후 URL 캐시 row를 갱신 필요 상태로 표시해 이전 fragment가 재사용되지 않게 해야 한다.

## 캐시별 운영 경계

| 캐시 | 소유 경계 | 사용자단에서 줄이는 부담 | 갱신 또는 재생성 기준 |
| --- | --- | --- | --- |
| 썸네일 파일 캐시 | `admin` 운영 화면과 공통 thumbnail helper. 생성 여부와 화면별 사용 정책은 호출 모듈이 결정한다. | 공개 이미지 원본 전송량과 원본 이미지 재처리 비용 | 원본 version/checksum/mtime/size가 바뀌면 새 URL을 만들고, 정리된 파일은 다음 화면 요청에서 필요할 때 다시 생성한다. |
| 커뮤니티 홈 피드 캐시 | `community` 모듈 | `home_feed_candidate`가 켜진 게시글의 공개 baseline 최신글/인기글 후보 조회, 최신댓글 조회, snapshot 구성 비용 | 게시글, 댓글, 게시판 공개/권한/유료 열람 조건 또는 게시판 홈 표시 설정 변경 후 게시글 후보값을 동기화하고 feed cache를 갱신 필요 상태로 표시한다. |
| 본문 URL 임베드 저장값 | `embed_manager` 모듈과 대상 모듈의 URL 계약 | 본문 URL 해석, 대상 조회, 공개 카드 렌더링 비용 | 본문 저장 시 URL cache row를 파생하고, 대상 제목/요약/이미지/공개 상태/version이 바뀌면 대상 모듈이 갱신 필요 상태로 표시한다. |

세 캐시는 모두 캐시 hit에서 사용자 요청 부담을 줄인다. cache miss, 만료, 갱신 필요 상태에서는 공유호스팅 제약상 같은 사용자 요청 안에서 재계산이나 파일 생성이 일어날 수 있으므로, 느린 화면을 판단할 때는 hit 경로와 miss 경로를 나누어 측정한다.

운영 화면은 캐시 소유 경계에 맞춰 요약 지표를 노출한다. `홈 피드 캐시`는 영속 저장소 감지, 상태별 row 수, 마지막 생성/변경 시각과 다음 만료 시각을 보여 주고, `본문 URL 임베드`는 전체 row 수, 상태별 수, 마지막 URL 확인/렌더 확인/변경 시각을 보여 준다. `홈 피드 캐시`의 row 수에는 공개 baseline 최신글/인기글과 익명/public 메인의 최신댓글 snapshot이 함께 포함될 수 있다. 이 요약은 캐시 상태를 판단하기 위한 읽기 전용 지표이며, 도메인 데이터 수정이나 일괄 재생성 정책은 각 소유 모듈의 별도 작업으로 둔다.

## 공유호스팅 한계

공유호스팅에서는 요청 시간, 메모리, cron 간격, 파일 I/O가 제한될 수 있다.

- 긴 작업은 한 요청에 모두 넣지 않고 배치 또는 작업 테이블형으로 나눈다.
- cron이 없거나 느린 환경에서는 read-only 운영 점검 명령과 관리자 재시도 흐름을 사용한다.
- 실시간 발송, 실시간 만료, 실시간 대량 재계산을 보장한다고 설명하지 않는다.
- 대형 커뮤니티 규모의 캐시 서버나 worker 기반 확장은 1.0 기본 범위가 아니다.

## 보안과 개인정보 기준

캐시를 추가할 때는 [보안 체크리스트](security-checklist.md)의 캐시 항목을 먼저 확인한다.

- 로그인 상태나 권한에 따라 달라지는 HTML을 캐시하지 않는다.
- CSRF token이 포함된 화면을 캐시하지 않는다.
- 개인정보가 포함된 화면을 캐시하지 않는다.
- locale처럼 출력에 영향을 주는 값은 캐시 키에 반영한다.
- 직접 `Cache-Control` 헤더를 추가하는 경우 이미지, 파일 다운로드, stream 응답처럼 HTML이 아닌 응답인지 확인하고 `.tools/bin/check-performance-baseline.php`의 허용 목록에 함께 반영한다.

## 기록 기준

성능 또는 캐시 동작을 바꾸는 변경은 다음 중 해당 항목을 남긴다.

- 어떤 요청 또는 목록이 빨라지는지
- 캐시 key와 무효화 기준
- 개인정보, 권한, CSRF token 포함 여부
- `storage/cache/` 사용 여부와 배포 보호 확인
- 페이지네이션, 인덱스, 작업 테이블형 전환 여부
- 실행한 검증 명령과 수동 점검 결과
