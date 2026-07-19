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
| 공개 업로드 이미지 응답 캐시 | 회원 프로필 이미지처럼 공개 경로로 제공되는 업로드 이미지는 권한/개인정보별 HTML과 분리하고, 파일명 또는 query version으로 갱신되게 한다. 로컬 파일 응답은 `Cache-Control`, `ETag`, `Last-Modified`를 보내고 조건부 요청이 맞으면 `304 Not Modified`로 끝낼 수 있다. |
| 공개 이미지 썸네일 캐시 | 원본이 공개로 노출 가능한 이미지일 때만 `storage/cache/thumbnails` 아래에 생성한다. 새 캐시는 `{module_key}/{hash-prefix}/{hash}_{variant}_{source_version}.{ext}` 형식을 사용하며, 배포 규칙은 생성 파일명 패턴의 JPEG/PNG/GIF/WebP만 직접 열 수 있게 제한해야 한다. 원본 교체 감지는 모듈 제공 `source_version`/checksum, S3 `VersionId`/ETag/LastModified, 로컬 mtime/size 순으로 산출한 source version이 담당한다. 호출자가 checksum/source version과 MIME을 제공하면 helper는 원본 파일 또는 S3 원본을 열기 전에 기존 cache file을 먼저 확인하고, cache hit이면 원본 검증/다운로드 없이 캐시 URL을 반환해야 한다. |
| 내부 URL 임베드 fragment 캐시 | URL 임베드 helper가 `storage/cache/embeds` 아래에 sanitized HTML fragment를 저장해 같은 공개 baseline 내부 URL 임베드를 다시 렌더링할 때 대상 계약 실행을 건너뛸 수 있다. 직접 공개 URL로 제공하지 않으며, 대상 모듈이 `fragment_cache_public`을 명시하고 익명 공개/비유료/비권한 조건과 target cache version을 제공할 때만 생성한다. |
| 공개 메뉴 메타데이터 캐시 | 사이트 메뉴의 published tree와 콘텐츠·퀴즈·설문 사이드 그룹 메뉴처럼 계정별 권한이 없는 좁은 공개 메타데이터만 `storage/cache/public-data` 아래에 저장한다. 최종 HTML, 현재 항목, 로그인 이동 경로, 계정별 권한은 캐시하지 않는다. |
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

관리자는 `/admin/storage-cache`에서 `storage/cache/thumbnails` 아래의 공개 썸네일 파일 캐시를 파일 스캔 방식으로 확인할 수 있다. 이 화면은 helper가 만든 파일명 패턴의 이미지 캐시만 대상으로 하며, 생성 내역은 파일 수정 시각 기준의 기간 필터와 module key 필터로 집계한다. DB 기록을 별도로 만들지 않기 때문에 기존 캐시 파일을 이관하거나 수동 배포한 뒤에도 실제 파일 트리를 기준으로 현황을 볼 수 있다. 스캔된 파일 목록은 관리자 공통 페이지당 표시 수에 따라 페이지로 나누되 전체 집계와 정리 대상 수는 현재 필터의 모든 파일을 기준으로 유지한다.

기간별 정리는 현재 조회 조건에 맞는 썸네일 파일 캐시만 삭제하고, 원본 파일이나 모듈 데이터는 변경하지 않는다. 삭제 작업은 하단 sticky 액션에서 확인 모달을 열고, 관리자 `delete` 권한, CSRF, 확인 문구, 감사 로그를 요구한다. 캐시 정책의 핵심은 원본 변경 시 정확한 시점에 새 캐시 URL이 나오고, 더 이상 필요하지 않은 파생 파일을 운영자가 확인해 지울 수 있는 것이다. 따라서 모듈은 가능한 한 `source_version` 또는 checksum을 제공하고, 원본 교체/삭제 작업 뒤에는 `sr_thumbnail_delete_variants()`를 호출해 같은 원본 key의 기존 variant를 정리한다.

S3 원본 이미지는 cache miss 또는 checksum/source version이 없어 선조회가 불가능할 때 `HeadObject`로 크기와 version marker를 확인한 뒤 임시 파일로 내려받아 local `storage/cache/thumbnails`에 썸네일 캐시를 생성한다. S3 `VersionId`가 있으면 presigned `GetObject`에도 같은 version을 지정해 HEAD와 GET 사이의 원본 변경 경합을 줄인다. 썸네일 캐시 저장소 자체를 S3로 바꾸는 기능은 adapter 기반 list/delete 계약이 필요하므로 후속 범위로 둔다.

