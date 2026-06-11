# 스모크 테스트 기준

이 문서는 설치 직후, 배포 전, 운영 수정 후 최소한으로 확인할 HTTP 검증 범위를 정리한다. 목표는 모든 기능을 자동 테스트하는 것이 아니라, 핵심 요청 흐름이 깨졌거나 내부 파일이 노출되는 문제를 빠르게 발견하는 것이다.

신규 설치 스모크를 수행할 때 `site_menu`와 콘텐츠/커뮤니티/퀴즈/설문 중 일부를 선택했다면 설치 직후 `header` 메뉴에 홈과 선택한 서비스 모듈의 `service_domain.main_page` 링크만 생성되고, 로그인/회원가입이 자동 삽입되지 않는지 DB와 공개 헤더에서 확인한다. `site_menu`를 선택하지 않은 설치에서는 이 seed가 실행되지 않아야 한다.

SEO 설정 화면 스모크에서는 사이트맵 확인 링크와 URL 복사 버튼이 설정 저장 submit을 발생시키지 않는지, 사이트맵 확인 링크와 robots 파일 확인 버튼이 새 탭으로 열리는지, 복사 성공/실패 피드백이 버튼 텍스트로 돌아오는지, robots 파일 확인 버튼이 카드 헤더 오른쪽에 표시되고 미리보기 텍스트가 좁은 화면에서도 넘치지 않는지 확인한다.

## 기본 정적 점검

코드 변경 후 먼저 기본 점검을 실행한다.

```sh
php .tools/bin/check.php
```

이 점검은 다음을 확인한다.

```text
git diff --check
SQL 파일 비어 있음 여부
모듈 기본 계약 파일 구성
관리자 메뉴 path와 paths.php GET route 일치
전체 PHP 문법
보상/설문 정합성 회귀 점검
```

현재 통합 점검은 코드 상태뿐 아니라 진행 중인 정책 TODO도 함께 검사한다. 보상 중복 방지 기준은 `.tools/bin/check-reward-abuse-standards.php`, 설문 통계/개인정보/완료 화면 회귀 기준은 `.tools/bin/check-survey-consistency.php`, 임베드 매니저 계약 구조 기준은 `.tools/bin/check-embed-manager-contracts.php`가 확인하며 모두 `.tools/bin/check.php`에 포함된다. 이후 실패가 발생하면 실패 항목이 현재 변경의 회귀인지, 새 정책 점검 추가로 드러난 기존 보완 항목인지 먼저 분리한다. `.tools/bin/check.php`의 PHP 문법 검사는 저장소 코드와 도구 파일을 대상으로 하며, 환경별 비밀 설정과 런타임 파일이 들어가는 `config/`, `storage/` 디렉터리는 제외한다.

설문 모듈을 확인할 때는 기타 선택지를 고른 공개 응답이 기타 텍스트를 요구하고, 분석 CSV와 개인정보 사본의 `other_text`에 저장되는지 함께 본다.

본문 임베드 변경을 확인할 때는 콘텐츠 관리자 화면에서 커뮤니티 게시글/퀴즈/설문 검색 결과를 CKEditor 본문에 삽입하고 저장 후 `sr_embed_manager_refs`가 동기화되는지 확인한다. 커뮤니티 작성 화면에서는 콘텐츠/퀴즈/설문 검색 후보가 공개 후보만 노출되는지 확인한다. 공개 콘텐츠/게시글 화면은 marker fallback blockquote가 카드/CTA로 치환되고, 퀴즈·설문 완료 후 `return_to` 링크로 원래 화면에 돌아갈 수 있어야 한다. 가능하면 브라우저 QA로 CKEditor 삽입까지 확인하고, 불가능하면 로컬 수동 smoke 결과와 미실행 사유를 기록한다.

비밀글/비밀댓글 변경을 확인할 때는 커뮤니티 전역과 게시판 설정, 콘텐츠 환경설정, 퀴즈/설문 항목 설정의 허용 스위치를 각각 껐다 켠다. 허용이 꺼진 상태에서 `is_secret=1`을 직접 POST해도 새 게시글/댓글은 공개로 저장되어야 하며, 기존 비밀 댓글은 수정 요청 후에도 비밀 상태가 유지되어야 한다. 허용이 켜진 상태에서는 사용자 작성/수정 화면에 비밀 선택지가 표시되고, 커뮤니티 비밀 게시글 본문·첨부·퀴즈 연결·댓글·SEO/OG 설명·커뮤니티 링크 카드 검색 결과는 작성자 또는 관리자/운영 권한자 외에는 노출되지 않아야 한다. 비밀 댓글 본문은 댓글 작성자, 대상 글/콘텐츠 작성자, 댓글 관리자 권한자만 볼 수 있고 멘션 알림을 만들지 않아야 한다.

커뮤니티 일반 게시글 작성/수정 화면에서는 SEO/OG 메타 필드가 보이지 않아야 한다. 직접 POST로 `seo_title`, `seo_description`, `og_title`, `og_description`을 보내도 신규 게시글에는 저장되지 않고, 기존 SEO/OG 값이 있는 게시글을 작성자가 수정해도 해당 값이 빈 값이나 조작된 POST 값으로 덮어써지지 않아야 한다. 공개 상세 화면의 title, description, OG 메타 fallback은 제목과 공개 가능한 본문 기준으로 계속 동작해야 한다.

1.0 릴리스 후보에서는 정적 점검 통과만으로 완료 판정하지 않는다. [1.0 범위 잠금 기준](1.0-scope.md)의 포함 모듈을 기준으로 HTTP 스모크와 필요한 브라우저 수동 점검을 함께 기록한다.

고부하 관리자 작업을 확인할 때는 production 데이터에서 파괴적 smoke test를 실행하지 않는다. 재계산/복사/삭제/저장소 정리 테스트는 local 또는 staging 더미 데이터로 수행하고, 소량/중량/대량 기준에서 부하 등급 안내, 확인 문구 서버 거부, 배치 진행/재시도 상태, 감사 로그 metadata가 남는지 확인한다. 작업 테이블형은 lock 만료 takeover 뒤 이전 `lock_token`의 늦은 쓰기를 거부하는지 확인하고, 재시도 시 대상 단위 완료 마커/map/dedupe로 중복 원장·중복 발송·중복 복사가 생기지 않는지 확인한다. 도메인 쓰기와 map/cursor/count 갱신이 같은 원자적 경계 안에 있는지, query snapshot drift가 절대 50건 이상 또는 10% 이상일 때 재확인 필요 상태로 멈추는지도 확인한다. timeout 유사 상황은 테스트 전용 sleep, 낮은 `max_execution_time`, batch 실패 주입, 중간 요청 중단으로 확인한다. 즉시 제한형 선택 기반 일괄 작업은 선택 없음, 허용되지 않은 `operation_key`, 허용되지 않은 상태, 존재하지 않는 ID, 100건 초과, 이미 같은 상태인 행 건너뜀, 감사 로그 metadata를 확인한다.

## HTTP 스모크 점검

로컬 PHP 내장 서버나 스테이징 서버가 떠 있으면 다음 명령을 실행한다.

```sh
php .tools/bin/smoke-http.php http://127.0.0.1:8080
```

같은 base URL은 환경변수로도 전달할 수 있다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 php .tools/bin/smoke-http.php
```

하위 경로에 설치된 환경은 base URL에 해당 경로를 포함한다. 예를 들어 `https://example.com/saanraan`처럼 실행하면 로그인 보호 redirect도 같은 하위 경로 기준으로 검증한다.

커뮤니티 모듈이 설치되어 있어야 하는 스테이징 검수에서는 404 허용을 제거한 강한 모드로 실행한다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_EXPECT_COMMUNITY=1 \
php .tools/bin/smoke-http.php
```

회원 전용 사이트 모드를 검증할 때는 local 또는 staging에서만 설정을 켜고, 실행 전 `sr_site_settings`의 `site.member_only_enabled`, `site.status`, `site.home_path`, `public_layout_key`와 `member` 모듈 상태를 기록한다. 가능하면 `/admin/settings` 저장 흐름으로 켜서 서버 검증과 감사 로그를 함께 확인하고, 직접 DB update를 쓴 경우에는 smoke profile 준비용 변경으로 구분한다. 성공/실패와 관계없이 원래 설정으로 되돌린 뒤 공개 모드 대표 경로가 기존 기대값으로 돌아왔는지 확인해야 한다.

회원 전용 ON 상태의 대표 HTTP smoke는 다음 profile로 실행한다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_MEMBER_ONLY=1 \
php .tools/bin/smoke-http.php
```

이 profile은 비로그인 `/`, `/ui-kit`, 콘텐츠/커뮤니티 공개 화면 후보가 `/login?next=...`로 이동하는지, 로그인·비밀번호 재설정 같은 인증 예외가 열리는지, 공개 서비스 모듈의 POST/action endpoint가 403 또는 미설치 404로 닫히는지, `robots.txt`가 `Disallow: /`를 반환하는지 확인한다. 회원 전용 OFF 회귀 smoke는 `SR_SMOKE_MEMBER_ONLY` 없이 다시 실행한다.