## 사이트 메뉴 캐시

`site_menu` 모듈은 공개 header/footer, 서비스 레이아웃, 사이드바에서 같은 메뉴가 반복 렌더링될 수 있으므로, published enabled menu 이름과 enabled item tree 데이터만 `storage/cache/public-data/site-menu-tree` 아래 JSON 파일과 요청 단위 메모리에 캐시한다. 현재 URL 활성 표시, `/login?next=...` 보정, 커뮤니티 게시판 context 판정, slot class, 최종 HTML은 렌더 단계에서 요청별로 계산하며 캐시하지 않는다. 파일 경로와 payload에는 namespace·cache key별 generation을 반영한다. 무효화와 겹쳐 이전 generation을 잡고 있던 읽기 요청은 DB 조회 뒤에도 오래된 generation으로 캐시를 다시 쓸 수 없다.

관리자 화면의 메뉴 저장, 항목 저장, 정렬, 삭제는 초안 테이블만 변경하므로 공개 렌더링 cache를 무효화하지 않는다. `공개 반영`이 초안을 공개 메뉴 테이블로 교체하거나 신규 설치 seed가 공개 항목을 만들면 `sr_site_menu_clear_cache()`가 요청 메모리와 cache key generation을 함께 무효화한다. generation marker 기록에 실패하면 공개 반영 결과는 유지하되 관리자 토스트와 오류 로그로 저장소 쓰기 권한 점검을 요구한다. 이전 payload 파일 삭제가 실패해도 새 generation에서는 읽히지 않으며 삭제 실패는 오류 로그에 남긴다.

콘텐츠·퀴즈·설문 공개 사이드바의 사용 상태 그룹 메뉴 원본은 같은 `storage/cache/public-data/public-side-menu` 저장소를 사용한다. 캐시에는 그룹 key와 표시명만 두고 현재 항목 표시는 요청마다 적용한다. 그룹 생성·수정·삭제 시 각 모듈 helper가 자기 cache key generation만 무효화하며, 콘텐츠 그룹 상태 일괄 변경처럼 helper 밖에서 상태를 바꾸는 action도 commit 직후 같은 무효화를 호출한다. 한 모듈의 key 무효화는 같은 namespace의 다른 모듈 key generation을 바꾸지 않는다. 커뮤니티 사이드 게시판 메뉴는 기존 `community-board` 활성 게시판 파일 캐시를 재사용하고 읽기 권한과 현재 게시판 판정은 요청별로 유지한다.

## 반복 렌더링 계약 캐시

공개 레이아웃 후보, 로고 위치 후보, output slot renderer 목록처럼 한 요청 안에서 반복 조회되는 모듈 계약 목록은 요청 단위 메모리 캐시를 사용할 수 있다. 이 cache key에는 enabled/installed 모드, locale, `SR_MODULE_CONTRACT_VERSION`처럼 출력 후보에 영향을 주는 marker를 반영하고, cache value는 배열, 문자열, 숫자, bool 같은 직렬화 가능한 데이터만 담는다.

활성/설치 모듈 key 목록과 계약 파일 경로 목록은 같은 요청에서 라우팅, 레이아웃, 선택 모듈 연동이 반복 참조하므로 PDO 연결과 registry 변경 token을 cache key로 하는 요청 단위 메모리 캐시를 사용한다. 모듈 설치, 활성화, 비활성화, 설치 실패 상태 전환, 파일 전용 버전 동기화는 registry cache를 즉시 비워 같은 요청의 후속 판정이 이전 상태를 사용하지 않게 한다. 레이아웃 option, 로고 position, 출력 slot 계약처럼 계약 목록에서 파생한 기존 요청 캐시도 같은 registry 변경 token을 cache key에 포함한다. 계약 파일의 반환값과 renderer 결과를 모듈 registry cache 자체에 넣지는 않는다.