로컬 PHP 내장 서버는 개발용 router로 실행한다.

```sh
php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
```

router 없이 프로젝트 루트를 문서 루트로 내장 서버를 실행하면 실제 파일이 직접 응답될 수 있으므로 내부 파일 보호 검증에 사용하지 않는다.
개발용 router도 운영 배포 규칙과 같이 공개 asset 경로의 `php`, `phtml`, `phar`, `sql` 파일 직접 응답을 403으로 차단해야 한다.

직접 접근을 차단해야 하는 경로의 정본은 [배포 보호 기준](deployment-protection.md)이다. 이 문서의 HTTP 항목은 그 기준이 실제 서버 응답에서 지켜지는지 빠르게 확인하기 위한 스모크 검증 목록이다.

확인 항목:

```text
/ 응답이 500 없이 열리는지 확인
/login 응답이 500 없이 열리는지 확인
/ui-kit 응답이 500 없이 열리고 Public UI-KIT 화면이 출력되는지 확인
/admin 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/updates 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/content/example 응답이 500 없이 열리지 않고, 미설치/비활성/없는 slug 상태에서는 404 등 허용된 응답으로 막히는지 확인
/admin/content 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
콘텐츠 모듈 설치 환경에서는 published 콘텐츠가 200으로 열리고 draft/hidden 콘텐츠는 공개 접근이 차단되는지 확인. scheduled 콘텐츠는 예약 시각 전 공개 접근이 차단되고, 예약 시각이 지난 뒤 공개/관리자 조회에서 published로 전환되는지 확인. 예약 시각은 현재보다 미래여야 하고, 예약 설정/해제/자동 전환 감사 로그가 남는지 확인한다. `/admin/content` 조회 권한이 있는 관리자 세션에서는 draft/scheduled 콘텐츠 공개 URL 미리보기가 열리고 열람 차감, 다운로드, 완료 버튼 처리가 실행되지 않는지 확인
콘텐츠 그룹을 만든 환경에서는 /content/group?key=... 공개 목록이 200으로 열리고 비사용/보관 그룹은 공개 접근이 차단되는지 확인
콘텐츠 모듈 설치 환경에서는 공용 배너/팝업레이어 직접 선택과 `content.view` 노출 위치 규칙이 공개 콘텐츠에 반영되는지 확인
/admin/content/series에서 콘텐츠 시리즈 등록 모달의 key, 제목, 상태, 공개 범위, 정렬 저장이 서버 검증을 통과하는지 확인. 설명이 서버 제한 길이를 넘으면 빈 값으로 저장하지 않고 오류를 표시해야 한다. 목록의 상태, 공개 범위, key/제목 검색 필터와 key, 제목, 상태, 공개 범위, 회차 수, 정렬, 수정일 헤더 정렬이 허용 목록 기반으로 동작하고, 상태와 공개 범위가 한국어 라벨로 표시되는지 확인한다. 콘텐츠 편집 화면에서 시리즈와 회차를 연결하면 공개 콘텐츠 본문 다음에 active 시리즈 내비게이션이 표시되고, 시리즈 정렬 순서는 서버에서 0 이상 1000000 이하 숫자로 검증되는지 확인한다. 유료 열람 콘텐츠가 포함된 시리즈는 완독 예상 금액을 자산별로 표시하고, 회원 그룹 정책 적용 금액이 다르면 원가와 회원가를 함께 표시하는지 확인한다. hidden/archived/deleted 시리즈 또는 hidden/removed 항목은 공개 출력에서 제외되는지 확인. 코드 배포 후 DB 업데이트 적용 전에는 콘텐츠 목록, 콘텐츠 시리즈 관리, 공개 콘텐츠, 개인정보 내보내기가 시리즈 테이블 누락으로 500을 내지 않아야 한다.

/admin/community/boards, /admin/community/board-groups, /admin/content-groups, /admin/content/series에서 삭제 버튼은 CSRF와 관리자 delete 권한을 요구해야 한다. 게시판 삭제는 게시글이 있어도 가능하며 설정, 설정 소스, 카테고리, 게시글, 댓글, 스크랩, 커뮤니티 시리즈/항목, 첨부 DB 행과 저장소 파일을 함께 삭제해야 한다. 단 사이트 메뉴/배너/팝업/쿠폰 같은 외부 운영 참조가 있으면 게시판 삭제는 차단되어야 하며, 자산 로그는 삭제하지 않아야 한다. 게시판이 없는 게시판 그룹은 그룹 설정과 함께 삭제되고, 연결 게시판이나 외부 운영 참조가 있으면 삭제되지 않아야 한다. 콘텐츠 그룹 삭제는 연결 콘텐츠가 있어도 가능하며 그룹 설정, 콘텐츠, 댓글, 리비전, 시리즈 항목 연결, 파일 링크, 파일 DB 행과 저장소 파일을 함께 삭제해야 한다. 단 사이트 메뉴/초기화면 참조가 있으면 콘텐츠 그룹 삭제는 차단되어야 하며, 콘텐츠 자산 로그와 파일 다운로드 로그는 삭제하지 않아야 한다. 파일 다운로드 로그는 콘텐츠/파일 삭제 후에도 저장 당시 콘텐츠 제목, slug, 파일 제목, 원본 파일명 스냅샷으로 조회와 검색 맥락을 유지해야 한다. 삭제 후 저장소 파일 정리가 실패하면 삭제 완료 문구와 파일 정리 실패 안내가 분리되어 표시되고, 실패한 driver/key가 커뮤니티 또는 콘텐츠 저장소 정리 실패 테이블과 관리 목록 화면에 남아야 한다. 관리자는 실패 항목의 재시도 버튼으로 저장소 삭제를 다시 실행할 수 있고 성공 시 항목이 pending 목록에서 사라져야 한다. 콘텐츠 시리즈 삭제는 콘텐츠 자체를 삭제하지 않고 `sr_content_series_items` 연결만 제거한 뒤 시리즈를 삭제해야 하며, 외부 운영 참조가 있으면 삭제되지 않아야 한다. 각 삭제 성공은 감사 로그에 대상 key/title과 삭제·참조 개수를 남겨야 한다.
/admin/member-group-rules에서 그룹, 조건, 자동 배정 방식, 상태, 최근 평가 헤더 정렬이 허용 목록 기반으로 동작하는지 확인
/admin/member-groups의 수동 배정 모달에서 이미 수동 배정된 회원을 같은 그룹에 다시 배정하면 새 배정이 추가되지 않고 중복 배정 안내 토스트와 감사 로그가 남는지 확인
/admin/members 회원 목록에서 현재 페이지 회원 선택 체크박스, 전체 선택, 선택 수 표시, 세션 일괄 회수가 동작하는지 확인한다. 서버는 `intent=batch_revoke_sessions`, `operation_key=member.revoke_sessions`, `selected_account_ids[]`를 다시 검증해야 하며, 선택 없음, 100건 초과, 존재하지 않는 회원, 현재 관리자 본인, owner가 아닌 관리자의 owner 대상 회수를 거부해야 한다. 성공 시 선택 회원의 활성 세션만 회수하고 감사 로그 metadata에 선택 ID와 회원별 회수 건수, 총 회수 건수를 남겨야 한다. 회원 상태 변경과 선택 회원 그룹 재평가는 이 즉시 제한형 작업에 포함하지 않는다. 세션 회수와 회원 그룹 재평가는 성공/실패 후 GET 화면으로 돌아와야 하며, 브라우저 새로고침으로 토스트나 감사 로그가 반복되지 않아야 한다.
/admin/member-group-rules의 규칙 저장, 그룹 규칙 평가, /admin/member-groups의 회원 규칙 평가도 성공 후 GET 화면으로 돌아와야 하며, 평가 실행 결과 토스트와 감사 로그가 새로고침으로 반복되지 않아야 한다.
/admin/content/submissions, /admin/content/author-applications, /admin/content/authors, /admin/community/board-copy-jobs, /account/content/author-application의 처리 POST는 성공 후 GET 화면으로 돌아와야 한다. 검수/신청/작성자 승인/복사 작업 실행 결과는 한 번만 표시되고 새로고침으로 상태 변경, 알림 생성, 복사 묶음 실행이 반복되지 않아야 한다. `/admin/content/authors`의 작성자 승인 추가/수정은 목록 위 상시 폼이 아니라 승인 목록의 추가/수정 모달에서 처리되는지 확인한다. 작성자 승인 추가 모달의 회원 선택은 직접 숫자 ID 입력만 요구하지 않고 회원 검색 모달에서 선택한 회원 식별자를 저장할 수 있어야 한다.
/admin/community/posts, /admin/community/comments, /admin/quiz/comments, /admin/surveys/comments, /admin/admin-notifications, /admin/notification-deliveries, /admin/surveys/responses, /admin/community/series, /admin/content/series, /admin/privacy-requests 목록의 행 단위 상태 변경은 필터용·생성/편집용 셀렉트를 제외하고 상태 변경 셀렉트를 사용하지 않아야 한다. 현재 상태를 제외한 다음 상태 버튼만 보이고, 삭제·거절·취소·실패·분석 제외 계열은 확인 후 제출되어야 하며, 직접 POST한 허용되지 않은 상태 값은 서버에서 거부되어야 한다. 처리 후에는 기존 필터·검색·정렬·페이지 쿼리로 돌아와야 한다. `/admin/community/posts`의 상태 필터와 행 버튼은 대기->공개->숨김->삭제 순서로 표시하고, `/admin/community/comments`는 현재 댓글 상태 계약에 대기 상태가 없으므로 공개->숨김->삭제 순서로 표시해야 한다. 상태 배지는 삭제됨처럼 상태명을 표시해도 행 작업 버튼은 삭제처럼 실행명을 표시해야 한다.
`/admin/surveys/statistics`의 설문 선택 필터는 상세검색·초기화 버튼 없이 설문 선택과 검색만 표시되어야 한다. 검색 버튼은 좌측에서 설문 셀렉트 바로 다음에 위치하고, CSV 보조 액션은 문항별 통계 섹션 헤더에 표시되어야 한다. 필터와 선택된 설문 요약 섹션 사이에는 공통 카드 간격이 있어야 하며, 문항별 통계는 다른 관리자 목록과 같은 목록 카드/테이블 스타일로 표시되어야 한다.
/admin/banners 목록에서 현재 페이지 배너 선택 체크박스, 전체 선택, 선택 수 표시, 상태 일괄 변경이 동작하는지 확인한다. 서버는 `intent=batch_status`, `operation_key=banner.set_status`, `selected_banner_ids[]`, `target_status`를 다시 검증해야 하며, 다른 모듈에서 참조 중인 enabled 배너는 일괄 비활성화가 차단되어야 한다. `/admin/popup-layers` 목록도 같은 기준으로 `operation_key=popup_layer.set_status`, `selected_popup_ids[]`를 검증하고 참조 중인 enabled 팝업레이어 일괄 비활성화를 차단해야 한다. `/admin/logo-manager` 로고 배치 목록은 `operation_key=logo_manager.set_status`, `selected_logo_ids[]`, `target_status=active|disabled`를 검증하고 선택 없음, 100건 초과, 존재하지 않는 ID를 거부해야 한다. `/admin/community/posts` 게시글 목록은 `intent=batch_post_status`, `operation_key=community.post_set_status`, `selected_post_ids[]`, `target_status=hidden|published`를 검증하고 공개->숨김, 숨김->공개 전이만 일괄 허용해야 한다. 보상 회수 설정이 켜진 환경에서는 회수 실패 시 전체 일괄 변경이 롤백되어야 하며, 첨부파일 상태 복구와 회원 레벨/그룹 재평가가 함께 실행되어야 한다. `/admin/community/comments` 댓글 목록은 `intent=batch_comment_status`, `operation_key=community.comment_set_status`, `selected_comment_ids[]`, `target_status=hidden|published`를 검증하고 공개->숨김, 숨김->공개 전이만 일괄 허용해야 한다. 댓글 보상 회수 실패 시 전체 변경이 롤백되고 회원 레벨/그룹 재평가와 감사 로그 metadata가 남아야 한다. `/admin/notifications` 알림 목록은 `operation_key=notification.set_status`, `selected_notification_ids[]`, `target_status=active|deleted`를 검증하고 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 감사 로그 metadata를 확인한다.
`/admin/community/reports` 신고 목록은 대상 칸에 게시글/댓글 게시물 새 탭 바로가기 아이콘만 표시하고 아이콘 `title`에 게시물 제목이 들어가는지 확인한다. 처리된 신고의 상태 셀에는 감사 로그 권한이 있는 관리자에게 대상 조치 로그 링크가 표시되고, 해당 링크가 `metadata`의 `"report_id":ID` 검색으로 이동하는지 확인한다. `operation_key=community.report_set_status`, `selected_report_ids[]`, `target_status`, `target_action`을 검증하고 현재 페이지 선택, 전체 선택, 공통 검토 메모 적용, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다. 단건과 일괄 대상 조치는 신고 상태를 `resolved`로 저장할 때만 허용되고, `open`, `reviewing`, `dismissed`와 대상 조치가 함께 제출되면 서버에서 거부되어야 한다. 일괄 숨김/삭제는 게시글·댓글 신고에만 적용되며 쪽지 신고가 섞이면 서버에서 거부해야 한다. 일괄 처리 성공 시 대상 조치 적용 건수와 대상 조치 결과 metadata가 남아야 한다. `dismissed` 상태는 이미 적용된 대상 조치를 되돌리지 않는다.
`/admin/admin-notifications` 운영 알림 목록은 권한이 있는 알림만 표시하고, 헤더 드롭다운의 열린 알림 수와 최근 항목이 같은 권한 기준을 따르는지 확인한다. 읽음, 확인, 처리됨, 보관, 다시 열기 POST는 CSRF와 현재 관리자 권한을 다시 검증해야 하며, action URL은 `/admin/...` 내부 상대 경로만 이동 링크로 노출되어야 한다. 같은 dedupe key 이벤트가 다시 발생하면 occurrence count와 최근 발생 시각이 갱신되고, 처리됨/보관 상태였던 알림은 열린 상태로 돌아와야 한다. 보존 정리는 열린 운영 알림을 삭제하지 않고 처리됨/보관됨 운영 알림과 확인 기록만 알림 보관일 기준으로 정리해야 한다. 알림 모듈이 비활성화되었거나 운영 알림 테이블 업데이트 전이면 신고, 신청, 개인정보 요청 같은 원래 업무 저장은 실패하지 않아야 한다.
`/admin/content` 콘텐츠 목록은 `operation_key=content.set_status`, `selected_content_ids[]`, `target_status=draft|published|hidden`을 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다. 예약 상태는 별도 예약 일시가 필요하므로 일괄 상태 변경 대상에서 제외하고, 공개 전환 시 기존 공개일이 없으면 서버 현재 시각으로 공개일을 채워야 한다.
`/admin/content-groups` 콘텐츠 그룹 목록은 `operation_key=content.group_set_status`, `selected_group_ids[]`, `target_status=enabled|disabled|archived`를 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다.
`/admin/content/files` 다운로드 파일 목록은 `operation_key=content.file_set_status`, `selected_file_ids[]`, `target_status=active|hidden`을 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다. 상태 일괄 변경은 파일 삭제나 저장소 정리 작업을 실행하지 않아야 한다.
`/admin/coupons` 쿠폰 종류 목록은 `intent=batch_definition_status`, `operation_key=coupon.definition_set_status`, `selected_definition_ids[]`, `target_status=active|disabled`를 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 감사 로그 metadata를 확인한다. 비활성화 대상에 발급/사용 이력 또는 운영 참조가 있는 쿠폰 정의가 포함되면 최신 `coupon-references.php` 결과로 일괄 변경을 차단해야 한다. 지급 내역의 지급 중지나 만료 처리는 이 즉시 제한형 상태 변경에 포함하지 않는다.
콘텐츠 자산 기능을 활성화한 환경에서는 유료 열람, 유료 다운로드, 완료 버튼 자산 처리가 비로그인 접근을 로그인으로 보내고, 로그인 회원은 잔액/중복 정책에 맞게 처리되는지 확인. 최초 1회와 반복 과금 모두 GET 접근만으로 차감되지 않고 확인 POST 후 처리되어야 하며, 이미 접근권이 있는 once 대상은 재확인 없이 열려야 한다. 다운로드 파일은 `/admin/content/files`에서 먼저 등록한 뒤 콘텐츠 생성/수정 화면에서 연결해야 하며, 숨김 파일은 공개 다운로드와 연결 후보에서 제외되어야 한다. 회원 그룹별 적용을 저장하고 콘텐츠/콘텐츠 그룹/파일에서 여러 적용을 선택한 경우 최종 금액과 `group_policy_snapshot_json`이 로그에 남고, 금액 0 처리나 처리 안 함처럼 최종 금액이 0인 경우 원장 거래 없이 접근권 또는 완료 로그가 `completed` 상태로 남는지 확인. 유료 다운로드는 S3 서명 URL 생성 또는 로컬 파일 경로 확인이 실패하면 자산 차감 없이 오류로 끝나야 하며, 전달 준비가 끝난 뒤에만 차감하고 리다이렉트/스트리밍해야 한다. 최초 설치 후 새 콘텐츠와 새 콘텐츠 그룹의 자산 설정은 사용하지 않음이며 포인트 등 특정 자산이 미리 선택되어 있지 않아야 한다. 콘텐츠 그룹에서 새 콘텐츠를 추가하면 콘텐츠 그룹의 콘텐츠 자체 기본값이 입력폼에 복사되고, 다운로드 파일 기본값과 기존 연결 파일 정책은 바뀌지 않으며 `그룹` 적용 범위는 자동 선택되지 않는지 확인. 콘텐츠 수정 화면에서 `그룹`/`전체` 적용을 선택하면 현재 편집값이 대상 콘텐츠에 한 번 복사되고, 콘텐츠 그룹 설정 변경 후에는 기존 콘텐츠의 자산 설정과 유효 동작이 바뀌지 않아야 한다. 완료 버튼 자산 처리는 콘텐츠 조회만으로 실행되지 않아야 한다. 복합 자산 설정은 자산 선택과 금액 입력이 같은 묶음으로 보이고, 완료 버튼 지급 방향에서는 처리 항목을 하나만 선택할 수 있으며 차감 방향에서는 선택 항목별 금액이 각각 차감되는지 확인한다. 회원 그룹별 적용 선택지는 선택한 자산에 맞는 정책만 보여야 하며, 선택 자산을 바꾸면 맞지 않는 적용 뱃지가 제거되어야 한다. 모든 자산을 해제한 뒤 저장하면 다시 체크되지 않아야 한다.

settlement 기반 복합 차감을 도입한 환경에서는 확인 token 재시도와 동시성 기준을 별도로 확인한다. 클라이언트 요청 토큰은 HTTP attempt가 아니라 구매 의도(intent)마다 확인 화면 렌더 시점에 1회 생성되어야 하며, 같은 토큰으로 POST를 재시도하면 dedupe key가 회원/참조/기준금액/통화/요청 토큰 같은 안정 입력에서만 파생되고, 성공 후에는 잔액 snapshot이나 자산별 계산 결과가 바뀌어도 원장을 다시 만들지 않고 저장된 성공 결과와 표시용 snapshot을 반환해야 한다. 재검증 거부나 실행 실패는 rollback으로 claim row도 사라지므로, 재시도 시 저장된 거부 결과가 아니라 현재 상태로 부작용 없이 재평가되어야 한다. 실행 트랜잭션은 원장 row lock보다 먼저 unique 제약이 있는 claim row를 insert해야 하며, 두 탭 동시 제출은 duplicate-key에서 `processing` 또는 저장된 성공 결과로 흡수되고 lock 획득 뒤에도 claim row 상태를 다시 확인해야 한다. 확인 window를 막 지난 late duplicate도 만료 직후 새 실행이 아니라 저장된 성공 결과로 떨어져야 한다. InnoDB의 미커밋 unique claim 중복 insert가 블록되는 경우를 고려해 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 fixture로 확인한다. 확인 화면 이후 실행 전 다른 탭에서 잔액이 줄면 row lock 안에서 확인 시점 plan 수량을 재검증하고, 구매력/통화 min-unit/policy_version이 바뀌면 snapshot drift 사유로 별도 기록하며, 두 경우 모두 재계획 없이 거부하고 재확인을 요구해야 한다. 다중 자산 row lock은 `deduction_order`와 `asset_module` tiebreak 순서로 잡는지 확인한다. `purchase_power`는 `asset_units`, `settlement_units`, `settlement_currency` 구조로 snapshot에 저장되고, `asset_units`/`settlement_units` 양의 정수 여부와 `settlement_currency`의 min-unit registry 존재 여부는 설정 저장 또는 관리자 config 로드 시점에 setup 오류로 드러나야 한다. 가격 통화와 모든 참여 자산의 settlement 통화가 일치하지 않으면 관리자 설정 오류 또는 실행 거부로 드러나야 한다. 1P = 10 KRW, 가격 1,005 KRW 같은 케이스는 정확 충당 불가로 실패하고 ceil overpay가 없어야 하며, 기준금액 0은 차감 없이 `settlement_amount=0` 로그와 접근권만 남겨야 한다. 통화 min-unit 또는 rounding/carry `policy_version` 변경 직후 기존 확인 화면의 in-flight 요청은 fail-closed 재확인으로 떨어질 수 있음을 운영 워크플로에서 확인한다. 복합 차감에 참여하는 `member-assets.php` 거래 helper는 같은 PDO transaction에 동참해야 하고, 내부 commit/별도 connection을 쓰는 자산은 후보에서 제외되어야 한다. 문서 정적 체크는 계약 조항 삭제 방지용이므로 transaction 동참, carry, overpay, lock 순서는 구현 테스트 fixture와 필요한 HTTP smoke로 행위를 검증한다.

자산 환전 모듈을 활성화한 환경에서는 `/admin/asset-exchange`에서 활성 자산 모듈 간 정책을 저장하고, 같은 자산 조합 중복과 필수값/환전 비율/최소·최대 금액/수수료 설정이 서버에서 검증되는지 확인한다. 환전 비율은 `100:1` 같은 `출금 기준량:입금 환산량` 형식으로 입력하며 잘못된 형식이 거부되어야 한다. 수수료 적용이 `사용 안 함`이면 수수료 설정 입력이 숨겨지고 저장값도 비워져야 한다. 수수료를 적용할 때는 정률 또는 정액 중 하나만 설정할 수 있어야 하며, 정률 수수료는 기준값 100의 퍼센트 숫자 하나로 계산되어야 한다. 수수료와 정렬순서 숫자 필드는 빈 값과 잘못된 숫자 형식이 구분되어야 하고 저장 실패 후 입력값이 유지되어야 한다. 활성 양방향 무수수료 정책은 반복 환전으로 가치가 증가하는 반올림/비율 조합이 서버 저장에서 거부되는지 확인한다. `/account/asset-exchange`에서 예상 출금액, 입금액, 수수료, 최종 증가액을 확인한 뒤 확정하면 출금 자산 원장 `exchange_out`, 입금 자산 원장 `exchange_in`, 수수료가 있는 경우 `exchange_fee`가 같은 환전 묶음 ID로 남고, 성공/실패 후 새로고침으로 중복 실행되지 않아야 한다. 운영 helper `sr_asset_exchange_correct_completed_group()`로 완료 환전 묶음을 정정하면 반대 원장 거래와 정정 로그가 남는지 확인한다. 확정 토큰은 예상 금액의 정책/금액/quote에 묶여야 하며 만료되거나 다른 금액으로 바뀐 제출은 거부되어야 한다. 짧은 시간 반복 실행은 계정 단위 제한으로 막히는지 확인한다. `sr_asset_exchange_logs.deposit_amount`에는 수수료 차감 후 최종 증가액이 저장되어야 하며, 성공 시 비율/반올림/수수료 스냅샷이 저장되고 잔액 부족 같은 실행 실패 시 실패 사유가 관리자/회원 로그 화면에 표시되는지 확인한다. 자산 모듈을 비활성화하면 신규 정책 후보에서 제외되고 기존 정책 목록에는 실행 불가 상태가 표시되며, 기존 정책 수정 화면에서 비활성 자산 선택값이 유지되고 중지 상태로 저장할 수 있는지 확인한다.
적립금/예치금 모듈을 활성화한 환경에서는 `/account/rewards`의 출금 신청과 `/account/deposits`의 환불 신청이 로그인/CSRF를 요구하고, 최소 금액/최대 금액/필수 계좌 정보/대기 신청액을 제외한 신청 가능액을 서버에서 검증하는지 확인한다. `/admin/rewards/settings`에서 출금 신청 사용을 끄거나 `/admin/deposits/settings`에서 환불 신청 사용을 끄면 회원 화면의 신청 폼이 숨겨지고 직접 POST도 거부되어야 한다. `/admin/rewards/settings`와 `/admin/deposits/settings`에서 신청 사용을 켠 뒤 신청 대상으로 전체 회원을 지정하면 활성 회원에게 신청 폼이 표시되고, 회원 그룹을 지정하면 해당 그룹 소속 회원에게만 신청 폼이 표시되며 직접 POST도 같은 기준으로 검증되어야 한다. 전체 회원 선택 뱃지를 삭제하면 대상 선택 컨트롤이 다시 활성화되어야 한다. 지정 대상을 모두 해제하면 어떤 회원도 출금 또는 환불 신청을 할 수 없어야 한다. 신청 전 회원 그룹 자동 평가가 실행되어 최신 그룹 소속 기준으로 판정되는지 확인한다. 신청 후 같은 화면의 신청 내역에 대기 상태가 표시되고, 대기 상태 신청만 회원이 취소할 수 있어야 한다. `/admin/rewards/withdrawal-requests`와 `/admin/deposits/refund-requests`는 view/edit 권한을 확인하고, 상태 필터 그룹 버튼이 전체/대기/완료/거부/취소 목록을 전환하며 검색 조건/검색어가 회원, 계좌, 메모, 신청/거래 번호를 필터링하는지 확인한다. 처리 메모 없이는 개별 완료와 일괄처리가 저장되지 않아야 하며, 개별 거부는 별도 모달의 거부 사유 없이는 저장되지 않아야 한다. 일괄처리는 현재 필터와 검색 조건에 맞는 대기 신청만 대상으로 하며 100건 초과 시 서버에서 거부되어야 한다. 완료 처리는 각 원장에 `withdraw` 음수 거래를 만들고 신청 상태를 완료로 바꾸며, 거부와 회원 취소는 원장 거래를 만들지 않아야 한다. `/admin/rewards/transactions`의 회수는 대상 양수 거래의 남은 금액까지만 가능해야 하며, 잔액 조정 모달에는 회수 유형이 보이지 않고 회수 거래에는 환불 버튼이 없어야 한다. 개인정보 사본 제공에는 신청 계좌 정보와 처리 이력이 포함되어야 한다.
콘텐츠 업데이트 후 기존 `/admin/content/settings` 권한 보유 운영자에게 `/admin/content/asset-policy-sets`의 같은 액션 권한이 승계되는지 확인. 콘텐츠 회원 그룹별 설정 목록에서 이름, Key, 상태, 수정일 헤더 정렬이 허용 목록 기반으로 동작하는지 확인
콘텐츠 유료 열람 환경에서 쿠폰·이용권 모듈이 활성화되어 있고 대상 콘텐츠 쿠폰이 있으면 확인 POST 이후 금액성 자산 차감보다 쿠폰 사용이 먼저 적용되는지 확인. 같은 콘텐츠 재열람은 `dedupe_key` 기준으로 중복 사용되지 않아야 한다. 만료된 active 쿠폰 지급 건은 계정/관리자 목록 조회나 사용 처리 시 `expired` 상태로 전이되어야 한다. `/admin/coupons`의 `쿠폰 추가` 모달에서 쿠폰 키는 영문 소문자로 시작하는 key 형식만 저장되고, 대상 번호 검색 스택 모달로 사용처별 대상을 선택할 수 있으며, 중복 키와 숫자가 아닌 사용 가능 횟수는 서버 검증에서 거부되어야 한다. `/admin/coupons`의 상태/사용처/검색어 필터, 헤더 정렬, 개별 `지급하기` 모달의 회원 검색, 전체, 그룹 지급이 동작하고, `/admin/coupons/issues`에서 상태/사용처/회원/쿠폰 검색어 필터, 헤더 정렬, 지급 취소가 동작해야 한다. `/admin/coupons/redemptions`에서 상태/환급 정책/사용처/회원/쿠폰 검색어 필터, 헤더 정렬, 환급 가능 쿠폰 사용 내역의 사유 필수 수동 환불이 동작해야 한다. DB 업데이트 전 상태에서는 사용 내역 목록이 500 없이 열리고 환불 실행은 업데이트 필요 오류로 막혀야 한다. 쿠폰 사용 환불 후 해당 `dedupe_key`로 부여된 콘텐츠/커뮤니티 접근권이 회수되고, 같은 세션의 커뮤니티 첨부 직접 접근도 회수된 접근권을 우회하지 않아야 한다. 알림 모듈이 활성화되어 있으면 환불 알림이 생성되어야 한다
/community 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `SR_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/board?key=free 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `SR_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/group?key={group_key} 응답이 500 없이 열리고 사용 상태인 게시판 그룹의 설명과 접근 가능한 게시판 목록이 표시되는지 확인한다. 커뮤니티 주 메뉴 슬롯을 사용 안 함으로 설정하면 사용 상태인 게시판 그룹이 주 메뉴 fallback으로 표시되고, 그룹이 하나도 없을 때만 게시판 fallback이 표시되어야 한다.
/community/board?key=free&category={category_key} 응답이 500 없이 열리고 게시판 카테고리 필터가 적용되는지 확인. `category=all`, 없는 key, 비활성 카테고리는 기본 목록으로 조용히 떨어지지 않고 안내와 `noindex, follow`가 적용되는지 확인한다. 게시글 작성/수정에서 카테고리 필수 게시판은 서버 검증으로 빈 카테고리를 거부해야 하며, 비활성 카테고리는 기존 글 표시에서는 텍스트로만 보여야 한다.
/community 공개 홈, 게시판 목록, 카테고리 목록, 게시글 상세의 canonical과 robots 메타가 커뮤니티 공개 정책과 맞는지 확인한다. 게시판 SEO/OG 설정은 `/admin/community/boards`에서 저장한 `seo_title`, `seo_description`, `og_title`, `og_description`, `og_image_url` 값을 사용하고, 검색 결과와 잘못된 카테고리는 `noindex, follow`여야 한다. 게시글 SEO/OG 값은 작성/수정 폼 값이 우선하고, 유료 열람 확인 전 화면은 본문 요약을 description/OG에 노출하지 않아야 한다. 게시글 작성 시 이미지 첨부가 있으면 게시글 OG 이미지 후보가 되고, 작성자 또는 `/admin/community/posts` edit/delete 권한자는 CSRF가 있는 POST로 지정 OG 이미지를 제거할 수 있어야 한다. 커뮤니티 sitemap에는 공개 접근 가능한 게시판과 게시글만 포함되어야 한다.
/community/series는 로그인 회원만 접근할 수 있고, 회원이 만든 시리즈는 같은 게시판 글 작성/수정 화면에서 선택할 수 있어야 한다. 회원 시리즈 설명과 관리자 운영 메모가 서버 제한 길이를 넘으면 빈 값으로 저장하지 않고 오류를 표시해야 한다. 글 작성/수정 중 새 시리즈 제목을 제출하면 시리즈와 현재 글 항목이 함께 생성되고, 시리즈 정렬 순서는 서버에서 0 이상 1000000 이하 숫자로 검증되는지 확인한다. 공개 게시글 본문 다음에 active 시리즈 내비게이션이 표시되어야 한다. member 시리즈는 비로그인에게 숨겨지고 private 시리즈는 소유자에게만 표시되어야 한다. 시리즈 내비게이션에서 시리즈 스크랩 추가/해제를 수행하면 `/community/scraps`의 시리즈 스크랩 섹션에 표시되고, 게시글 스크랩과 자동으로 서로 생성되지 않아야 한다. 시리즈를 hidden, archived, deleted로 바꾸거나 게시판 읽기 권한을 제거하면 내 스크랩 목록에서 열람 불가 시리즈로 표시하되 해제는 가능해야 한다. 개인정보 사본 제공에는 게시글 스크랩과 별도로 시리즈 스크랩의 `series_id`, 생성일이 포함되어야 한다. `/admin/community/series`에서 상태/공개 범위/검색어 필터와 제목, 게시판, 소유자, 상태, 공개 범위, 글 수, 수정일 헤더 정렬이 허용 목록 기반으로 동작하고, 상태와 공개 범위가 한국어 라벨로 표시되는지 확인한다. 상태를 archived/deleted로 바꾸면 연결 항목이 공개 출력에서 제거되는지 확인. 코드 배포 후 DB 업데이트 적용 전에는 커뮤니티 시리즈 관리, 공개 게시글, 개인정보 내보내기가 시리즈 테이블 누락으로 500을 내지 않아야 한다.
/community/message/write 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/write?key=free 비로그인 접근이 로그인 흐름으로 막히는지 확인
커뮤니티 자산 기능을 활성화한 환경에서는 글/댓글 적립, 글/댓글 작성 차감, 게시글 유료 열람, 첨부 다운로드 차감이 비로그인/잔액 부족/중복 처리 정책에 맞게 동작하는지 확인. 게시글 유료 열람과 첨부 다운로드의 최초 1회와 반복 과금은 GET 접근만으로 차감되지 않고 확인 POST 후 처리되어야 하며, 이미 접근권이 있는 once 대상은 재확인 없이 열려야 한다. 회원 그룹별 적용을 저장하고 전역/게시판 그룹/게시판에서 여러 적용을 선택한 경우 상속 기준에 따라 최종 금액과 `group_policy_snapshot_json`이 로그에 남고, 최종 금액 0이면 원장 거래 없이 유료 열람/첨부 다운로드 접근권 또는 처리 로그가 `completed` 상태로 남는지 확인. 회원 그룹별 적용 선택지는 선택한 자산에 맞는 정책만 보여야 하며, 선택 자산을 바꾸면 맞지 않는 적용 뱃지가 제거되어야 한다. 같은 회원 그룹에 최소 레벨별 정책을 저장하면 레벨 충족 여부에 따라 다른 정책이 적용되고, 같은 우선순위에서는 충족한 정책 중 최소 레벨이 높은 행이 먼저 적용되며, 스냅샷에 매칭 최소 레벨과 현재 레벨이 남는지 확인. 유료 첨부는 S3 서명 URL 생성 또는 로컬 파일 경로 확인이 실패하면 자산 차감 없이 오류로 끝나야 하며, 전달 준비가 끝난 뒤에만 열람/다운로드 차감과 접근권 처리를 진행해야 한다. 최초 설치 후 커뮤니티 환경설정, 새 게시판 그룹, 새 게시판의 자산 설정은 사용하지 않음이며 포인트 등 특정 자산이 미리 선택되어 있지 않아야 한다. 복합 자산 차감 설정은 선택 자산별 금액이 각각 차감되는지 확인. 첨부 URL 직접 접근도 게시글 유료 열람 정책을 우회하지 않는지 함께 확인
커뮤니티 업데이트 후 기존 `/admin/community/settings` 권한 보유 운영자에게 `/admin/community/asset-policy-sets`의 같은 액션 권한이 승계되는지 확인. 커뮤니티 회원 그룹별 설정 목록에서 이름, Key, 상태, 수정일 헤더 정렬이 허용 목록 기반으로 동작하는지 확인
`/admin/community/boards` 게시판 편집 화면에서 회원 검색 모달로 회원을 선택해 게시판 관리권한을 부여/회수할 수 있고, 서버가 `view_manage`, `delete_post`, `remove_post_og_image` 외 권한 key와 빈 권한 제출을 거부하는지 확인한다. `delete_post` 권한 보유자는 해당 게시판의 게시글만 삭제할 수 있고 다른 게시판 게시글은 삭제할 수 없어야 하며, 게시판 관리권한만으로 게시글 본문 수정 화면/POST가 허용되지 않아야 한다. `remove_post_og_image` 권한 보유자는 해당 게시판 게시글의 지정 OG 이미지를 제거할 수 있고, 회수 후 즉시 삭제와 OG 이미지 제거가 거부되어야 한다. 권한 부여/회수, 게시판 관리권한으로 수행한 게시글 삭제, 게시판 관리권한 OG 이미지 제거는 감사 로그에 남아야 한다.
로고 매니저에서 용도별 상시 로고를 등록하면 공개 헤더, 모바일 헤더, 관리자 사이드바, 파비콘 용도에 반영되는지 확인. 같은 용도에 상시 로고와 현재 기간 로고가 함께 있으면 기간 로고가 우선이고, 현재 기간 로고가 여러 개이면 전체 기간이 더 짧은 로고가 우선인지 확인. 기간이 끝난 뒤에는 상시 로고로 되돌아가는지 확인. 활성 모듈이 `logo-positions.php`를 제공하는 경우 해당 모듈 후보가 로고 배치 추가 화면의 로고 용도 선택지에 표시되는지 확인. 기존 로고 배치를 수정할 때 파일을 선택하지 않으면 기존 이미지가 유지되고, 새 파일을 선택하면 파일 참조가 교체되며 감사 로그에 `logo_manager.logo.updated`와 이미지 교체 여부가 남는지 확인. 관리 버튼은 파비콘 용도에서 아이콘 세트가 맨 앞에 표시되고, 삭제 버튼은 로고 배치와 생성된 아이콘 세트 행 및 저장소 파일 정리를 실행하며 `logo_manager.logo.deleted` 감사 로그를 남기는지 확인한다. `public.favicon` 용도에서는 사용자 화면 심볼 스위치가 활성화되고 저장값이 심볼 helper에서 반환되는지, 공개 헤더 PC/모바일 로고가 모두 없을 때 기본 공개/콘텐츠/커뮤니티/퀴즈 레이아웃의 브랜드 영역에 앱아이콘과 사이트명이 함께 표시되는지, 다른 용도에서는 스위치가 꺼지고 조작된 POST 값도 저장되지 않는지 확인. 심볼 스위치는 파비콘 head link 조건과 별도이므로 심볼을 끈 활성 파비콘도 head link로 출력되고, 심볼을 켠 중지 파비콘은 head link로 출력되지 않아야 한다. 활성 파비콘 1개를 중지하면 관리자/공개/콘텐츠/커뮤니티/퀴즈 head에서 해당 URL의 `icon`/`apple-touch-icon` 링크가 제거되고, 다른 활성 후보가 없으면 빈 data 아이콘 링크가 출력되어 이전 favicon이 유지되지 않아야 하며, 같은 용도의 다른 활성 후보가 있으면 정렬 기준대로 그 후보가 적용될 수 있다. 로고 배치 목록은 `현재 적용`과 현재 시각에 적용 가능한 `적용 후보`를 구분해 보여야 하며, 기간이 지났거나 아직 시작 전인 사용 상태 로고에는 적용 후보 배지를 붙이지 않아야 한다. 탭 아이콘은 브라우저 캐시나 루트 `/favicon.ico` fallback으로 늦게 바뀔 수 있으므로 smoke 판정은 HTML head 출력 기준으로 한다. 로컬 PNG/JPEG/WebP 파비콘 원본에서는 아이콘 세트 모달의 생성 크기 스위치가 줄바꿈 목록으로 표시되고 전체 선택 스위치가 개별 크기 스위치를 모두 활성화/비활성화하는지 확인한다. 16/32/48/180/192/512 PNG variant를 생성하며, 생성 후 즉시 사용을 선택하면 공개 head에 사이즈별 `icon`/`apple-touch-icon` 링크가 출력되는지 확인. SVG 또는 S3 원본은 생성 불가 안내가 표시되고 기존 단일 favicon fallback이 유지되어야 한다
커뮤니티 게시글 또는 게시판 대상 쿠폰이 있으면 게시글 유료 열람과 첨부 직접 접근에서 금액성 자산 차감보다 쿠폰 사용이 먼저 적용되는지 확인. `once` 정책에서는 같은 세션/대상 중복 차감과 중복 쿠폰 사용이 없어야 한다
커뮤니티 환경설정 저장은 레벨 사용, 자동 재계산, 최대 레벨, 레벨 점수, 쪽지 작성 정책/회원 그룹, 커뮤니티 홈 레이아웃, 게시글 에디터, 본문 URL 자동 링크, 복합 자산 차감 선택과 금액을 함께 바꿔 `sr_module_settings` 값이 갱신되는지 확인한다. 레벨 점수 입력은 자동 재계산을 사용할 때만 보이는지 확인한다. 최대 레벨을 늘릴 때는 1차 안내 모달과 2차 확인 문구 입력을 거친 뒤 부족한 레벨 행이 기본 최소 점수로 자동 추가되는지 확인한다. `/admin/community/levels`는 레벨 미사용 상태에서 환경설정 링크가 있는 안내를 표시해야 하며, 재계산 모달은 부하 안내 확인 단계와 확인 문구 입력 단계를 거친 뒤 배치 재계산 진행상태가 표시되는지 확인한다. 복합 자산 차감에서 포인트/적립금/예치금을 모두 해제하고 저장하면 다시 체크되지 않아야 한다. 저장 실패가 발생하면 화면 검증 메시지 또는 `storage/logs/error.log`에 원인이 남아야 한다
커뮤니티 자산 관리자 설정을 바꾼 환경에서는 커뮤니티 전역, 게시판 그룹, 게시판 수정 화면의 `자산 변경 이력` 링크에서 대상별 변경 로그가 보이는지 확인. 게시판 그룹에서 새 게시판을 추가하면 게시판 그룹의 기본값이 입력폼에 복사되고, 게시판 상태와 스킨은 그룹 설정값으로 바뀌지 않으며 `그룹` 적용 범위는 자동 선택되지 않는지 확인. 게시판 수정 화면에서 `그룹`/`전체` 적용을 선택하면 현재 편집값이 대상 게시판에 한 번 복사되고, 전역 설정이나 게시판 그룹 설정 변경 후에는 기존 게시판의 자산 설정과 유효 동작이 바뀌지 않아야 한다. 게시판을 다른 그룹으로 옮기더라도 저장된 게시판 자산 설정은 현재 게시판 값으로 유지되어야 한다
CKEditor 플러그인을 활성화한 환경에서는 콘텐츠 본문, 커뮤니티 게시글, 팝업레이어 본문, 관리자 본문 textarea가 설정에 따라 에디터로 강화되고, 초기화 성공 시에만 `body_format=html`로 저장되는지 확인. 직접 호스팅 모드는 `modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js`와 `ckeditor5.css`를 로드해야 한다. CKEditor 설정 화면의 기본 툴바 구성은 명시 preset이 없는 CKEditor textarea의 fallback으로 적용되고, 콘텐츠 환경설정의 툴바 구성은 콘텐츠 본문 입력 화면에, 커뮤니티 환경설정의 툴바 구성은 커뮤니티 게시글 작성/수정 화면에 적용되는지 확인한다. CKEditor asset 로딩을 실패시킨 경우에는 일반 textarea 제출이 유지되고 서버가 `plain` 저장으로 fallback해야 한다. 악성 HTML은 저장/출력 과정에서 허용 태그와 속성만 남아야 한다. 콘텐츠/커뮤니티/팝업레이어 본문 이미지 업로드는 권한, CSRF, 업로드 token을 요구하고, 저장 전 temporary 이미지는 업로드 권한이 있는 현재 사용자에게 보이며, 저장 후 정화된 HTML에 남은 프록시 URL만 소유 모듈의 로컬 경로로 이동되어야 한다. 유료/비공개 콘텐츠와 권한 제한 게시글의 본문 이미지 프록시는 소유 모듈 접근 정책을 우회하지 않아야 하며, 본문에서 제거되거나 레코드가 삭제된 이미지는 소유 모듈 저장 경로 기준으로 정리되어야 한다. 관리자 설정형 rich textarea에는 소유 모듈이 subject key와 삭제 정책을 명시한 경우에만 upload endpoint가 붙는지 확인한다.
알림 모듈이 활성화된 환경에서는 포인트/적립금/예치금 거래와 쿠폰 지급/사용/상태 변경 후 회원 대상 사이트 알림이 생성되는지 확인. 포인트 환경설정에서 기본 유효기간을 1일 이상으로 저장한 뒤 `grant` 지급 거래를 만들면 거래 목록과 회원 화면에 유효기간이 표시되고, 기본 유효기간 입력 옆에 `일` 단위가 표시되는지 확인한다. 사용/차감 거래가 가장 먼저 만료되는 지급분의 만료 가능 잔여량을 줄이고 `sr_point_expiration_consumptions`에 소비 매핑을 남기는지도 확인한다. 포인트 환불 모달의 환불 유효기간 기본값은 `환불 참조 원거래의 유효기간`이어야 하며, 환불 건마다 `환불 시점부터 기본 유효기간 계산`으로 바꿀 수 있어야 한다. 사용/차감 거래를 환불할 때 기본값은 소비 매핑의 원 지급 유효기간을 따라야 하고, 여러 유효기간 지급분이 복원되면 환불 거래가 유효기간별로 나뉘어야 하며, 이미 환불한 수량과 합쳐 원거래 수량을 넘으면 서버에서 거부되어야 한다. 포인트/적립금/예치금 조정 모달에는 환불 거래 유형이 일반 선택지로 보이지 않아야 하고, 참조 없는 환불 POST, 양수 원거래 환불 POST, 원거래 잔여 환불 가능액을 넘는 환불 POST는 서버에서 거부되어야 한다. 콘텐츠 파일 다운로드 수동 환불 모달도 포인트 환불 유효기간 기준을 환불 건마다 선택할 수 있어야 한다. 기한이 지난 지급분은 `/admin/points/settings`의 `수동 만료 실행` 버튼, `php .tools/bin/expire-points.php` 실행, 또는 다음 포인트 거래 전에 `expire` 거래로 차감되어야 하며, 기존 업데이트 전 거래처럼 `expires_at`이 없는 거래는 자동 만료되지 않아야 한다. 적립금 관리자 조정에서 지급 원거래에는 환불 버튼이 보이지 않고 회수 버튼만 제공되어야 한다. 예치금 지급/예치 원거래에는 환불 버튼이 보이지 않아야 하며, 회원에게 예치금을 내보내는 처리는 출금 또는 예치금 환불 신청 완료 흐름으로 처리되어야 한다. `회수` 유형은 음수 금액만 허용하고, 회수 대상 원거래를 참조해야 하며, 같은 대상의 누적 회수액이 원거래 금액을 넘으면 서버에서 거부되어야 한다. 회수 성공 후 회원 알림 제목이 적립금 회수로 생성되어야 한다. 알림 모듈을 비활성화한 환경에서는 같은 자산 처리가 알림 실패 없이 성공해야 한다. 포인트/적립금/예치금 관리자 수동 조정은 1,000,000 초과 시 처리자와 다른 편집 권한 보유 승인자 식별자와 승인 사유가 없으면 거부되고, 10,000,000 초과 1회 조정 또는 관리자별 일일 10,000,000 초과 조정은 서버에서 거부되는지 확인
/community/edit?id=1 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/edit 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/edit 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/report 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/content/comment 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
알림 모듈이 활성화된 환경에서 커뮤니티/콘텐츠/퀴즈/설문 댓글 textarea에 `@`를 입력하면 로그인 회원용 `/member/mention-search` 후보가 공개 이름과 hash prefix만 반환하고, 이메일/내부 계정 ID/가입일을 노출하지 않는지 확인한다. 후보 선택으로 삽입된 `@공개이름#prefix`는 현재 공개 이름과 public account hash prefix가 함께 단일 활성 회원에 일치할 때만 `module_key=community/content/quiz/survey, event_key=comment.mention` 템플릿 기반 사이트 알림을 생성해야 한다. 동명이인에게 `@공개이름`만 입력한 모호한 멘션은 단일 대상 알림을 만들지 않아야 하며, 자기 자신과 글/콘텐츠 작성자는 멘션 대상에서 제외되어야 한다. 비밀 댓글은 멘션 알림을 만들지 않아야 한다. 알림 모듈이 비활성화되었거나 템플릿이 누락된 환경에서는 댓글 저장이 실패하지 않아야 한다. `/admin/audit-logs`에서는 댓글 작성 감사 로그에 작성자 알림 생성 여부가 남고, 댓글 작성/수정 감사 로그에는 멘션 후보 수, 실제 멘션 알림 생성 수, 멘션 대상 공개 해시가 남는지 확인한다. `php .tools/bin/check-mention-ux.php`, `php .tools/bin/check-quiz-consistency.php`, `php .tools/bin/check-survey-consistency.php`로 prefix 파서, 후보 API 연결, 비밀 댓글 UI 제어 정합성을 확인한다.
/community/scraps 비로그인 접근이 로그인 흐름으로 막히는지 확인
POST /community/scrap 비로그인 접근이 로그인 흐름으로 막히는지 확인. `target_type=series` 시리즈 스크랩 POST도 같은 로그인/CSRF 흐름을 따라야 한다.
/community/messages 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/message?id=1 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/message/write 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/message/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/admin/community/boards 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/community/reports 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/community/reports에서 신고 상태 저장 시 대상 조치 없음/게시글 숨김/댓글 숨김/피신고 회원 정지 등 대상 유형별 허용 조치만 서버에서 처리되는지 확인
/admin/community/posts 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/sitemap.xml 응답이 200이면 sitemap XML 루트가 있고 404여도 PHP 오류가 노출되지 않는지 확인
/assets/saanraan.css 정적 파일 응답과 `--sr-bg` 홈 스킨 토큰 확인
/assets/public-layout.css 정적 파일 응답과 공통 공개 layout header/main/footer 확인
/assets/public-layout.js 정적 파일 응답과 공통 공개 layout 스크롤 header 동작 기준 확인
/assets/public-ui.css 정적 파일 응답과 공개 UI kit scope 및 홈 화면 primitive 확인
/modules/community/assets/community-public.css 정적 파일 응답과 커뮤니티 화면 wrapper 확인
/modules/community/assets/community-layout.js 정적 파일 응답과 커뮤니티 layout 스크롤 header 동작 기준 확인
/database/core/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/member/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/community/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/community/module.php 직접 접근에서 커뮤니티 모듈 코드가 노출되지 않는지 확인
/core/helpers.php 직접 접근에서 PHP 코드가 노출되지 않는지 확인
/config/.gitignore 직접 접근에서 config 디렉터리 내용이 노출되지 않는지 확인
/storage/.gitignore 직접 접근에서 storage 디렉터리 내용이 노출되지 않는지 확인
/docs/deployment-protection.md 직접 접근에서 문서 내용이 노출되지 않는지 확인
/examples/sample_module/module.php 직접 접근에서 예제 모듈 코드가 노출되지 않는지 확인
/AGENTS.md 직접 접근에서 프로젝트 지침이 노출되지 않는지 확인
/README.md 직접 접근에서 루트 문서가 노출되지 않는지 확인
/.tools/bin/check.php 직접 접근에서 도구 코드가 노출되지 않는지 확인
/.git/HEAD 직접 접근에서 저장소 메타데이터가 노출되지 않는지 확인
```

설치 전 상태에서는 `/login`, `/admin`, 내부 경로 요청도 설치 화면으로 이어질 수 있다. 이 경우 200 또는 redirect는 허용한다. 중요한 기준은 PHP fatal error가 노출되지 않고, 보호되어야 할 내부 파일의 실제 내용이 직접 노출되지 않는 것이다.

## 인증 커뮤니티 스모크 점검

커뮤니티 모듈이 설치되어 있고 테스트 계정이 준비된 환경에서는 인증 흐름까지 확인한다. 이 점검은 게시글, 댓글, 스크랩, 쪽지, 신고, 관리자 처리 데이터를 실제로 만든다. 운영 DB가 아닌 로컬 또는 스테이징 DB에서 실행한다.

최소 실행은 작성자 계정만 필요하다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_IDENTIFIER=writer@example.com \
SR_SMOKE_PASSWORD='password' \
php .tools/bin/smoke-community-auth.php
```

## 읽기 참조 계약 스모크

마일스톤 13 읽기 참조 계약을 검증할 때는 다음 흐름을 확인한다.

- 발급/사용 이력이 있는 쿠폰 정의를 비활성화할 때 서버가 최신 `coupon-references.php` 결과로 차단한다.
- 퀴즈 쿠폰 보상 정책이 쿠폰 정의를 참조하고 있으면 서버가 최신 `coupon-references.php` 결과로 비활성화 영향을 표시한다.
- 설문 쿠폰 보상 정책이 쿠폰 정의를 참조하고 있으면 서버가 최신 `coupon-references.php` 결과로 비활성화 영향을 표시한다.
- 콘텐츠나 커뮤니티 설정에서 직접 선택한 배너/팝업레이어가 있으면 해당 배너/팝업레이어 삭제 POST가 차단된다.
- 적립금/예치금/콘텐츠/커뮤니티/회원 자동 규칙에서 쓰는 enabled 회원 그룹은 비활성 또는 보관 상태로 바꾸는 POST가 차단된다.
- SEO 설정이나 로고 alt text에 기존 사이트명이 직접 들어 있으면 사이트명 변경 POST가 차단된다.
- `php .tools/bin/check-read-reference-contracts.php`가 통과하고, `php .tools/bin/check.php` 통합 점검에도 포함된다.
- 보상/접근권 중복 방지 기준은 `php .tools/bin/check-reward-abuse-standards.php`가 통과하고, `php .tools/bin/check.php` 통합 점검에도 포함된다.