사이트 설정과 모듈 설정의 요청 단위 메모리 캐시는 PDO 연결별로 분리한다. 일반 HTTP 요청은 하나의 연결을 사용하더라도 fixture, 설치/업데이트 점검, 장기 실행 CLI가 같은 프로세스에서 둘 이상의 DB 연결을 사용할 수 있으므로 module key만으로 cache를 공유하지 않는다. 설정 저장 후 cache token 무효화는 모든 연결에 적용하고, 다음 조회는 각 연결에서 자기 설정을 다시 읽는다.

`sr_render_output_slot()`은 renderer 결과 HTML을 캐시하지 않는다. output slot cache는 `module_key`, `contract_name`, `contract_version`, `contract_file` metadata만 저장하고, 실제 계약 파일 로딩, callable 확인, renderer 실행은 요청 흐름에서 명시적으로 수행한다. 로그인 상태 HTML, 관리자 HTML, CSRF token, 개인정보, 권리 상태는 이 캐시 대상이 아니다.

## DB와 목록 성능

1. 관리자 목록은 기본적으로 페이지네이션을 사용한다. 단, `/admin/modules`처럼 DB 성장형 업무 데이터가 아니라 배포된 모듈/플러그인 파일과 설치 행을 조율하는 bounded 관리 화면은 전체 표시를 허용하고, 표준 테이블 UI와 헤더 정렬, 스티키 섹션 탭 같은 빠른 이동 수단을 둔다.
2. 검색 조건, 상태 필터, 정렬 컬럼은 허용 목록으로 제한한다.
3. 대량 일괄 작업은 [관리자 UI 작성 기준](admin-ui-guide.md)의 즉시 제한형/작업 테이블형 기준을 따른다.
4. 금액성 원장, 알림, 개인정보, 감사 로그, 작업 queue처럼 증가하는 테이블은 조회 패턴에 맞는 인덱스를 설치 SQL과 update SQL에 함께 둔다.
5. 핵심 인덱스는 설치 SQL과 관련 이슈에 기록하고, 설치 SQL에서 marker가 빠지면 `.tools/bin/check-performance-baseline.php`가 실패해야 한다.
6. 관리자 CSV export는 타입별 행 상한을 두고, 감사 로그 metadata에 실제 적용 상한을 남긴다.
7. 주요 관리자 목록의 페이지네이션, 캐시 경로, 핵심 인덱스, sitemap/export 상한, 관리자 CSV export 상한은 관련 코드와 이슈에서 추적한다.
8. `php .tools/bin/check.php`는 SQL 파일 비어 있음, 모듈 계약, 일부 인덱스/정합성 marker를 확인하지만 실제 쿼리 실행 계획을 증명하지 않는다. 릴리스 후보에서는 느린 화면을 수동 점검 기록에 남긴다.

커뮤니티 게시글처럼 큰 테이블을 관리자 lookup에서 참조할 때는 offset pagination과 기본 count를 피하고, `id < cursor` 최신순 조회와 `LIMIT + 1` 방식의 `has_more` 계산을 기본으로 한다. 보드가 선택된 최신순/상태 조회는 `(board_id, status, id)` 계열 인덱스에 맞추고, 보드 없이 상태만 거는 최신순 조회를 허용하면 `(status, id)` 인덱스를 설치 SQL과 update SQL에 함께 둔다. 홈/위젯 인기글처럼 공개 baseline 전체에서 `view_count DESC, id DESC`를 쓰는 경로는 `(status, view_count, id)` 인덱스와 후보 게시글 선제 `LIMIT`을 기준으로 측정한다. 제목 `LIKE '%keyword%'` 검색은 보조 fallback으로만 두고, 텍스트 최소 길이와 강한 limit, 가능하면 보드 필터로 범위를 좁힌다.

커뮤니티 게시글 읽기 화면의 댓글은 전체를 한 번에 조회하지 않고 `status = published`와 스레드 표시 순서를 유지한 숫자 페이지로 조회한다. 페이지당 댓글 수는 커뮤니티 환경설정 `comments_per_page`의 1~100 값을 기본으로 쓰며 새 설치와 저장값이 없는 환경의 기본값은 20이다. 게시판 설정 `comments_per_page`가 1~100이면 게시판 값을 우선하며 0이면 전역값을 상속한다. 기존에 저장된 운영값은 기본값 변경으로 덮어쓰지 않는다. 공개 화면의 페이지 번호는 JavaScript에서 댓글 영역만 교체해 본문 페이지 이동과 추가 조회수·유료 열람 처리를 일으키지 않는다. `comment_fragment=1` 요청은 댓글 영역만 응답하고 게시글 본문, 첨부, 시리즈, 공통 레이아웃 렌더링을 건너뛰며 동적 HTML 공통 `no-store` 정책을 따른다. JavaScript를 사용할 수 없는 경우 같은 숫자 URL로 이동할 수 있으며 서버는 이미 승인된 댓글 페이지 열람 세션을 다시 과금하지 않는다. 로그인 상태 HTML, CSRF, 비밀 댓글 권한 결과는 파일 캐시하지 않는다.