## 퀴즈 보상 전용 E2E

퀴즈 마일스톤을 검증할 때는 로컬 또는 스테이징에서 관리자 테스트 계정을 사용해 다음 명령을 실행한다. 이 검사는 퀴즈를 생성하고 제출 기록과 보상 지급을 만든 뒤 가능한 경우 생성 퀴즈를 소프트삭제하므로 운영 DB에서 실행하지 않는다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_ADMIN_IDENTIFIER=admin \
SR_SMOKE_ADMIN_PASSWORD='12341234' \
php .tools/bin/smoke-quiz-e2e.php
```

활성 자산 보상 후보를 명시해야 하면 `SR_SMOKE_QUIZ_REWARD_MODULE=point`처럼 지정한다. 스크립트는 관리자 퀴즈 생성, 복수/단일 선택 제출, 통과 결과, 보상 지급, 회원당 1회 재응시 차단을 확인한다.

전체 커뮤니티 흐름은 선택 계정을 함께 지정해 확인한다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_IDENTIFIER=writer@example.com \
SR_SMOKE_PASSWORD='password' \
SR_SMOKE_RECIPIENT_IDENTIFIER=recipient@example.com \
SR_SMOKE_RECIPIENT_PASSWORD='password' \
SR_SMOKE_REPORTER_IDENTIFIER=reporter@example.com \
SR_SMOKE_REPORTER_PASSWORD='password' \
SR_SMOKE_ADMIN_IDENTIFIER=admin@example.com \
SR_SMOKE_ADMIN_PASSWORD='password' \
php .tools/bin/smoke-community-auth.php
```