기본 커뮤니티 테마의 게시글 읽기 화면은 두 열 레이아웃과 커뮤니티 요약 사이드 영역을 유지하되, 본문 응답 후 `/community/summary` fragment를 같은 origin 요청으로 불러온다. fragment는 계정별 읽기 권한을 다시 적용하고 공개 baseline 최신글·인기글·최신댓글은 기존 `storage/cache/community-feed` snapshot을 재사용한다. 권한별 HTML 응답 자체는 `no-store`이며 피드 캐시를 무효화하거나 별도 피드 사본을 만들지 않는다. JavaScript를 사용할 수 없거나 fragment 요청이 실패해도 게시글 본문은 계속 읽을 수 있고 사이드 영역에는 커뮤니티 홈으로 이동할 수 있는 대체 경로를 둔다.

커뮤니티 댓글의 `thread_root_id`는 최상위 댓글도 자기 `id`를 저장하며 공개 정렬은 `(post_id, status, thread_root_id, depth, id)` 인덱스 순서를 사용한다. 댓글 생성과 게시판 복사 경로는 최상위 댓글 저장 직후 이 값을 확정해야 한다. `2026.07.009` 업데이트는 기존 NULL 답글을 부모 스레드에 연결하고 남은 NULL 행을 독립 최상위 댓글로 정규화한 뒤 인덱스를 교체한다. 대형 댓글 테이블에서는 UPDATE와 인덱스 재구성이 잠금과 추가 디스크를 사용할 수 있으므로 DB 백업과 유지보수 시간 확보 후 적용한다.

콘텐츠, 퀴즈, 설문 공개 상세의 댓글도 `status = published`와 스레드 표시 순서를 유지한 숫자 페이지로 조회하며 페이지당 20개를 표시한다. 이 세 모듈은 동기식 `comment_page` URL을 사용하고, 페이지 범위를 벗어난 요청은 마지막 페이지로 보정한다. 댓글 작성·수정·삭제·숨김 후에는 처리 대상 댓글이 속한 페이지 또는 보정된 현재 페이지로 이동해 20개 이후 댓글도 계속 접근할 수 있게 한다. 댓글 총수는 현재 페이지 row 수가 아니라 전체 게시 댓글 수를 표시한다.

콘텐츠 전체검색, 커뮤니티 전체검색, 커뮤니티 내 글·댓글 목록은 20개 단위 `page` offset을 사용하되 서비스 정책상의 최대 페이지를 두지 않는다. 입력값은 정수 연산이 넘치지 않는 범위에서만 기술적으로 보정하며, 50페이지 이후 결과도 같은 이전·다음 흐름으로 접근할 수 있어야 한다.

댓글 한 페이지 렌더링에서 현재 계정의 관리자·게시판 운영 권한은 요청당 한 번만 조회해 댓글별 판정에 재사용한다. 댓글 작성자 팔로우 상태와 댓글 리액션 수·현재 사용자의 리액션은 현재 페이지의 대상 ID를 묶어 조회하며, 댓글마다 같은 권한·팔로우·리액션 쿼리를 반복하지 않는다. 이 값은 요청 단위 렌더 context이며 다음 요청까지 보존하는 캐시가 아니다.

로그인 회원용 댓글 답글·수정 모달과 댓글 신고 모달은 댓글마다 전체 마크업을 반복하지 않고 댓글 영역에 각각 하나만 렌더링한다. 댓글별 버튼은 대상 ID와 답글 원문 또는 수정에 필요한 원문·비밀 여부만 공유 모달에 전달한다. 회원 답글의 자동등록방지 설정이 `always`인 경우에는 게시글별 회원 답글 검증 key를 단일 폼과 서버 검증에서 함께 사용한다. 비회원 답글·수정 흐름은 부모 댓글별 자동등록방지 context와 비밀번호 입력 계약이 있으므로 같은 방식으로 임의 통합하지 않는다.