확인 항목:

```text
작성자 로그인 후 /community/messages 접근
자유 게시판 게시글 작성과 상세 화면 제목 확인
작성자 게시글 수정과 상세 화면 수정 제목/본문 확인
댓글 작성과 상세 화면 댓글 본문 확인
게시글 스크랩 추가와 스크랩 목록 노출, 해제 후 목록 미노출 확인. 시리즈 스크랩은 게시글 스크랩과 별도 목록으로 표시되고 해제 후 목록에서 빠지는지 확인
수신자 계정 지정 시 쪽지 발송과 보낸 쪽지 본문 확인
수신자 비밀번호 지정 시 수신자 로그인 후 받은 쪽지 본문 확인
보낸 쪽지 삭제 후 보낸 쪽지함 미노출과 발신자 404 응답 확인
신고자 계정 지정 시 작성된 게시글 신고 확인
관리자 계정 지정 시 신고 처리, 댓글 숨김과 댓글 미노출, 게시글 숨김, 숨김 게시글 404 응답 확인
```

`SR_SMOKE_RECIPIENT_PASSWORD`는 `SR_SMOKE_RECIPIENT_IDENTIFIER`가 있을 때만 사용할 수 있다. 신고자와 관리자 계정은 identifier/password를 함께 지정해야 한다. 게시판 키를 바꿔야 하면 `SR_SMOKE_BOARD_KEY`를 사용하고, 기존 게시글 ID를 보조값으로 넘겨야 하면 `SR_SMOKE_POST_ID`를 사용한다.

## 수동 확인 시나리오

릴리스 전에는 다음 흐름을 브라우저에서 한 번 확인한다.

```text
1. 새 DB로 설치 화면 진입
2. 설치 화면의 4단계 흐름(환경 확인 → 기본 정보 → 관리자와 모듈 → 확인 및 설치)이 보이고, 이전/다음 이동과 최종 요약이 입력값을 반영하는지 확인
3. 필수 모듈 설치 완료
4. 최초 owner 계정으로 로그인
5. /admin 대시보드 진입
6. 대시보드 모듈별 섹션이 표시되고 드래그 앤 드롭으로 순서가 바뀌는지 확인
7. /admin/modules에서 설치 버전과 코드 버전 확인
8. /admin/updates에서 미적용 SQL 목록 또는 없음 확인
9. /admin/audit-logs에서 이벤트와 대상 유형을 한국어 선택 컨트롤로 고를 수 있고, 대상 식별값, 처리자 유형, IP, 결과, 날짜 필터가 상세검색 안에서 동작하며 초기화 버튼으로 표시된 필터가 비워지고 metadata 상세 모달이 민감값을 마스킹한 채 열리는지 확인
10. /account에서 계정 화면 진입
11. 로그아웃 후 /admin 접근 시 로그인 흐름 확인
```