커뮤니티 게시글 묶음 feed cache는 `storage/cache/community-feed` 파일을 영속 저장소로 사용한다. 커뮤니티 메인과 게시판 목록 밖 피드의 everyone-discoverable 공개 게시판 baseline 최신글/인기글 feed는 context hash, 게시판 ID set, 정렬, 표시 수, locale, 정책 버전을 기준으로 snapshot JSON 파일을 저장한다. baseline 후보는 게시판별 `커뮤니티 피드 노출`이 켜진 게시판으로 제한한다. 이 설정이 꺼진 게시판의 글, 댓글, 시리즈는 커뮤니티 메인, 사이드바, 최근글/인기글 같은 게시판 목록 밖 피드 후보와 feed cache 입력 board set에서 제외한다. 게시글 row에는 `summary_feed_candidate`를 저장해 최신글/인기글 live query가 게시판 설정 join 없이 `summary_feed_candidate = 1`로 후보를 자른다. 신규 글은 작성 시점의 커뮤니티 피드 노출 설정을 복사하고, 관리자 게시판 설정에서 커뮤니티 피드 노출 값을 바꾸면 해당 게시판 글의 후보값을 일괄 동기화한다. cache miss 또는 갱신 필요 상태에서는 live query를 실행하고 같은 요청에서 cache 파일을 갱신한다. 일반 피드는 시간 만료가 아니라 변경 이벤트로 갱신하며, 인기글처럼 조회수 기반으로 자연 변동이 생기는 피드의 재계산 주기는 코드의 feed key 정책으로 고정한다. cache value에는 최종 HTML, CSRF token, 계정별 권한 결과, 계정별 유료 접근권 상태, 본문 전체, 렌더된 썸네일 URL을 넣지 않는다. 저장 snapshot은 post id, board id, 제목, 작성자 account id, 조회수, 공개 댓글 수, 공개 excerpt, 썸네일 source marker, 비밀글/공지사항 표시, 생성/수정 시각처럼 공개 baseline 카드 렌더링에 필요한 좁은 값만 담는다. cache hit는 후보 재선정과 게시글 row hydrate를 건너뛰고 snapshot으로 카드 row를 구성하되, 작성자 label처럼 계정 상태를 반영해야 하는 값은 렌더 단계에서 resolve한다. 게시글 작성/수정/삭제/상태 변경, 댓글 작성, 커뮤니티 피드 노출 설정 변경은 feed cache 파일을 삭제해 다음 조회에서 다시 생성하게 해야 한다.

커뮤니티 활성 게시판 메타 캐시는 `storage/cache/community-board/enabled-boards.json`에 게시판 row와 effective 게시판 설정만 저장한다. 이 값은 계정별 권한 결과나 HTML이 아니라 게시판 공개/읽기 정책, 그룹/전역 설정 상속 결과, 피드 노출 설정처럼 관리자 변경으로만 바뀌는 메타데이터다. 공개 홈은 이 파일로 활성 게시판 목록 재구성과 설정 N+1 조회를 건너뛰고, 이후 계정별 읽기 권한은 요청 시점에 별도로 계산한다. 게시판 생성/수정/삭제, 게시판 설정 변경, 게시판 그룹 설정 변경, 그룹 삭제는 파일을 삭제해 다음 요청에서 재생성하게 해야 한다.

본문 URL 임베드 fragment 캐시는 최신글 공개 baseline과 같은 원칙을 따른다. 콘텐츠 유료 열람, 커뮤니티 paid read/비밀글/비공개 게시판, 퀴즈 회원 그룹 제한, 설문 로그인/회원 그룹 제한처럼 viewer별 계약이 필요한 대상은 fragment cache value에 넣지 않는다. 대상 모듈은 공개 상태나 target cache version이 바뀌는 저장/삭제/상태 변경 후 URL 캐시 row를 갱신 필요 상태로 표시해 이전 fragment가 재사용되지 않게 해야 한다.

## 캐시별 운영 경계

커뮤니티 메인 본문의 그룹/게시판별 최신글 섹션은 게시판별 최신글 context를 재사용해 각 게시판에서 최대 5건을 표시한다. 공개 baseline cache가 없는 게시판만 해당 게시판 범위의 최신글 조회를 실행한다.