선택 모듈이 포함된 배포본은 다음 항목을 추가로 확인한다.

```text
선택 모듈 체크 후 설치 완료
서비스 도메인 모듈 카드에서 초기화면으로 설정 체크를 선택한 경우 site.home_path가 저장되고 / 접속 시 해당 경로로 이동
/admin/settings 화면 섹션에서 기본 홈페이지 / 접속, 콘텐츠 메인/커뮤니티 홈/퀴즈 메인 초기화면 선택과 fallback 확인
선택 모듈 관리자 메뉴 노출
선택 모듈의 GET 관리자 path가 500 없이 열림
```

모듈 설치/업데이트 흐름을 완료 판정할 때는 다음 상태도 함께 확인한다.

```text
/admin/modules에서 미설치 모듈이 미설치 또는 설치 차단 상태로 구분됨
/admin/modules에서 failed 또는 installing 모듈이 재설치 필요 상태로 구분됨
/admin/modules에서 코드 버전이 설치 버전보다 낮은 모듈이 파일 재배치 필요 상태로 구분됨
/admin/modules에서 pending SQL이 있는 모듈이 /admin/updates 이동 대상으로 구분됨
/admin/updates에서 pending SQL 적용 전 백업 확인을 요구함
/admin/updates에서 SQL 없는 파일 전용 업데이트가 설치 버전 반영 대상으로 구분됨
모듈 수명주기 판정이 core helper 기준으로 유지되고, /admin/modules와 /admin/updates가 같은 pending SQL/버전 차이를 표시함
```

## QA 더미 데이터 준비

관리자 계정 외 사이트 데이터를 비우고 다시 채우는 기준은 [사이트 초기화와 더미 데이터 기준](site-reset-and-fixtures.md)을 따른다. 더미 데이터는 DB 직접 insert 대신 실제 등록/저장 HTTP 경로를 사용하고, 기본 검수 세트는 주요 도메인별 10-15건을 만든다.

더미 데이터 준비 후에는 다음을 기록한다.

```text
사용한 base URL
보존한 관리자 계정
실행한 등록 경로
도메인별 생성 전/후 카운트
실패한 경로와 실패 사유
php .tools/bin/check.php 결과
가능한 HTTP smoke 결과
```

## 실패 시 확인 순서

HTTP 스모크 점검이 실패하면 다음 순서로 확인한다.

```text
1. 실패한 URL과 HTTP status
2. storage/logs/error.log
3. 최근 변경한 action 또는 helper의 PHP 문법
4. modules/{module_key}/paths.php의 method/path 매핑
5. 웹서버의 내부 디렉터리 접근 차단 규칙
```

보호 경로에서 내부 파일 내용이 보이면 코드 수정 전에 서버 배포 설정을 먼저 확인한다. 운영 환경에서 `config/`, `database/`, `modules/`, `storage/` 내부 파일이 직접 열리는 상태로 설치를 진행하지 않는다.