| 캐시 | 소유 경계 | 사용자단에서 줄이는 부담 | 갱신 또는 재생성 기준 |
| --- | --- | --- | --- |
| 썸네일 파일 캐시 | `admin` 운영 화면과 공통 thumbnail helper. 생성 여부와 화면별 사용 정책은 호출 모듈이 결정한다. | 공개 이미지 원본 전송량과 원본 이미지 재처리 비용 | 원본 version/checksum/mtime/size가 바뀌면 새 URL을 만들고, 정리된 파일은 다음 화면 요청에서 필요할 때 다시 생성한다. |
| 커뮤니티 피드 캐시 | `community` 모듈 | `summary_feed_candidate`가 켜진 게시글의 공개 baseline 최신글/인기글 후보 조회, 최신댓글 조회, snapshot 구성 비용 | 게시글, 댓글, 게시판 공개/권한/유료 열람 조건 또는 커뮤니티 피드 노출 설정 변경 후 게시글 후보값을 동기화하고 feed cache를 갱신 필요 상태로 표시한다. 조회수 기반 인기글처럼 자연 변동이 있는 피드는 코드에 고정한 feed key 정책으로 주기 재계산할 수 있다. |
| 커뮤니티 활성 게시판 메타 캐시 | `community` 모듈 | 커뮤니티 홈의 활성 게시판/effective 설정 조회와 설정 N+1 계산 비용 | 게시판 생성/수정/삭제, 게시판 설정 변경, 게시판 그룹 설정 변경, 그룹 삭제 후 파일을 삭제하고 다음 요청에서 재생성한다. |
| 사이트 메뉴 트리 캐시 | `site_menu` 모듈과 공통 public data cache helper | 공개 header/footer·서비스 레이아웃·사이드바의 published 메뉴명과 enabled item tree 조회 비용 | 사이트 메뉴 공개 반영과 신규 설치 seed 후 해당 namespace를 비우고 다음 요청에서 재생성한다. 손상되거나 계약과 다른 payload는 렌더 전에 거부한다. |
| 공개 사이드 그룹 메뉴 캐시 | `content`, `quiz`, `survey` 모듈과 공통 public data cache helper | 공개 사이드바의 사용 상태 그룹 key·표시명 조회 비용 | 그룹 생성·수정·삭제와 콘텐츠 그룹 상태 일괄 변경 후 모듈별 key를 비운다. 손상된 그룹 row는 거부하고 DB에서 다시 조회한다. |
| 본문 URL 임베드 저장값 | URL 임베드 helper와 대상 모듈의 URL 계약 | 본문 URL 해석, 대상 조회, 공개 카드 렌더링 비용 | 본문 저장 시 URL cache row를 파생하고, 대상 제목/요약/이미지/공개 상태/version이 바뀌면 대상 모듈이 갱신 필요 상태로 표시한다. |

위 캐시는 모두 cache hit에서 사용자 요청 부담을 줄인다. cache miss, 갱신 필요, 코드 정책상 재계산 대상 상태에서는 공유호스팅 제약상 같은 사용자 요청 안에서 재계산이나 파일 생성이 일어날 수 있으므로, 느린 화면을 판단할 때는 hit 경로와 miss 경로를 나누어 측정한다.

운영 화면은 캐시 소유 경계에 맞춰 요약 지표를 노출한다. `피드 캐시`는 영속 저장소 감지, 상태별 row 수, 마지막 생성/변경 시각, 현재 유효한 feed context와 코드에 고정된 갱신 정책을 보여 주고, `본문 URL 임베드`는 전체 row 수, 상태별 수, 마지막 URL 확인/렌더 확인/변경 시각을 보여 준다. `피드 캐시`의 row 수에는 공개 baseline 최신글/인기글과 익명/public 메인의 최신댓글 snapshot, 게시판 목록 밖 피드 snapshot이 함께 포함될 수 있다. 갱신 대기 row는 이전 조건으로 만든 값이므로 표에 섞지 않고 요약 숫자로만 표시한다. 이 요약은 캐시 상태를 판단하기 위한 읽기 전용 지표이며, 도메인 데이터 수정이나 일괄 재생성 정책은 각 소유 모듈의 별도 작업으로 둔다.

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
