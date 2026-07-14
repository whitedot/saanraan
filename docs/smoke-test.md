# 스모크 테스트 기준

이 문서는 설치 직후, 배포 전, 운영 수정 후 최소한으로 확인할 HTTP 검증 범위를 정리한다. 목표는 모든 기능을 자동 테스트하는 것이 아니라, 핵심 요청 흐름이 깨졌거나 내부 파일이 노출되는 문제를 빠르게 발견하는 것이다.

신규 설치 스모크를 수행할 때 `site_menu`와 콘텐츠/커뮤니티/퀴즈·테스트/설문·여론조사 중 일부를 선택했다면 설치 직후 `header` 메뉴에 홈과 선택한 서비스 모듈의 `service_domain.main_page` 링크만 생성되고, 콘텐츠, 커뮤니티, 퀴즈·테스트, 설문·여론조사 순서와 라벨로 표시되며, 로그인/회원가입이 자동 삽입되지 않는지 DB와 공개 헤더에서 확인한다. `site_menu`를 선택하지 않은 설치에서는 이 seed가 실행되지 않아야 한다.

SEO 설정 화면 스모크에서는 사이트맵 확인 링크와 URL 복사 버튼이 설정 저장 submit을 발생시키지 않는지, 사이트맵 확인 링크와 robots 파일 확인 버튼이 새 탭으로 열리는지, 복사 성공/실패 피드백이 버튼 텍스트로 돌아오는지, robots 파일 확인 버튼이 카드 헤더 오른쪽에 표시되고 미리보기 텍스트가 좁은 화면에서도 넘치지 않는지 확인한다.

자동등록방지 모듈을 활성화한 로컬 또는 staging 환경에서는 `/admin/antispam/settings`에서 산술 문제와 활성 `antispam-providers.php` 계약이 제공하는 Turnstile/hCaptcha/reCAPTCHA provider 설정 저장을 각각 확인한다. provider secret key는 화면과 감사 로그에 원문이 노출되지 않아야 하며, 빈 secret 입력은 기존 값을 유지해야 한다. 회원가입, 비회원 커뮤니티 글, 비회원 커뮤니티 댓글처럼 각 모듈이 `antispam-targets.php` 계약으로 선언한 적용 대상은 설정된 적용 모드에 따라 같은 강도의 산술 challenge가 렌더링되고 서버 검증 실패 시 저장되지 않아야 한다. 기본 산술 challenge는 한 자리 더하기/빼기 고정 형식이 아니라 두 자리, 3항, 제한적 곱셈, 짧은 한국어 문장형이 섞여야 하며, 정답 원문은 세션에 저장하지 않는다. 외부 provider는 `antispam_captcha_providers` 플러그인을 활성화한 뒤 실제 staging key 또는 mock endpoint로 성공, 실패, timeout/fallback 정책, reCAPTCHA 최소 점수 미달, 최소 제출 시간, action/hostname 불일치와 검증 토글을 확인한다. `fallback_math` 정책은 provider 장애(`provider_unavailable`)에서만 예비 산술 문제로 이어지고, missing input, bad token, score_low, action/hostname mismatch 같은 검증 실패는 fallback으로 우회되지 않아야 한다. 운영 DB에서는 자동등록방지 설정을 바꾸거나 mutation smoke를 실행하지 않는다.

알림 모듈의 회원 대상 외부 push를 확인할 때는 로컬 또는 staging에서 `/account/notifications`의 Telegram 개인 chat endpoint와 Slack/Discord 개인 webhook endpoint 연결/해제가 현재 비밀번호 재확인과 CSRF를 요구하는지 먼저 확인한다. 연결 후 DB에는 `endpoint_ciphertext`와 fingerprint만 저장되고 delivery `recipient`에는 `endpoint:{id}` 참조만 남아야 한다. Slack/Discord webhook URL은 credential로 취급해 화면, 감사 로그, delivery 오류, 개인정보 export에 원문이 남지 않아야 한다. 회원 허용이 켜진 provider에 활성 endpoint가 있으면 회원 계정 알림의 기본 site/email 채널 구성과 별개로 연결된 개인 수신처 delivery가 생성되고, 웹 POST 요청에서는 알림 저장 commit 뒤 즉시 발송을 시도해야 한다. 즉시 발송 성공 row는 발송 작업 목록에 남기지 않고, 실패 또는 재시도 대상 row만 오류 메시지와 함께 남긴다. cURL을 우선 사용하고 cURL이 없으면 `allow_url_fopen` stream으로 HTTPS JSON POST를 시도한다. 둘 다 사용할 수 없는 환경에서는 delivery 오류가 `provider_unavailable`로 남아야 한다. 같은 provider/endpoint를 다른 계정에 중복 연결할 수 없고 계정별 provider 활성 endpoint 5개 상한이 적용되어야 한다. 연결/해제 성공 시 회원 인증 로그, 감사 로그, 사이트 보안 알림이 남아야 하며, endpoint 해제 후 row의 `endpoint_ciphertext`는 비워져야 한다. 이미 queued 상태인 delivery를 runner로 처리하면 provider 호출 없이 `canceled`가 되어야 하며, 개인정보 export에는 provider, recipient type, masked label, 상태와 시각만 포함되고 token/chat_id/webhook URL 원문이나 ciphertext가 포함되면 안 된다. 개인정보 cleanup을 실행하면 해당 계정의 push endpoint가 disabled tombstone으로 전환되고 ciphertext가 남지 않아야 한다.

## 기본 정적 점검

코드 변경 후 먼저 기본 점검을 실행한다.

```sh
php .tools/bin/check.php
```

자산 모듈이 설치된 로컬 또는 스테이징 환경에서 금액성 흐름을 검증할 때는 read-only reconciliation도 실행한다.

```sh
php .tools/bin/reconcile-assets.php
```

이 명령은 포인트, 적립금, 예치금의 balance row, 거래 합계, 마지막 거래 `balance_after`를 비교한다. 운영 DB에서 실행해도 데이터를 바꾸지는 않지만, 결과 해석과 후속 정정은 운영 절차에 따라 별도 승인 후 처리한다.

이 점검은 다음을 확인한다.

```text
git diff --check
SQL 파일 비어 있음 여부
모듈 기본 계약 파일 구성
모듈 상태 등급 문서 누락 여부
릴리스 검증 기록 템플릿 기준
운영 상태 점검 기준
외부 의존성 배치 기준
관리자 메뉴 path와 paths.php GET route 일치
전체 PHP 문법
rich text sanitizer payload 회귀 점검
문서 링크와 .tools/bin 명령 참조 존재 여부
보상/설문 정합성 회귀 점검
```

현재 통합 점검은 코드 상태뿐 아니라 진행 중인 정책 TODO도 함께 검사한다. 보상 중복 방지 기준은 `.tools/bin/check-reward-abuse-standards.php`, 설문 통계/개인정보/완료 화면 회귀 기준은 `.tools/bin/check-survey-consistency.php`, URL 임베드 계약 구조·모듈별 외부/내부 gate·Markdown wrapper 밖 임베드 경계는 `.tools/bin/check-url-embed-contracts.php`가 확인하며 모두 `.tools/bin/check.php`에 포함된다. 이후 실패가 발생하면 실패 항목이 현재 변경의 회귀인지, 새 정책 점검 추가로 드러난 기존 보완 항목인지 먼저 분리한다. `.tools/bin/check.php`의 PHP 문법 검사는 저장소 코드와 도구 파일을 대상으로 하며, 환경별 비밀 설정과 런타임 파일이 들어가는 `config/`, `storage/` 디렉터리는 제외한다.

공개 레이아웃/테마/스킨 설정을 바꾼 경우 사이트 설정의 공개 화면 테마는 `core/views/theme/{theme_key}/home.php`를 가진 내부 초기화면 테마만 저장할 수 있어야 하며, 공개 레이아웃 선택지는 레이아웃만 저장해야 한다. 콘텐츠/커뮤니티/퀴즈/설문 환경설정의 공개 테마 선택지는 각 모듈의 `theme/{theme_key}` 필수 view 세트가 있는 키만 저장할 수 있어야 한다. `basic` 테마는 배포판 기본 view를 `modules/{module}/theme/basic`에서 렌더링하고, `sample` 테마를 선택하면 콘텐츠 `/content`, `/content/group?key=...`, `/content/{slug}`, 커뮤니티 `/community`, `/community/group?key=...`, `/community/board?key=...`, `/community/post?id=...`, `/community/write?key=...`, `/community/search`, 퀴즈 `/quiz`, `/quiz/{quiz_key}`, 설문 `/survey`, `/survey/{survey_key}` 공개 화면의 body DOM에 `data-example-theme-view` marker가 나타나야 한다. 모듈 UI-KIT 미리보기 `/content/ui-kit`, `/community/ui-kit`, `/quiz/ui-kit`, `/survey/ui-kit`은 선택 theme의 `ui-kit.php`에서 렌더링되어 `data-theme-ui-kit-view` marker와 선택 theme의 `assets/common.css`, `assets/ui-kit-layout.css`를 가져야 한다. 각 모듈 관리자 화면은 공개 테마 선택으로 바뀌지 않아야 한다. 스킨과 테마는 별도 선택값이며, 내부 theme view가 없는 경우에만 기존 community/quiz/survey skin view가 fallback으로 사용되어야 한다. 현재 화면 target을 지원하지 않는 레이아웃은 공개 렌더링에서 `common.basic`으로 fallback해야 하며, 모듈 환경설정의 레이아웃 선택지는 해당 모듈 필수 target 전체를 지원하는 option만 저장할 수 있어야 한다.

퀴즈/설문 공개 스킨 설정을 바꾼 경우 `/admin/quiz/settings`, `/admin/surveys/settings`의 기본값과 개별 퀴즈/설문 수정 화면의 `skin_key`가 허용 목록 값으로만 저장되는지 확인한다. 개별 값이 비어 있으면 환경설정 기본값을 상속하고, 값이 있으면 상세/응시/응답/완료 화면에서 개별값이 우선해야 한다. `/quiz`, `/quiz/{quiz_key}`, `/survey`, `/survey/{survey_key}`는 선택 스킨의 `home`/`view` 본문을 공개 레이아웃 안에서 렌더링해야 하며, 결과/완료 화면의 보상 지급 안내가 유지되어야 한다. 잘못된 legacy `skin_key`나 누락된 스킨 view는 `basic`으로 fallback하고 운영 로그에 module, skin key, view, fallback file이 남는지 확인한다.

설문 모듈을 확인할 때는 기타 선택지를 고른 공개 응답이 기타 텍스트를 요구하고, 분석 CSV와 개인정보 사본의 `other_text`에 저장되는지 함께 본다.

본문 임베드 변경을 확인할 때는 콘텐츠/커뮤니티 본문에 YouTube, X, Instagram, 내부 콘텐츠·커뮤니티·퀴즈·설문 URL을 한 줄에 단독으로 붙여 넣고 저장한다. 공개 콘텐츠/게시글 화면에서는 저장 HTML에 전용 marker나 script/iframe을 남기지 않은 채 서버 렌더링 시점에 URL이 카드 또는 외부 임베드로 해석되어야 한다. 내부 URL 임베드는 호출처의 `module.css`가 아니라 대상 모듈의 전용 `assets/embed.css`가 로드되어야 하고, 카드 안에 제목 링크와 별도 전체 canonical URL 텍스트 링크가 보여야 한다. 외부 URL 임베드는 `/assets/url-embed.css`만 로드되어야 한다. 렌더링된 내부 임베드가 대상별 custom tag를 사용하고 기존 fragment cache의 옛 HTML을 재사용하지 않는지도 확인한다. 각 대상 모듈의 관리자 임베드 캐시 화면에서 해당 모듈의 fragment cache 파일 수, 용량, 미리보기 목록이 보이고, 확인 문구와 권한이 있는 경우 조건별 정리가 bounded batch로 실행되는지도 확인한다. 퀴즈·설문 완료 후 내부 URL 임베드의 `return_to` 링크로 원래 화면에 돌아갈 수 있어야 한다. 가능하면 브라우저 QA로 실제 붙여넣기와 공개 렌더링까지 확인하고, 불가능하면 로컬 수동 smoke 결과와 미실행 사유를 기록한다.

비밀글/비밀댓글 변경을 확인할 때는 커뮤니티 전역과 게시판 설정, 콘텐츠 환경설정, 퀴즈/설문 항목 설정의 허용 스위치를 각각 껐다 켠다. 허용이 꺼진 상태에서 `is_secret=1`을 직접 POST해도 새 게시글/댓글은 공개로 저장되어야 하며, 기존 비밀 댓글은 수정 요청 후에도 비밀 상태가 유지되어야 한다. 허용이 켜진 상태에서는 사용자 작성/수정 화면에 비밀 선택지가 표시되고, 커뮤니티 비밀 게시글 본문·첨부·퀴즈 연결·댓글·SEO/OG 설명·URL 임베드 결과는 작성자 또는 관리자/운영 권한자 외에는 노출되지 않아야 한다. 비밀 댓글 본문은 댓글 작성자, 대상 글/콘텐츠 작성자, 댓글 관리자 권한자만 볼 수 있고 멘션 알림을 만들지 않아야 한다.

커뮤니티 일반 게시글 작성/수정 화면에서는 SEO/OG 메타 필드가 보이지 않아야 한다. 직접 POST로 `seo_title`, `seo_description`, `og_title`, `og_description`을 보내도 신규 게시글에는 저장되지 않고, 기존 SEO/OG 값이 있는 게시글을 작성자가 수정해도 해당 값이 빈 값이나 조작된 POST 값으로 덮어써지지 않아야 한다. 공개 상세 화면의 title, description, OG 메타 fallback은 제목과 공개 가능한 본문 기준으로 계속 동작해야 한다. 공지사항 권한이 없는 사용자가 `is_notice=1` 또는 `/community/notice`를 직접 POST하면 저장이 거부되어야 하고, `write_notice` 게시판 운영권한을 가진 로그인 회원은 일반 쓰기 정책을 통과하지 못해도 공지사항으로 작성하거나 게시글 상세에서 공지 지정/해제를 할 수 있어야 한다. 게시판 목록에서는 선택한 정렬 기준 안에서 공지사항이 일반글보다 먼저 표시되어야 한다.

커뮤니티 임시저장을 확인할 때는 `/admin/community/settings`의 임시저장 섹션에서 자동 임시저장, 저장 간격, 보존기간, 계정당 최대 개수가 서버 검증 범위 안에서만 저장되는지 먼저 확인한다. 활성화 후 로그인 회원의 `/community/write?key=...`와 `/community/edit?id=...` 화면에서 제목, 본문, 카테고리, 비밀글, 공지사항, 추가 필드, 시리즈 선택을 바꾼 뒤 `/community/draft/autosave`가 JSON으로 성공 응답하고 새로 열었을 때 복원/삭제 alert가 나타나는지 확인한다. 복원은 CKEditor와 일반 textarea 모두에 본문을 되돌려야 하고, 삭제는 서버 draft와 같은 탭 `sessionStorage` 버퍼를 지워야 한다. 비로그인, CSRF 실패, 쓰기/수정 권한 없음, 자동저장 비활성 상태는 draft를 만들지 않고 401/400/403 계열 JSON으로 끝나야 한다. 정식 작성/수정 성공 후에는 같은 context의 draft가 삭제되어 다시 복원 alert가 나타나지 않아야 한다. 파일 input 값은 복원 대상이 아니며, 세션이 바뀐 임시 본문 이미지는 안내 문구로 대체되는지 확인한다.

1.0 릴리스 후보에서는 정적 점검 통과만으로 완료 판정하지 않는다. [1.0 범위 잠금 기준](1.0-scope.md)의 포함 모듈을 기준으로 HTTP 스모크와 필요한 브라우저 수동 점검을 함께 기록한다.

릴리스 후보나 큰 운영 보강 묶음에서는 개별 smoke를 실행하기 전에 설치 DB 게이트 상태표를 먼저 만든다.

```sh
php .tools/bin/release-installed-gate-status.php
```

필요한 옵션과 환경 변수는 `php .tools/bin/release-installed-gate-status.php --help`로 먼저 확인한다. 상태표 도구는 알 수 없는 옵션을 exit 2로 거부하므로 릴리스 자동화의 오타를 조용히 무시하지 않는다. `--markdown-table`과 `--json`은 서로 배타적인 출력 형식이므로 함께 지정하면 exit 2로 실패해야 한다. 기록 표에 붙일 Markdown 표가 필요하면 `php .tools/bin/release-installed-gate-status.php --markdown-table`을 실행하고, 자동화/보관용 구조화 증거가 필요하면 `php .tools/bin/release-installed-gate-status.php --json`을 실행한다. 상태표 도구는 `SR_SMOKE_BASE_URL`과 `SR_BROWSER_QA_BASE_URL`의 URL userinfo를 metadata, gate 환경값, 실행 출력 요약에 남기기 전에 마스킹해야 한다. 실행 출력 요약은 한국어, 체크마크 등 멀티바이트 문자가 포함되거나 길이 제한으로 잘릴 때도 UTF-8 안전한 단일 라인 문자열이어야 하며, `--json` 출력은 `json_decode()` 가능한 구조화 증거로 남아야 한다. CI나 릴리스 스크립트에서 미해결 게이트를 실패로 다루려면 `--fail-on-unresolved`를 함께 지정한다. 설치 DB를 읽을 수 있는 로컬/staging 실행 사용자라면 `--run-readonly`로 `reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run`까지 함께 기록한다. 현재 CLI 사용자가 `config/config.php`를 읽지 못하면 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 `php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved`를 다시 실행한다. 로컬/staging HTTP와 관리자 계정이 준비된 경우에는 `SR_SMOKE_BASE_URL`, `SR_SMOKE_ADMIN_IDENTIFIER`, `SR_SMOKE_ADMIN_PASSWORD`를 지정한 뒤 `php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved`로 구조화 증거를 남긴다. 관리자 read-only 화면도 같은 자격 증명으로 `--run-admin-readonly`를 추가하면 `/admin/assets/reconciliation`과 `/admin/operations`를 로그인 세션으로 GET하고 기대 화면 문구를 확인할 수 있다. base URL이 준비된 환경에서는 `--run-http-smoke`로 기본 route, 보안 헤더, 보호 경로 HTTP smoke를 상태표 안에서 실행하고, `--run-browser-qa`로 설치 DB가 필요 없는 CKEditor asset/fallback browser smoke를 실행할 수 있다. 기존 설치본 업데이트 흐름은 로컬/staging disposable DB에서 `SR_SMOKE_ALLOW_MUTATION=1`, 관리자 계정, `--run-update-smoke`를 함께 지정해 `smoke-update-apply.php`로 확인한다. 이 smoke는 기본적으로 `coupon 2026.05.003`의 `sr_schema_versions` 행을 지워 pending 상태를 만들고 `/admin/updates` POST, 적용 이력 복원, 감사 로그, 모듈 버전 동기화를 확인한다. 인증, 퀴즈 E2E, 자산/쿠폰/유료 접근권, 개인정보 export/cleanup, CKEditor upload/save mutation smoke는 각각 `--run-auth-smoke`, `--run-quiz-smoke`, `--run-asset-smoke`, `--run-privacy-smoke`, `--run-ckeditor-upload-save-smoke`를 쓰되, 로컬/staging disposable 데이터와 `SR_SMOKE_ALLOW_MUTATION=1`, 필요한 계정/관리자 계정, 자산 smoke의 `SR_SMOKE_FORM_PATH`와 `SR_SMOKE_EXPECT_DEDUPE_TABLE`/`SR_SMOKE_EXPECT_DEDUPE_KEY`가 모두 준비된 경우에만 실행한다. 개인정보 smoke는 대상 계정을 탈퇴/익명화하고, CKEditor upload/save smoke는 disposable 콘텐츠와 본문 이미지를 만든다. public-looking base URL에서 mutation smoke를 실행해야 하는 staging 환경은 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 함께 지정해야 한다. `--run-privacy-fixtures`와 `--run-performance-fixtures`는 SQLite/static fixture를 `부분 확인`으로 남길 뿐 설치 DB 개인정보 smoke나 대표 데이터 성능 수동 점검을 대체하지 않는다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_ADMIN_IDENTIFIER=admin \
SR_SMOKE_ADMIN_PASSWORD=12341234 \
SR_SMOKE_ALLOW_MUTATION=1 \
php .tools/bin/release-installed-gate-status.php --run-update-smoke
```

개인정보 export/cleanup smoke 하니스 자체는 `check-installed-gate-status.php`의 mock HTTP fixture로 로그인, export JSON 필수 key와 기본 타입(`exported_at`, `account_id`, `privacy_requests`, `module_exports.member`), 탈퇴 redirect, 탈퇴 후 기존 세션의 `/account` 접근 차단, 재로그인 차단 확인 흐름을 점검한다. 이 fixture는 설치 DB smoke 성공 기록을 대체하지 않고, 스크립트의 HTTP 계약 회귀를 잡기 위한 보조 증거다.

고부하 관리자 작업을 확인할 때는 production 데이터에서 파괴적 smoke test를 실행하지 않는다. 재계산/복사/삭제/저장소 정리 테스트는 local 또는 staging 더미 데이터로 수행하고, 소량/중량/대량 기준에서 부하 등급 안내, 확인 문구 서버 거부, 배치 진행/재시도 상태, 감사 로그 metadata가 남는지 확인한다. 작업 테이블형은 lock 만료 takeover 뒤 이전 `lock_token`의 늦은 쓰기를 거부하는지 확인하고, 재시도 시 대상 단위 완료 마커/map/dedupe로 중복 원장·중복 발송·중복 복사가 생기지 않는지 확인한다. 도메인 쓰기와 map/cursor/count 갱신이 같은 원자적 경계 안에 있는지, query snapshot drift가 절대 50건 이상 또는 10% 이상일 때 재확인 필요 상태로 멈추는지도 확인한다. timeout 유사 상황은 테스트 전용 sleep, 낮은 `max_execution_time`, batch 실패 주입, 중간 요청 중단으로 확인한다. 즉시 제한형 선택 기반 일괄 작업은 선택 없음, 허용되지 않은 `operation_key`, 허용되지 않은 상태, 존재하지 않는 ID, 100건 초과, 이미 같은 상태인 행 건너뜀, 감사 로그 metadata를 확인한다.

회원 목록의 마케팅 수신거부 명단 업데이트를 확인할 때는 local 또는 staging 더미 회원으로 CSV 샘플 다운로드, CSV/XLSX 업로드, `email`/`public_hash`/`login_id`/`account_id` 컬럼 매칭, 중복·미존재 식별자 제외, 처리 상한 초과 안내, 감사 로그 metadata를 확인한다. 업로드 후 기존 마케팅 동의 회원도 목록과 CSV 다운로드에서 미동의 상태로 표시되어야 한다.

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

PWA 1차 지원을 확인할 때는 설치된 로컬 또는 staging에서 `/manifest.webmanifest`가 `application/manifest+json`으로 응답하고, `/service-worker.js`가 `application/javascript`와 `Service-Worker-Allowed` 헤더를 반환하는지 확인한다. 서비스 워커는 공개 정적 asset만 cache 대상으로 삼고 관리자, 계정 개인정보, 저장소 경로, navigation fallback은 cache하지 않아야 한다.

사이트 메뉴를 2·3단계로 구성한 환경에서는 기본 공개, 콘텐츠, 커뮤니티, 퀴즈 헤더에서 2단계 dropdown과 3단계 flyout이 표시되는지 확인한다. 데스크톱에서는 hover와 Tab focus로 열리고 Escape와 바깥 클릭으로 닫혀야 하며, 모바일 폭에서는 하위 메뉴가 있는 링크가 첫 탭에서 펼쳐지고 열린 상태의 재탭은 링크 이동으로 이어져야 한다. 같은 메뉴 안에 동일 URL 항목을 다른 라벨이나 상위 항목으로 여러 개 저장할 수 있어야 한다. footer 메뉴와 관리자 메뉴는 헤더 dropdown 스타일의 영향을 받지 않아야 한다.

알림 delivery runner는 로컬 또는 staging에서 `/admin/notification-deliveries`의 수동 실행 버튼 또는 다음 CLI로 확인한다. 운영 DB에서 테스트 발송을 만들거나 provider 설정을 바꾸지 않는다.

```sh
php .tools/bin/run-notification-deliveries.php
```

CLI 사용법은 `php .tools/bin/run-notification-deliveries.php --help`로 확인한다.

알림 외부 푸시를 확인할 때는 `/admin/notifications/settings`에서 `기본환경`, `이메일`, `외부 알림 채널` 순서로 섹션이 보이고 SMTP와 메일 API가 이메일 섹션의 필드명에 붙어 표시되는지 먼저 확인한다. 기본환경의 웹/수동/CLI 처리 수는 이메일 통수가 아니라 발송 작업 수로 안내되어야 한다. 외부 알림 채널에서는 Slack/Discord/Telegram별 운영 알림 발송 여부와 회원 허용 여부를 따로 저장하고, 운영 알림 발송을 켠 provider의 운영 채널 표시명, 운영 webhook URL 또는 Telegram bot token/chat ID에는 필수 표시와 server-side validation이 함께 적용되어야 한다. 운영 webhook URL은 HTTPS만 허용되어야 하고 secret 원문은 화면, 감사 로그, delivery 목록, 오류 로그에 노출되지 않아야 한다. 관리자 운영 알림이 생성되면 운영 발송이 켜져 있고 운영 수신처 설정이 유효한 `slack_webhook`, `discord_webhook`, `telegram_bot` delivery가 queue되고, 회원 허용이 켜진 provider만 `/account/notifications`에서 개인 수신처 연결 폼을 보여야 한다. `/admin/notification-deliveries` 수동 실행 또는 CLI runner는 외부 delivery를 이메일과 같은 batch 안에서 처리해야 한다. 실제 provider URL/token 대신 로컬/staging mock endpoint를 우선 사용하고, 실제 provider key는 staging에서만 짧게 확인한다. 운영 DB에서는 webhook 설정 변경이나 테스트 발송을 실행하지 않는다.

설치 화면을 수정한 경우에는 `기본 정보` 단계의 기본 통화 선택지가 `sr_known_currency_min_units()` 기준과 일치하는지, 선택값이 최종 요약에 표시되는지, 설치 후 `/admin/settings`에서는 읽기 전용으로만 보이고 일반 설정 POST로 바뀌지 않는지 확인한다. owner에게는 사이트 설정 sticky submit 영역 좌측의 `통화 변경` 버튼이 `/admin/settings/currency`로 연결되어야 한다. 이 값은 신규 가격/정책 row 기본값이며 기존 가격·로그·구매력 snapshot 변환 스위치가 아니라는 안내도 함께 확인한다.

커뮤니티 모듈이 설치되어 있어야 하는 스테이징 검수에서는 설치 시 생성되는 기본 게시판과 데이터 독립적인 커뮤니티 라우트의 404 허용을 제거한 강한 모드로 실행한다. 설치 데이터에 포함되지 않는 `general` 게시판 그룹과 존재 여부가 보장되지 않는 게시글 ID 1 조회는 404를 허용한다. 비회원 글·댓글을 지원하는 수정·삭제·댓글 POST는 로그인 리다이렉트 대신 CSRF 검증이 먼저 실행될 수 있으므로, 토큰 없는 smoke 요청의 400 응답을 정상 보호 결과로 본다.

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
개발용 router도 운영 배포 규칙과 같이 공개 asset 경로의 `php`, `phtml`, `phar`, `sql` 파일 직접 응답을 403으로 차단하고, `config/`, `storage/`, `modules/` 내부 파일 같은 보호 경로를 직접 403으로 막아야 한다.

직접 접근을 차단해야 하는 경로의 정본은 [배포 보호 기준](deployment-protection.md)이다. 이 문서의 HTTP 항목은 그 기준이 실제 서버 응답에서 지켜지는지 빠르게 확인하기 위한 스모크 검증 목록이다.

확인 항목:

```text
/ 응답이 500 없이 열리는지 확인
/login 응답이 500 없이 열리는지 확인
/login/mfa 응답이 500 없이 로그인 또는 2차 인증 challenge 흐름으로 이어지는지 확인
회원 환경설정에서 로그인 2차 인증 정책을 `사용안함`으로 바꾸면 활성 TOTP factor가 있는 계정도 `/login/mfa`로 이동하지 않고 로그인되며, `선택`으로 바꾸고 TOTP provider를 허용하면 회원이 등록한 factor 기준으로 challenge가 복구되는지 확인
회원 환경설정에서 로그인 2차 인증 정책을 `필수`로 바꾸면 활성 TOTP factor가 있는 계정은 challenge를 거치고, factor가 없는 로그인 회원은 `/mypage/security`로 이동하며, `/mypage/security`의 2차 인증 해제 action이 거부되는지 확인
로그인한 계정의 `/mypage/security`에서 TOTP 준비가 현재 비밀번호 재확인 뒤 pending factor를 만들고, 등록용 QR 이미지, 수동 secret/otpauth URI, 첫 code 활성화 form을 보여주는지 확인
TOTP 활성화 직후 백업 코드가 한 번 표시되고, `/login/mfa`에서 미사용 백업 코드 1개로 로그인한 뒤 같은 백업 코드 재사용이 거부되는지 확인
로그인한 계정의 `/mypage/security`에서 백업 코드 재발급과 2차 인증 해제가 재인증, CSRF, PRG 흐름으로 처리되는지 확인
활성 TOTP factor fixture가 있는 계정은 1차 로그인 뒤 `/login/mfa`로 이동하고, 올바른 TOTP code 제출 후 원래 next 경로로 돌아가며 같은 time step code 재사용은 거부되는지 확인

회원 전용 모드와 MFA가 함께 켜진 local/staging fixture에서는 `SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:<port> SR_SMOKE_IDENTIFIER=<fixture-login> SR_SMOKE_PASSWORD=<fixture-password> SR_SMOKE_MFA_CODE=<current-code> php .tools/bin/smoke-member-mfa.php`를 실행한다. 이 smoke는 `/ui-kit` 같은 회원 전용 보호 대상이 로그인 전에는 `/login?next=...`로 이동하고, 1차 로그인 뒤 challenge 상태에서는 아직 보호 내용을 렌더하지 않으며, `/login/mfa` 검증 뒤 원래 `next`로 복귀하는지 확인한다.
/ui-kit 응답이 500 없이 열리고 Public UI-KIT 화면이 출력되는지 확인
/admin 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/updates 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/content/example 응답이 500 없이 열리지 않고, 미설치/비활성/없는 slug 상태에서는 404 등 허용된 응답으로 막히는지 확인
/admin/content 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
콘텐츠 모듈 설치 환경에서는 published 콘텐츠가 200으로 열리고 draft/hidden 콘텐츠는 공개 접근이 차단되는지 확인. scheduled 콘텐츠는 예약 시각 전 공개 접근이 차단되고, 예약 시각이 지난 뒤 공개/관리자 조회에서 published로 전환되는지 확인. 예약 시각은 현재보다 미래여야 하고, 예약 설정/해제/자동 전환 감사 로그가 남는지 확인한다. `/admin/content` 조회 권한이 있는 관리자 세션에서는 draft/scheduled 콘텐츠 공개 URL 미리보기가 열리고 열람 차감, 다운로드, 완료 버튼 처리가 실행되지 않는지 확인한다. 관리자 목록/수정 화면의 사용자 화면 미리보기는 `preview=admin`으로 열려 published 콘텐츠도 조회수를 올리지 않아야 한다
콘텐츠 그룹을 만든 환경에서는 /content/group?key=... 공개 목록이 200으로 열리고 비사용/보관 그룹은 공개 접근이 차단되는지 확인
콘텐츠 모듈 설치 환경에서는 공용 배너/팝업레이어 직접 선택과 `content.view` 노출 위치 규칙이 공개 콘텐츠에 반영되는지 확인
/admin/content/settings에서 콘텐츠 시리즈 기능 사용 설정을 저장하면 `sr_module_settings` 값이 갱신되고 다시 열어도 선택값이 유지되는지 확인한다. 끈 상태에서는 콘텐츠 시리즈 관리, 콘텐츠 편집 화면의 시리즈 연결, 공개 콘텐츠의 시리즈 내비게이션을 사용하지 않아야 하며, 콘텐츠 저장이 기존 시리즈 연결을 임의로 지우지 않아야 한다. /admin/content/series에서 콘텐츠 시리즈 등록 모달의 key, 제목, 상태, 공개 범위, 정렬 저장이 서버 검증을 통과하는지 확인. 설명이 서버 제한 길이를 넘으면 빈 값으로 저장하지 않고 오류를 표시해야 한다. 목록의 상태, 공개 범위, key/제목 검색 필터와 key, 제목, 상태, 공개 범위, 회차 수, 정렬, 수정일 헤더 정렬이 허용 목록 기반으로 동작하고, 상태와 공개 범위가 한국어 라벨로 표시되는지 확인한다. 콘텐츠 편집 화면에서 시리즈와 회차를 연결하면 공개 콘텐츠 본문 다음에 active 시리즈 내비게이션이 표시되고, 시리즈 정렬 순서는 서버에서 0 이상 1000000 이하 숫자로 검증되는지 확인한다. 유료 열람 콘텐츠가 포함된 시리즈는 완독 예상 금액을 자산별로 표시하고, 회원 그룹 정책 적용 금액이 다르면 원가와 회원가를 함께 표시하는지 확인한다. hidden/archived/deleted 시리즈 또는 hidden/removed 항목은 공개 출력에서 제외되는지 확인. 코드 배포 후 DB 업데이트 적용 전에는 콘텐츠 목록, 콘텐츠 시리즈 관리, 공개 콘텐츠, 개인정보 내보내기가 시리즈 테이블 누락으로 500을 내지 않아야 한다.

/admin/community/boards, /admin/community/board-groups, /admin/content-groups, /admin/content/series에서 삭제 버튼은 CSRF와 관리자 delete 권한을 요구해야 한다. 게시판 삭제는 게시글이 있어도 가능하며 설정, 설정 소스, 카테고리, 게시글, 댓글, 스크랩, 커뮤니티 시리즈/항목, 첨부 DB 행과 저장소 파일을 함께 삭제해야 한다. 단 사이트 메뉴/배너/팝업/쿠폰 같은 외부 운영 참조가 있으면 게시판 삭제는 차단되어야 하며, 자산 로그는 삭제하지 않아야 한다. 게시판 그룹 삭제는 연결 게시판이 있어도 가능하며 게시판은 삭제하지 않고 `board_group_id`만 비운 뒤 그룹 row와 legacy 그룹 설정 row를 삭제해야 한다. 단 사이트 메뉴 같은 외부 운영 참조가 있으면 차단되어야 한다. 콘텐츠 그룹 삭제는 연결 콘텐츠가 있어도 가능하며 콘텐츠, 댓글, 파일, 리비전은 삭제하지 않고 `content_group_id`만 비운 뒤 그룹 row와 legacy 그룹 설정 row를 삭제해야 한다. 단 사이트 메뉴/초기화면 참조가 있으면 콘텐츠 그룹 삭제는 차단되어야 하며, 콘텐츠 자산 로그와 파일 다운로드 로그는 삭제하지 않아야 한다. 파일 다운로드 로그는 콘텐츠/파일 삭제 후에도 저장 당시 콘텐츠 제목, slug, 파일 제목, 원본 파일명 스냅샷으로 조회와 검색 맥락을 유지해야 한다. 삭제 후 저장소 파일 정리가 실패하면 삭제 완료 문구와 파일 정리 실패 안내가 분리되어 표시되고, 실패한 driver/key가 커뮤니티 또는 콘텐츠 저장소 정리 실패 테이블과 관리 목록 화면에 남아야 한다. 관리자는 실패 항목의 재시도 버튼으로 저장소 삭제를 다시 실행할 수 있고 성공 시 항목이 pending 목록에서 사라져야 한다. 콘텐츠 시리즈 삭제는 콘텐츠 자체를 삭제하지 않고 `sr_content_series_items` 연결만 제거한 뒤 시리즈를 삭제해야 하며, 외부 운영 참조가 있으면 삭제되지 않아야 한다. 각 삭제 성공은 감사 로그에 대상 key/title과 삭제·참조 개수를 남겨야 한다.
/admin/member-group-rules에서 그룹, 조건, 자동 배정 방식, 상태, 최근 평가 헤더 정렬이 허용 목록 기반으로 동작하는지 확인
/admin/member-groups의 수동 배정 모달에서 이미 수동 배정된 회원을 같은 그룹에 다시 배정하면 새 배정이 추가되지 않고 중복 배정 안내 토스트와 감사 로그가 남는지 확인
/admin/members 회원 목록에서 현재 페이지 회원 선택 체크박스, 전체 선택, 선택 수 표시, 세션 일괄 회수가 동작하는지 확인한다. 목록에는 이메일 인증, 최근 로그인, 가입일 컬럼을 노출하지 않고 회원 모듈의 최신 `marketing` 동의 상태를 표시해야 한다. CSV 다운로드 버튼은 `/admin/members/export` 옵션 화면으로 이동해야 하며, 다운로드 옵션 화면에서 상태/검색/정렬/파일당 최대 건수, 이메일·휴대폰 번호 마스킹 여부, 포함 컬럼 추가/제거/순서를 지정하고 CSV 다운로드 버튼의 대상 수 표시를 확인한 뒤 CSV를 받을 수 있어야 한다. 다운로드 가능 대상이 있으면 다운로드 파일 섹션이 구간별 CSV 다운로드 버튼을 1열로 보여야 하고, 옵션 변경 뒤 대상 확인을 누르면 파일 목록이 업데이트된다는 안내를 표시해야 하며, 대상이 파일당 최대 건수를 초과하면 각 버튼으로 이어 다운로드할 수 있어야 한다. CSV에는 포함 컬럼 행만 지정 순서대로 생성하고, 목록에서 제외한 이메일 인증, 최근 로그인, 가입일을 포함하지 않아야 한다. 휴대폰 번호는 컬럼 추가 후보에서 선택할 수 있어야 하며, 이메일과 휴대폰 번호는 마스킹을 선택하지 않으면 원문으로 다운로드되고 선택하면 마스킹되어야 한다. 회원 수정 화면은 최신 마케팅 수신동의 상태, 기록 시각, 문서 snapshot 제목과 버전을 읽기 전용으로 보여야 한다. 쪽지 모듈이 활성이고 현재 관리자가 쪽지를 보낼 수 있으면 활성 회원 행에 쪽지 발송 버튼이 표시되고 `/message/write?to_account=...`로 수신자가 미리 선택되어야 한다. 회원 수정 화면도 페이지 타이틀 아래에 목록과 같은 조건의 쪽지, 차단, 탈퇴, 익명화, 그룹 규칙 재평가, 세션 회수 버튼을 버튼명과 함께 표시해야 하며 일반 작업은 왼쪽, 위험 작업은 오른쪽에 두어야 한다. 목록 행은 수정, 쪽지, 그룹 규칙 재평가만 바로 노출하고 차단, 탈퇴, 익명화, 세션 회수는 `위험작업` 모달에서 처리해야 한다. 탈퇴/익명화 회원에게는 그룹 규칙 재평가 버튼을 표시하지 않아야 한다. 서버는 `intent=batch_revoke_sessions`, `operation_key=member.revoke_sessions`, `selected_account_ids[]`를 다시 검증해야 하며, 선택 없음, 100건 초과, 존재하지 않는 회원, 현재 관리자 본인, owner가 아닌 관리자의 owner 대상 회수를 거부해야 한다. 성공 시 선택 회원의 활성 세션만 회수하고 감사 로그 metadata에 선택 ID와 회원별 회수 건수, 총 회수 건수를 남겨야 한다. 회원 상태 변경과 선택 회원 그룹 재평가는 이 즉시 제한형 작업에 포함하지 않는다. 세션 회수와 회원 그룹 재평가는 성공/실패 후 GET 화면으로 돌아와야 하며, 브라우저 새로고침으로 토스트나 감사 로그가 반복되지 않아야 한다.
/admin/member-settings에서 회원 세션 유효시간을 초 단위로 저장하고 다시 열었을 때 값이 유지되는지 확인한다. 1800초 미만이나 2592000초 초과 값은 서버 검증에서 거부되어야 한다. 값을 줄이면 기존 로그인 세션도 `created_at + 현재 설정 lifetime`을 지난 다음 요청에서 로그아웃될 수 있음을 안내가 보여야 하며, 값을 늘려도 이미 저장된 `sr_member_sessions.expires_at`을 넘어 기존 세션이 되살아나지 않아야 한다. 브라우저 세션 쿠키와 PHP 런타임 세션 수명이 별도라는 안내도 확인한다.
/admin/member-group-rules의 규칙 저장, 그룹 규칙 평가, /admin/member-groups의 회원 규칙 평가도 성공 후 GET 화면으로 돌아와야 하며, 평가 실행 결과 토스트와 감사 로그가 새로고침으로 반복되지 않아야 한다.
회원 제출본을 21건 이상 만든 로컬 더미 계정에서는 `/account/content`의 두 번째 페이지와 마지막 부분 페이지까지 이동할 수 있어야 한다. 두 번째 페이지에서 편집 가능한 제출본을 열고 임시저장·제출 또는 검증 실패 후 돌아와도 편집 대상과 가능한 현재 목록 페이지가 함께 유지되는지 확인한다.

콘텐츠 등록자 신청, 작성자 승인, 회원 제출본을 각각 21건 이상 만든 로컬 fixture에서는 `/admin/content/author-applications`, `/admin/content/authors`, `/admin/content/submissions`가 전체 건수와 현재 표시 범위를 보여주고 마지막 부분 페이지까지 이동할 수 있어야 한다. 상태·검수 조건·신청 ID 필터를 적용한 count와 행 결과가 일치하고, 두 번째 페이지에서 승인·수정·검수 작업을 처리한 뒤 같은 필터와 페이지로 돌아오는지 확인한다.

/admin/content/submissions, /admin/content/author-applications, /admin/content/authors, /admin/community/board-copy-jobs, /account/content/author-application의 처리 POST는 성공 후 GET 화면으로 돌아와야 한다. 검수/신청/작성자 승인/복사 작업 실행 결과는 한 번만 표시되고 새로고침으로 상태 변경, 알림 생성, 복사 묶음 실행이 반복되지 않아야 한다. `/admin/content/authors`의 작성자 승인 추가/수정은 목록 위 상시 폼이 아니라 승인 목록의 추가/수정 모달에서 처리되는지 확인한다. 작성자 승인 추가 모달의 회원 선택은 직접 숫자 ID 입력만 요구하지 않고 회원 검색 모달에서 선택한 회원 식별자를 저장할 수 있어야 한다.
/admin/privacy-requests의 대응 기록 추가는 목록 위 상시 폼이 아니라 `대응 기록 추가` 모달에서 처리되는지 확인한다. 생성 모달은 계정 ID 또는 요청자 중 하나, 요청 유형, 요청 내용을 서버에서 검증해야 하며, 실패 후 GET 화면으로 돌아왔을 때 직전 입력값과 오류 요약을 유지한 채 모달을 다시 열어야 한다. JavaScript 비활성 환경에서는 noscript 대체 폼으로 같은 `intent=create_request` POST 흐름을 사용할 수 있어야 한다.
퀴즈·설문 댓글을 각각 21건 이상 만든 로컬 fixture에서는 `/admin/quiz/comments`와 `/admin/surveys/comments`가 전체 건수와 현재 표시 범위를 보여주고 다음 페이지를 제공해야 한다. 검색어·상태·비밀 댓글 필터를 적용한 count와 행 결과가 일치하고, 두 번째 페이지에서 상태를 변경한 뒤 같은 필터와 페이지로 돌아오는지 확인한다.

설문과 리액션 사용 기록을 각각 21건 이상 만든 로컬 fixture에서는 `/admin/surveys`와 `/admin/reactions/records`가 전체 건수와 현재 표시 범위를 보여주고 마지막 부분 페이지까지 이동할 수 있어야 한다. 설문 상태·응답 가능·검색 필터와 리액션 회원·대상·리액션 키 필터의 count가 행 결과와 일치해야 하며, 설문 두 번째 페이지에서 삭제 또는 영구 삭제한 뒤 같은 필터와 페이지로 돌아오는지 확인한다.

쿠폰 발급 캠페인과 발급 로그를 각각 21건 이상 만든 로컬 fixture에서는 `/admin/coupons/campaigns`와 `/admin/coupons/campaigns/logs`가 전체 건수와 현재 표시 범위를 보여주고 마지막 부분 페이지까지 이동할 수 있어야 한다. 캠페인 상태·발급 유형·공개 여부·검색 필터와 로그 회원·상태·발급 표면·캠페인/쿠폰 검색 필터의 count가 행 결과와 일치해야 한다. 쿠폰 정의가 300건을 넘는 환경에서 오래된 정의를 연결한 캠페인을 수정해도 현재 연결 쿠폰이 선택기에 남아야 한다.

설문이 300건을 넘는 환경에서는 오래된 설문을 선택한 `/admin/surveys/responses`, `/admin/surveys/statistics`, `/admin/surveys/reward-logs`를 열어도 현재 설문이 선택기에 남아야 한다. 활성 쿠폰 정의가 200건을 넘는 환경에서는 오래된 쿠폰을 보상으로 저장한 퀴즈·설문과 퀴즈 기본 설정을 수정할 때 현재 쿠폰이 선택기에 남아야 한다.

공개 중인 퀴즈·설문·쿠폰 발급 캠페인이 각각 페이지당 표시 수보다 많은 로컬 fixture에서는 `/quiz`, `/survey`, `/coupons`의 다음 페이지와 마지막 부분 페이지까지 이동할 수 있어야 한다. 기간 종료·비공개·삭제 항목은 전체 count와 각 페이지 행에서 모두 빠져야 하며, `/coupons?campaign={campaign_key}` 단건 화면은 목록 페이지네이션 없이 기존 상세 캠페인만 표시해야 한다.

정책 문서 안내메일 작업이 관리자 페이지당 표시 수보다 많으면 `/admin/policy-documents`의 `mail_page`로 다음 페이지와 마지막 부분 페이지까지 이동할 수 있어야 한다. 현재 페이지에서 배치 발송, 실패 재대기, 남은 발송 취소를 실행한 뒤에는 같은 `mail_page`로 돌아와야 한다.

회원의 자산 환전 내역이 20건을 넘는 로컬 fixture에서는 `/account/asset-exchange`의 `history_page`로 다음 페이지와 마지막 부분 페이지까지 이동할 수 있어야 한다. 환전 견적을 확인한 상태에서 내역 페이지를 이동해도 `policy_id`와 `amount` 쿼리가 유지되어야 하며, 다른 회원의 환전 내역은 count와 페이지 행에 포함되면 안 된다.

인증 로그나 로그인 세션이 100건을 넘는 회원의 개인정보 사본은 최신 100건만 포함하되 해당 섹션의 `has_more`와 모듈별 `partial` 상태를 기록해야 한다. 100건 이하인 섹션은 완전 제공으로 표시되어야 하며, 초과 사실 없이 조용히 잘린 사본을 완료 상태로 제공하면 안 된다.

/admin/community/posts, /admin/community/comments, /admin/quiz/comments, /admin/surveys/comments, /admin/admin-notifications, /admin/notification-deliveries, /admin/surveys/responses, /admin/community/series, /admin/content/series, /admin/privacy-requests 목록의 행 단위 상태 변경은 필터용·생성/편집용 셀렉트를 제외하고 상태 변경 셀렉트를 사용하지 않아야 한다. 현재 상태를 제외한 다음 상태 버튼만 보이고, 삭제·거절·취소·실패·분석 제외 계열은 확인 후 제출되어야 하며, 직접 POST한 허용되지 않은 상태 값은 서버에서 거부되어야 한다. 처리 후에는 기존 필터·검색·정렬·페이지 쿼리로 돌아와야 한다. `/admin/community/posts`의 상태 필터와 행 버튼은 대기->공개->숨김->삭제 순서로 표시하고, `/admin/community/comments`는 현재 댓글 상태 계약에 대기 상태가 없으므로 공개->숨김->삭제 순서로 표시해야 한다. 상태 배지는 삭제됨처럼 상태명을 표시해도 행 작업 버튼은 삭제처럼 실행명을 표시해야 한다.
`/admin/surveys/statistics`의 설문 선택 필터는 상세검색·초기화 버튼 없이 설문 선택과 검색만 표시되어야 한다. 검색 버튼은 좌측에서 설문 셀렉트 바로 다음에 위치하고, CSV 보조 액션은 문항별 통계 섹션 헤더에 표시되어야 한다. 필터와 선택된 설문 요약 섹션 사이에는 공통 카드 간격이 있어야 하며, 문항별 통계는 다른 관리자 목록과 같은 목록 카드/테이블 스타일로 표시되어야 한다.
/admin/banners 목록에서 현재 페이지 배너 선택 체크박스, 전체 선택, 선택 수 표시, 상태 일괄 변경이 동작하는지 확인한다. 서버는 `intent=batch_status`, `operation_key=banner.set_status`, `selected_banner_ids[]`, `target_status`를 다시 검증해야 하며, 다른 모듈에서 참조 중인 enabled 배너는 일괄 비활성화가 차단되어야 한다. `/admin/popup-layers` 목록도 같은 기준으로 `operation_key=popup_layer.set_status`, `selected_popup_ids[]`를 검증하고 참조 중인 enabled 팝업레이어 일괄 비활성화를 차단해야 한다. `/admin/logo-manager` 로고 배치 목록은 `operation_key=logo_manager.set_status`, `selected_logo_ids[]`, `target_status=active|disabled`를 검증하고 선택 없음, 100건 초과, 존재하지 않는 ID를 거부해야 한다. `/admin/community/posts` 게시글 목록은 `intent=batch_post_status`, `operation_key=community.post_set_status`, `selected_post_ids[]`, `target_status=hidden|published`를 검증하고 공개->숨김, 숨김->공개 전이만 일괄 허용해야 한다. 보상 회수 설정이 켜진 환경에서는 회수 실패 시 전체 일괄 변경이 롤백되어야 하며, 첨부파일 상태 복구와 회원 레벨/그룹 재평가가 함께 실행되어야 한다. `/admin/community/comments` 댓글 목록은 `intent=batch_comment_status`, `operation_key=community.comment_set_status`, `selected_comment_ids[]`, `target_status=hidden|published`를 검증하고 공개->숨김, 숨김->공개 전이만 일괄 허용해야 한다. 댓글 보상 회수 실패 시 전체 변경이 롤백되고 회원 레벨/그룹 재평가와 감사 로그 metadata가 남아야 한다. `/admin/notifications` 알림 목록은 `operation_key=notification.set_status`, `selected_notification_ids[]`, `target_status=active|deleted`를 검증하고 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 감사 로그 metadata를 확인한다.
`/admin/community/reports` 신고 목록은 대상 칸에 게시글/댓글 게시물 새 탭 바로가기 아이콘만 표시하고 아이콘 `title`에 게시물 제목이 들어가는지 확인한다. 처리된 신고의 상태 셀에는 감사 로그 권한이 있는 관리자에게 대상 조치 로그 링크가 표시되고, 해당 링크가 `metadata`의 `"report_id":ID` 검색으로 이동하는지 확인한다. `operation_key=community.report_set_status`, `selected_report_ids[]`, `target_status`, `target_action`, `reporter_action`을 검증하고 현재 페이지 선택, 전체 선택, 공통 검토 메모 적용, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다. 단건과 일괄 대상 조치는 신고 상태를 `resolved`로 저장할 때만 허용되고, `open`, `reviewing`, `dismissed`와 대상 조치가 함께 제출되면 서버에서 거부되어야 한다. 일괄 숨김/삭제는 게시글·댓글 신고에만 적용되며 쪽지 신고가 섞이면 서버에서 거부해야 한다. 삭제+게시자 정지와 숨김+게시자 정지는 대상 상태 변경과 회원 정지가 같은 트랜잭션에서 처리되어야 한다. 허위신고자 조치는 신고 상태를 `dismissed`로 저장할 때만 허용되고, 다른 상태와 함께 제출되면 서버에서 거부되어야 한다. 일괄 처리 성공 시 대상 조치와 신고자 조치 적용 건수 및 결과 metadata가 남아야 한다. `dismissed` 상태는 이미 적용된 대상 조치를 되돌리지 않는다.
`/admin/admin-notifications` 운영 알림 목록은 권한이 있는 알림만 표시하고, 헤더 드롭다운의 열린 알림 수와 최근 항목이 같은 권한 기준을 따르는지 확인한다. 읽음, 안 읽음, 확인, 처리됨, 보관, 다시 열기 POST는 CSRF와 현재 관리자 권한을 다시 검증해야 하며, action URL은 `/admin/...` 내부 상대 경로만 이동 링크로 노출되어야 한다. 헤더 드롭다운 항목 본문 클릭은 읽음 처리 후 바로가기로 이동하고, 항목의 읽음 버튼은 이동 없이 해당 알림만 읽음 처리하면서 드롭다운에서 즉시 제거해야 한다. 운영 알림 바로가기는 가능한 경우 대상 건만 보이도록 `/admin/community/reports?report_id=ID`, `/admin/privacy-requests?request_id=ID`, `/admin/content/author-applications?application_id=ID`, `/admin/notification-deliveries?delivery_id=ID` 단건 필터로 이동해야 한다. 같은 dedupe key 이벤트가 다시 발생하면 occurrence count와 최근 발생 시각이 갱신되고, 처리됨/보관 상태였던 알림은 열린 상태로 돌아와야 한다. 보존 정리는 열린 운영 알림을 삭제하지 않고 처리됨/보관됨 운영 알림과 확인 기록만 알림 보관일 기준으로 정리해야 한다. 알림 모듈이 비활성화되었거나 운영 알림 테이블 업데이트 전이면 신고, 신청, 개인정보 요청 같은 원래 업무 저장은 실패하지 않아야 한다.
`/admin/content` 콘텐츠 목록은 `operation_key=content.set_status`, `selected_content_ids[]`, `target_status=draft|published|hidden`을 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다. 예약 상태는 별도 예약 일시가 필요하므로 일괄 상태 변경 대상에서 제외하고, 공개 전환 시 기존 공개일이 없으면 서버 현재 시각으로 공개일을 채워야 한다.
`/admin/content-groups` 콘텐츠 그룹 목록은 `operation_key=content.group_set_status`, `selected_group_ids[]`, `target_status=enabled|disabled|archived`를 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다.
`/admin/content/files` 다운로드 파일 목록은 `operation_key=content.file_set_status`, `selected_file_ids[]`, `target_status=active|hidden`을 검증하고 현재 페이지 선택, 전체 선택, 조건부 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 동시 상태 변경 충돌, 감사 로그 metadata를 확인한다. 상태 일괄 변경은 파일 삭제나 저장소 정리 작업을 실행하지 않아야 한다.
`/admin/coupons` 쿠폰 종류 목록은 `intent=batch_definition_status`, `operation_key=coupon.definition_set_status`, `selected_definition_ids[]`, `target_status=active|issue_stopped|disabled`를 검증하고 현재 페이지 선택, 전체 선택, 상태 전이, 선택 없음, 100건 초과, 존재하지 않는 ID, 감사 로그 metadata를 확인한다. 쿠폰 정의 상태는 `active`가 신규 지급과 기존 지급분 사용을 모두 허용하고, `issue_stopped`가 신규 지급과 공개 발급 캠페인을 막되 기존 지급분 사용은 허용하며, `disabled`가 신규 지급과 기존 지급분 사용을 모두 막는다. 발급/사용 이력 또는 운영 참조는 상태 변경 차단 조건이 아니라 참조 현황과 감사 로그로 확인한다. `/admin/coupons/settings`에서는 공개 쿠폰존 명칭이 저장되고, `/admin/coupons/notification-templates`에서는 지급, 사용, 사용 환불, 발급 환불, 지급 상태 변경, 사용 중지 회수 안내 알림의 제목, 본문, 사용 여부와 회원 대상 알림 채널이 케이스별로 저장되어야 한다. 켜진 케이스에서 채널을 하나도 선택하지 않은 설정은 서버 검증에서 거부되어야 한다. `/admin/coupons/campaigns`는 무료/유료 발급 유형, 유료 가격/통화, 허용 포인트/금액 항목을 저장하고, 유료 캠페인은 허용 항목을 하나 이상 요구해야 한다. 공개 `/coupons`는 설정한 쿠폰존 명칭과 유료 캠페인의 가격, 허용 항목 선택을 표시하고, POST 시 선택 항목으로 보유 자산을 차감한 뒤 발급해야 한다.
콘텐츠 자산 기능을 활성화한 환경에서는 유료 열람, 유료 다운로드, 완료 버튼 자산 처리가 비로그인 접근을 로그인으로 보내고, 로그인 회원은 잔액/중복 정책에 맞게 처리되는지 확인. 최초 1회와 반복 과금 모두 GET 접근만으로 차감되지 않고 확인 POST 후 처리되어야 하며, 이미 접근권이 있는 once 대상은 재확인 없이 열려야 한다. 다운로드 파일은 `/admin/content/files`에서 먼저 등록한 뒤 콘텐츠 생성/수정 화면에서 연결해야 하며, 숨김 파일은 공개 다운로드와 연결 후보에서 제외되어야 한다. 회원 그룹별 적용을 저장하고 콘텐츠/파일에서 여러 적용을 선택한 경우 최종 금액과 `group_policy_snapshot_json`이 로그에 남고, `/admin/content/file-downloads`의 유료 다운로드 내역은 연결 차감 로그의 자산 단위 차감량, 기준 settlement 금액/통화, `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`을 사람이 읽을 수 있는 요약으로 보여야 한다. 금액 0 처리나 처리 안 함처럼 최종 금액이 0인 경우 원장 거래 없이 접근권 또는 완료 로그가 `completed` 상태로 남는지 확인. 유료 다운로드는 S3 서명 URL 생성 또는 로컬 파일 경로 확인이 실패하면 자산 차감 없이 오류로 끝나야 하며, 전달 준비가 끝난 뒤에만 차감하고 리다이렉트/스트리밍해야 한다. 최초 설치 후 새 콘텐츠의 자산 설정은 사용하지 않음이며 포인트 등 특정 자산이 미리 선택되어 있지 않아야 한다. 콘텐츠 그룹에서 새 콘텐츠를 추가해도 콘텐츠 그룹 설정이 입력폼에 복사되지 않고, 콘텐츠 그룹 관리 화면은 기본 정보만 제공하는지 확인. 콘텐츠 수정 화면에서 `그룹`/`전체` 적용을 선택하면 현재 편집값이 대상 콘텐츠에 한 번 복사된다. 완료 버튼 자산 처리는 콘텐츠 조회만으로 실행되지 않아야 한다. 복합 자산 설정은 자산 선택과 금액 입력이 같은 묶음으로 보이고, 완료 버튼 지급 방향에서는 처리 항목을 하나만 선택할 수 있으며 차감 방향에서는 선택 항목별 금액이 각각 차감되는지 확인한다. `/admin/content/settings`에서 복합 자산 결제를 끄면 콘텐츠 유료 열람/다운로드 저장에서 여러 포인트/금액 항목 선택이 서버 검증으로 거부되고, 일부 할인 쿠폰 후 남은 금액이 두 항목 이상으로 배분되는 public 결제는 쿠폰 사용과 자산 차감, payment-unit row를 남기지 않고 실패해야 한다. 회원 그룹별 적용 선택지는 선택한 자산에 맞는 정책만 보여야 하며, 선택 자산을 바꾸면 맞지 않는 적용 뱃지가 제거되어야 한다. 모든 자산을 해제한 뒤 저장하면 다시 체크되지 않아야 한다.

settlement 기반 복합 차감을 도입한 환경에서는 확인 token 재시도와 동시성 기준을 별도로 확인한다. 클라이언트 요청 토큰은 HTTP attempt가 아니라 구매 의도(intent)마다 확인 화면 렌더 시점에 1회 생성되어야 하며, 허용 길이를 넘은 확인 token은 잘라서 검증하지 않고 거부해야 한다. 같은 토큰으로 POST를 재시도하면 dedupe key가 회원/참조/기준금액/통화/요청 토큰 같은 안정 입력에서만 파생되고, 성공 후에는 잔액 snapshot이나 자산별 계산 결과가 바뀌어도 원장을 다시 만들지 않고 저장된 성공 결과와 표시용 snapshot을 반환해야 한다. 재검증 거부나 실행 실패는 rollback으로 claim row도 사라지므로, 재시도 시 저장된 거부 결과가 아니라 현재 상태로 부작용 없이 재평가되어야 한다. 실행 트랜잭션은 원장 row lock보다 먼저 unique 제약이 있는 claim row를 insert해야 하며, 두 탭 동시 제출은 duplicate-key에서 `processing` 또는 저장된 성공 결과로 흡수되고 lock 획득 뒤에도 claim row 상태를 다시 확인해야 한다. 확인 window를 막 지난 late duplicate도 만료 직후 새 실행이 아니라 저장된 성공 결과로 떨어져야 한다. InnoDB의 미커밋 unique claim 중복 insert가 블록되는 경우를 고려해 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 fixture로 확인한다. 확인 화면 이후 실행 전 다른 탭에서 잔액이 줄면 row lock 안에서 확인 시점 plan 수량을 재검증하고, 구매력/통화 min-unit/`rounding_policy_version`이 바뀌면 snapshot drift 사유로 별도 기록하며, 두 경우 모두 재계획 없이 거부하고 재확인을 요구해야 한다. 다중 자산 row lock은 `deduction_order`와 `asset_module` tiebreak 순서로 잡는지 확인한다. `purchase_power`는 `asset_units`, `settlement_units`, `settlement_currency` 구조로 snapshot에 저장되고, `asset_units`/`settlement_units` 양의 정수 여부와 `settlement_currency`의 min-unit registry 존재 여부는 설정 저장 또는 관리자 config 로드 시점에 setup 오류로 드러나야 한다. 가격 통화와 모든 참여 자산의 settlement 통화가 일치하지 않으면 관리자 설정 오류 또는 실행 거부로 드러나야 한다. settlement 로그에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`이 저장되어야 하며, 기존 `legacy 1:1 assumed` 또는 `legacy_unknown` 차감 로그는 업데이트에서 삭제되어야 한다. 1P = 10 KRW, 가격 1,005 KRW 같은 케이스는 정확 충당 불가로 실패하고 ceil overpay가 없어야 하며, 기준금액 0은 차감 없이 `settlement_amount=0` 로그와 접근권만 남겨야 한다. 통화 min-unit 또는 rounding/carry `rounding_policy_version` 변경 직후 기존 확인 화면의 in-flight 요청은 fail-closed 재확인으로 떨어질 수 있음을 운영 워크플로에서 확인한다. 복합 차감에 참여하는 `member-assets.php` 거래 helper는 같은 PDO transaction에 동참해야 하고, 내부 commit/별도 connection을 쓰는 자산은 후보에서 제외되어야 한다. 문서 정적 체크는 계약 조항 삭제 방지용이므로 transaction 동참, carry, overpay, lock 순서는 구현 테스트 fixture와 필요한 HTTP smoke로 행위를 검증한다.

자산 환전 모듈을 활성화한 환경에서는 `/admin/asset-exchange`에서 회원 OAuth 설정 화면처럼 섹션 이동 탭과 카드형 인라인 폼이 표시되고, 하단 sticky 제출 영역에 `전체 사용`, `전체 해제`, 저장 버튼이 있는지 확인한다. 첫 섹션에는 환전 사용 여부와 환전 신청 본인확인이 표시되고, 관리자 메뉴에는 별도 환전 환경설정 항목이 없어야 한다. `전체 사용`은 회원 OAuth 설정의 전체 사용 버튼처럼 `btn-outline-secondary`, `전체 해제`는 회원 OAuth 설정의 전체 해제 버튼처럼 `btn-solid-light`로 표시하고 아이콘은 붙이지 않는다. 고정 6개 방향 카드의 첫 행에는 출금 기준값, 입금 기준값, 저장 가능/저장 불가/중지 상태 뱃지만 표시되어야 하며, 전역 설정, 방향별 기준값, 상태, 최소·최대 금액, 소수 처리 방식, 수수료 조건은 한 번의 저장으로 함께 반영되어야 한다. 최소·최대 금액은 환산 기준처럼 한 행에 표시되고 소수 처리 방식보다 위에 있어야 하며, 소수 처리 방식은 셀렉트가 아니라 라디오 토글 그룹으로 표시되어야 한다. 탭과 카드 타이틀은 각 방향의 기준값과 소수 처리 방식으로 입금액이 1 이상이 되는 최소 실행 단위를 `포인트(1) -> 적립금(2)`처럼 직접 표시해야 하며, 포인트/적립금/예치금 항목명과 단위는 각 환경설정의 표시명과 단위를 따라야 한다. 각 방향 카드는 기준값과 소수 처리 방식으로 계산한 최소 허용 환전량을 최소 환전량 입력의 min 값에 즉시 반영해야 한다. 별도 `alert-info` 환산 기준 블록, 실행 상태 행, 실행 단위 행, 계산 상세 뱃지는 표시하지 않는다. 방향별 기준값과 최소 환전량 또는 수수료 보정을 같은 제출에서 함께 저장하면, 기존 저장값이 아니라 제출된 카드값 기준으로 검증되어야 한다. 입력되었거나 저장된 최소 환전량이 계산된 최소 허용 환전량보다 작으면 카드 헤더 아래 alert 블록에 문제, 계산식, 해결 방법 목록이 표시되고 저장되지 않아야 한다. 사용 상태 정책은 최소 환전량 기준 입금 결과가 0이거나 수수료 차감 후 0 이하가 되는 설정으로 저장되지 않아야 하며, 실패 시 alert, 토스트, 브라우저 validation이 환전 방향과 계산 근거를 함께 알려주고 제출값을 한 번 다시 표시해야 한다. 저장 실행 시 alert 블록이 있으면 가장 앞에 있는 alert 블록으로 포커스와 스크롤이 이동해야 한다. 정렬순서 입력은 노출하지 않고 canonical 6방향 순서를 사용한다. 기존 `/admin/asset-exchange/settings` 경로는 `/admin/asset-exchange`로 이동해야 한다. 환전 사용 여부를 끄면 회원 화면의 신청 후보, 예상 금액 계산, 확정 POST, helper 직접 실행이 모두 거부되어야 하며, 기존 환전 로그 조회와 완료 묶음 정정은 계속 가능해야 한다. 수수료 적용이 `사용 안 함`이면 수수료 설정 입력이 숨겨지고 저장값도 비워져야 한다. 수수료를 적용할 때는 정률 또는 정액 중 하나만 설정할 수 있어야 하며, 수수료 적용 조건은 `사용 안 함`과 `항상 적용`만 제공해야 한다. 정률 수수료는 기준값 100의 퍼센트 숫자 하나로 계산하고, 계산 결과 소수점은 같은 카드의 소수 처리 방식을 따라 버림, 반올림, 올림 처리되어야 한다. 활성 무수수료 정책은 반복 환전으로 가치가 증가하는 환산 기준/소수 처리 방식 조합이 서버 저장에서 거부되는지 확인한다. 이전 임의 정책 row는 업데이트 또는 설정 동기화 후 회원 환전 후보에 남지 않아야 하며, 기존 로그는 실행 당시 스냅샷을 유지하고 삭제된 정책 참조만 비워져야 한다. `/account/asset-exchange`에서 예상 출금액, 입금액, 수수료, 최종 증가액을 확인한 뒤 확정하면 출금 자산 원장 `exchange_out`, 입금 자산 원장 `exchange_in`, 수수료가 있는 경우 `exchange_fee`가 같은 환전 묶음 ID로 남고, 성공/실패 후 새로고침으로 중복 실행되지 않아야 한다. `/admin/asset-exchange/logs`에서 완료 환전 묶음 정정 버튼을 실행하면 CSRF와 관리자 edit 권한을 거쳐 반대 원장 거래, 정정 로그, `asset_exchange.log.corrected` 감사 로그가 남는지 확인한다. 이미 정정된 묶음이나 실패/정정 로그는 다시 정정되지 않아야 한다. 확정 토큰은 예상 금액의 정책/금액/quote에 묶여야 하며 만료되거나 다른 금액으로 바뀐 제출은 거부되어야 한다. 짧은 시간 반복 실행은 계정 단위 제한으로 막히는지 확인한다. `sr_asset_exchange_logs.deposit_amount`에는 수수료 차감 후 최종 증가액이 저장되어야 하며, 성공 시 비율/소수 처리 방식/수수료 스냅샷이 저장되고 잔액 부족 같은 실행 실패 시 실패 사유가 관리자/회원 로그 화면에 표시되는지 확인한다. 자산 모듈을 비활성화하면 정책 카드와 회원 환전 후보에서 실행 불가로 처리되는지 확인한다.
적립금/예치금 모듈을 활성화한 환경에서는 `/account/rewards`의 출금 신청과 `/account/deposits`의 환불 신청이 로그인/CSRF를 요구하고, 최소 금액/최대 금액/필수 계좌 정보/대기 신청액을 제외한 신청 가능액을 서버에서 검증하는지 확인한다. `/admin/rewards/settings`에서 출금 신청 사용을 끄거나 `/admin/deposits/settings`에서 환불 신청 사용을 끄면 회원 화면의 신청 폼이 숨겨지고 직접 POST도 거부되어야 한다. `/admin/rewards/settings`와 `/admin/deposits/settings`에서 신청 사용을 켠 뒤 신청 대상을 선택하지 않으면 전체 로그인 회원에게 신청 폼이 표시되고, 회원 그룹을 지정하면 해당 그룹 소속 회원에게만 신청 폼이 표시되며 직접 POST도 같은 기준으로 검증되어야 한다. 신청 전 회원 그룹 자동 평가가 실행되어 최신 그룹 소속 기준으로 판정되는지 확인한다. 신청 후 같은 화면의 신청 내역에 대기 상태가 표시되고, 대기 상태 신청만 회원이 취소할 수 있어야 한다. 포인트·적립금·예치금 거래가 21건 이상이거나 출금·환불 신청이 21건 이상인 로컬 더미 계정에서는 다음 페이지로 이동해 전체 이력을 확인할 수 있고, 적립금·예치금 화면에서 신청 페이지를 이동해도 거래 페이지가 유지되는지 확인한다. `/admin/rewards/withdrawal-requests`와 `/admin/deposits/refund-requests`는 view/edit 권한을 확인하고, 상태 필터 그룹 버튼이 전체/대기/완료/거부/취소 목록을 전환하며 검색 조건/검색어가 회원, 계좌, 메모, 신청/거래 번호를 필터링하는지 확인한다. 처리 메모 없이는 개별 완료와 일괄처리가 저장되지 않아야 하며, 개별 거부는 별도 모달의 거부 사유 없이는 저장되지 않아야 한다. 일괄처리는 현재 필터와 검색 조건에 맞는 대기 신청만 대상으로 하며 100건 초과 시 서버에서 거부되어야 한다. 완료 처리는 각 원장에 `withdraw` 음수 거래를 만들고 신청 상태를 완료로 바꾸며, 거부와 회원 취소는 원장 거래를 만들지 않아야 한다. `/admin/rewards/transactions`의 회수는 대상 양수 거래의 남은 금액까지만 가능해야 하며, 잔액 조정 모달에는 회수 유형이 보이지 않고 회수 거래에는 환불 버튼이 없어야 한다. 개인정보 사본 제공에는 신청 계좌 정보와 처리 이력이 포함되어야 한다.
콘텐츠 업데이트 후 기존 `/admin/content/settings` 권한 보유 운영자에게 `/admin/content/asset-policy-sets`의 같은 액션 권한이 승계되는지 확인. 콘텐츠 회원 그룹별 설정 목록에서 이름, Key, 상태, 수정일 헤더 정렬이 허용 목록 기반으로 동작하는지 확인
콘텐츠 유료 열람, 콘텐츠 유료 다운로드, 커뮤니티 유료 게시글 열람 환경에서 쿠폰 모듈이 활성화되어 있고 대상 `access` 쿠폰이 있으면 확인 POST 이후 금액성 자산 차감보다 쿠폰 사용이 먼저 적용되는지 확인. 선택된 `access` 쿠폰이나 전액 할인 쿠폰이 있으면 쿠폰 redemption과 접근권만 남기고 자산 원장 거래와 settlement 차감 로그를 만들지 않아야 한다. 일부 할인 쿠폰은 할인액과 남은 결제액을 redemption snapshot에 남기고, 남은 금액만 자산 원장 거래와 settlement 차감 로그로 기록해야 한다. 같은 대상 재열람은 `dedupe_key` 기준으로 중복 사용되지 않아야 한다. 다른 dedupe로 다시 시도하더라도 이미 접근권이 있으면 쿠폰 사용 row와 사용 횟수를 남기지 않는 `already_entitled` no-op으로 끝나야 한다. 사용처가 pricing resolver를 제공하면 쿠폰 사용 row에 금액, 통화 또는 자산 단위, 정책 요약, 가격 시각, whitelisted target snapshot, 쿠폰 유형, 할인액, 남은 결제액이 저장되어야 한다. 쿠폰 사용 row를 만든 뒤 접근권 부여가 실패하면 사용 row와 `used_count`가 함께 rollback되어야 하며, 접근권 없이 쿠폰만 소모된 상태가 남으면 안 된다. 만료된 active 쿠폰 지급 건은 계정/관리자 목록 조회나 사용 처리 시 `expired` 상태로 전이되어야 한다. `/admin/coupons`의 `쿠폰 추가` 모달에서 쿠폰 키는 영문 소문자로 시작하는 key 형식만 저장되고, 혜택 유형은 열람/이용권, 정액 할인, 정률 할인을 선택할 수 있어야 한다. 정액 할인은 원 단위 금액, 정률 할인은 1-100 사이 할인율을 UI와 서버 검증에서 모두 요구해야 하며, 정액 할인 통화 선택 필드는 노출하지 않고 현재 저장 기본값은 KRW로 처리해야 한다. 대상 번호 검색 스택 모달로 사용처별 대상을 선택할 수 있으며, 검색 결과에는 사용처 capability와 현재 가격 또는 가격 조회 불가 사유가 표시되어야 한다. 중복 키와 숫자가 아닌 사용 가능 횟수는 서버 검증에서 거부되어야 한다. `/admin/coupons/campaigns`에서 무료 발급 캠페인을 만들고 수정한 뒤 `/coupons` 또는 `/coupons?campaign={campaign_key}`에서 로그인 회원이 직접 발급받을 수 있는지 확인한다. 같은 발급 의도 토큰 재시도는 기존 발급본으로 수렴해야 하고, 총 발급 한도와 회원당 발급 한도 초과는 서버에서 거부되어야 한다. 발급 로그가 있는 캠페인의 key/연결 쿠폰 변경과 이미 점유된 수보다 낮은 한도 변경은 서버에서 거부되어야 한다. 본문에 `/coupons?campaign=...` 단독 URL을 넣으면 embed manager가 쿠폰 임베드 카드로 렌더링하고, 카드 CTA는 같은 단건 쿠폰존 화면으로 이어져야 한다. 팝업레이어의 `쿠폰 CTA`에는 `popup_layer` 노출 위치가 켜진 공개 무료 캠페인만 저장되어야 하며, 유효 후보보다 최근에 생성된 비대상 캠페인이 300건을 넘어도 선택지에서 밀리면 안 된다. 현재 연결 캠페인의 공개 범위나 노출 위치가 바뀌었으면 편집 화면에서 기존 연결을 식별할 수 있어야 하며, 다시 저장할 때는 현재 서버 검증을 통과해야 한다. 공개 렌더링은 로그인 상태와 발급 가능 상태를 서버에서 다시 판정하고 팝업 닫기 쿠키가 claim row나 쿠폰 발급 상태를 바꾸지 않아야 한다. `/admin/coupons/campaigns/logs`의 최근 발급 로그는 회원/상태/발급 표면/캠페인·쿠폰 검색어 필터와 발급 표면, 상태, 발급본, 실패 사유를 보여주며, 예약 시간이 지난 점유 행은 `만료(미정리)`로 표시되어 운영자가 재고 점유로 오해하지 않아야 한다. `/admin/coupons`의 상태/사용처/검색어 필터, 헤더 정렬, 개별 `지급하기` 모달의 회원 검색, 전체, 그룹 지급이 동작하고, `/admin/coupons/issues`에서 상태/사용처/회원/쿠폰 검색어 필터, 헤더 정렬, 지급 취소, 사용 이력이 없는 유료 발급본의 사유 필수 발급 환불이 동작해야 한다. `/admin/coupons/redemptions`에서 상태/환급 정책/사용처/회원/쿠폰 검색어 필터, 헤더 정렬, 환급 가능 쿠폰 사용 내역의 사유 필수 수동 환불이 동작해야 한다. DB 업데이트 전 상태에서는 사용 내역 목록이 500 없이 열리고 환불 실행은 업데이트 필요 오류로 막혀야 한다. 쿠폰 사용 환불 후 해당 `dedupe_key`로 부여된 콘텐츠/커뮤니티 접근권이 회수되고, 같은 세션의 커뮤니티 첨부 직접 접근도 회수된 접근권을 우회하지 않아야 한다. 유료 발급본 환불 후 원 자산 차감이 복원되고 claim 점유 슬롯이 반환되어 새 nonce 재구매가 가능해야 한다. 알림 모듈이 활성화되어 있으면 환불 알림이 생성되어야 하며, 사용 전 지급건이 남은 쿠폰 정의를 `사용 중지`로 전환하면 해당 회원에게 `issue.definition_disabled` 알림이 생성되어야 한다.
/community 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `SR_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/board?key=free 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `SR_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/group?key={group_key} 응답이 500 없이 열리고 사용 상태인 게시판 그룹의 설명과 접근 가능한 게시판 목록이 표시되는지 확인한다. 커뮤니티 주 메뉴 슬롯을 사용 안 함으로 설정하면 사용 상태인 게시판 그룹이 주 메뉴 fallback으로 표시되고, 그룹이 하나도 없을 때만 게시판 fallback이 표시되어야 한다.
/community/board?key=free&category={category_key} 응답이 500 없이 열리고 게시판 카테고리 필터가 적용되는지 확인. `category=all`, 없는 key, 비활성 카테고리는 기본 목록으로 조용히 떨어지지 않고 안내와 `noindex, follow`가 적용되는지 확인한다. 게시글 작성/수정에서 카테고리 필수 게시판은 서버 검증으로 빈 카테고리를 거부해야 하며, 필수 선택 시 카테고리 사용 설정도 함께 켜져야 한다. 카테고리 사용을 끈 게시판은 공개 목록 필터와 작성/수정 선택지가 표시되지 않고 POST된 카테고리 값도 저장되지 않아야 한다. 비활성 카테고리는 기존 글 표시에서는 텍스트로만 보여야 한다.
/community 공개 홈, 게시판 목록, 카테고리 목록, 게시글 상세의 canonical과 robots 메타가 커뮤니티 공개 정책과 맞는지 확인한다. 게시판 SEO/OG 설정은 `/admin/community/boards`에서 저장한 `seo_title`, `seo_description`, `og_image_url` 값을 사용하고, 제목/설명은 검색 메타와 OG 메타에 함께 적용되어야 한다. 게시판 OG 이미지가 비어 있으면 사이트 기본 OG 이미지가 fallback으로 적용되고, 검색 결과와 잘못된 카테고리는 `noindex, follow`여야 한다. 게시글 SEO/OG 값은 저장된 게시글 SEO/OG 컬럼이 있으면 우선하고, 일반 작성/수정 화면에서 직접 POST한 SEO/OG 값은 저장되지 않아야 한다. 저장값이 비어 있으면 제목과 공개 가능한 본문 요약으로 fallback하고, 유료 열람 확인 전 화면은 본문 요약을 description/OG에 노출하지 않아야 한다. 게시글 작성 시 이미지 첨부가 있으면 게시글 OG 이미지 후보가 되고, 작성자 또는 `/admin/community/posts` edit/delete 권한자는 CSRF가 있는 POST로 지정 OG 이미지를 제거할 수 있어야 한다. 관리자 게시글/댓글 목록의 게시글 확인 링크는 `preview=admin`으로 열려 조회수 증가와 유료 열람 처리를 실행하지 않아야 한다. 커뮤니티 sitemap에는 공개 접근 가능한 게시판과 게시글만 포함되어야 한다.
/community/series는 로그인 회원만 접근할 수 있고, 회원이 만든 시리즈는 같은 게시판 글 작성/수정 화면에서 선택할 수 있어야 한다. 내 시리즈가 20건을 넘으면 다음 페이지와 마지막 부분 페이지까지 이동할 수 있어야 하며, 사용 중지 게시판이나 시리즈 사용을 끈 게시판의 항목은 목록 count와 페이지 행에서 모두 빠져야 한다. 회원 시리즈 설명과 관리자 운영 메모가 서버 제한 길이를 넘으면 빈 값으로 저장하지 않고 오류를 표시해야 한다. 글 작성/수정 중 새 시리즈 제목을 제출하면 시리즈와 현재 글 항목이 함께 생성되고, 시리즈 정렬 순서는 서버에서 0 이상 1000000 이하 숫자로 검증되는지 확인한다. 공개 게시글 본문 다음에 active 시리즈 내비게이션이 표시되어야 한다. member 시리즈는 비로그인에게 숨겨지고 private 시리즈는 소유자에게만 표시되어야 한다. 시리즈 내비게이션에서 시리즈 스크랩 추가/해제를 수행하면 `/community/scraps`의 시리즈 스크랩 섹션에 표시되고, 게시글 스크랩과 자동으로 서로 생성되지 않아야 한다. 시리즈를 hidden, archived, deleted로 바꾸거나 게시판 읽기 권한을 제거하면 내 스크랩 목록에서 열람 불가 시리즈로 표시하되 해제는 가능해야 한다. 개인정보 사본 제공에는 게시글 스크랩과 별도로 시리즈 스크랩의 `series_id`, 생성일이 포함되어야 한다. `/admin/community/series`에서 상태/공개 범위/검색어 필터와 제목, 게시판, 소유자, 상태, 공개 범위, 글 수, 수정일 헤더 정렬이 허용 목록 기반으로 동작하고, 상태와 공개 범위가 한국어 라벨로 표시되는지 확인한다. 상태를 archived/deleted로 바꾸면 연결 항목이 공개 출력에서 제거되는지 확인. 코드 배포 후 DB 업데이트 적용 전에는 커뮤니티 시리즈 관리, 공개 게시글, 개인정보 내보내기가 시리즈 테이블 누락으로 500을 내지 않아야 한다.
/message/write 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/write?key=free 비로그인 접근이 로그인 흐름으로 막히는지 확인
커뮤니티 자산 기능을 활성화한 환경에서는 글/댓글 적립, 글/댓글 작성 차감, 게시글 유료 열람, 첨부 다운로드 차감이 비로그인/잔액 부족/중복 처리 정책에 맞게 동작하는지 확인. 게시글 유료 열람과 첨부 다운로드의 최초 1회와 반복 과금은 GET 접근만으로 차감되지 않고 확인 POST 후 처리되어야 하며, 이미 접근권이 있는 once 대상은 재확인 없이 열려야 한다. 회원 그룹별 적용을 저장하고 전역/게시판에서 여러 적용을 선택한 경우 최종 금액과 `group_policy_snapshot_json`이 로그에 남고, `/admin/community/attachment-downloads`의 유료 다운로드 내역은 연결 차감 로그의 자산 단위 차감량, 기준 settlement 금액/통화, `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`을 사람이 읽을 수 있는 요약으로 보여야 한다. 최종 금액 0이면 원장 거래 없이 유료 열람/첨부 다운로드 접근권 또는 처리 로그가 `completed` 상태로 남는지 확인. 회원 그룹별 적용 선택지는 선택한 자산에 맞는 정책만 보여야 하며, 선택 자산을 바꾸면 맞지 않는 적용 뱃지가 제거되어야 한다. 같은 회원 그룹에 최소 레벨별 정책을 저장하면 레벨 충족 여부에 따라 다른 정책이 적용되고, 같은 우선순위에서는 충족한 정책 중 최소 레벨이 높은 행이 먼저 적용되며, 스냅샷에 매칭 최소 레벨과 현재 레벨이 남는지 확인. 유료 첨부는 S3 서명 URL 생성 또는 로컬 파일 경로 확인이 실패하면 자산 차감 없이 오류로 끝나야 하며, 전달 준비가 끝난 뒤에만 열람/다운로드 차감과 접근권 처리를 진행해야 한다. 최초 설치 후 커뮤니티 환경설정과 새 게시판의 자산 설정은 사용하지 않음이며 포인트 등 특정 자산이 미리 선택되어 있지 않아야 한다. 복합 자산 차감 설정은 선택 자산별 금액이 각각 차감되는지 확인. `/admin/community/settings`에서 복합 자산 결제를 끄면 전역/게시판의 유료 게시글 열람/첨부 다운로드 저장에서 여러 포인트/금액 항목 선택이 서버 검증으로 거부되고, 일부 할인 쿠폰 후 남은 금액이 두 항목 이상으로 배분되는 public 결제는 쿠폰 사용과 자산 차감, payment-unit row를 남기지 않고 실패해야 한다. 첨부 URL 직접 접근도 게시글 유료 열람 정책을 우회하지 않는지 함께 확인
커뮤니티 업데이트 후 기존 `/admin/community/settings` 권한 보유 운영자에게 `/admin/community/asset-policy-sets`의 같은 액션 권한이 승계되는지 확인. 커뮤니티 회원 그룹별 설정 목록에서 이름, Key, 상태, 수정일 헤더 정렬이 허용 목록 기반으로 동작하는지 확인
`/admin/community/boards` 게시판 편집 화면에서 회원 검색 모달로 회원을 선택해 게시판 운영권한을 부여/회수할 수 있고, 서버가 `view_manage`, `write_notice`, `hide_post`, `delete_post`, `hide_comment`, `delete_comment`, `remove_post_og_image` 외 권한 key와 빈 권한 제출을 거부하는지 확인한다. 이 권한은 관리자 모드 권한이 아니라 공개 사용자 모드에서 해당 게시판을 운영하는 권한이다. `write_notice` 권한 보유자는 해당 게시판에서 공지사항을 작성할 수 있고 권한 회수 후 `is_notice=1` 저장이 거부되어야 한다. `hide_post`/`delete_post` 권한 보유자는 해당 게시판의 게시글만 숨김/삭제할 수 있고 다른 게시판 게시글은 처리할 수 없어야 하며, `hide_comment`/`delete_comment` 권한 보유자는 해당 게시판 댓글만 숨김/삭제할 수 있어야 한다. 게시판 운영권한만으로 관리자 게시글 목록이나 게시글 본문 수정 화면/POST가 허용되지 않아야 한다. `remove_post_og_image` 권한 보유자는 해당 게시판 게시글의 지정 OG 이미지를 제거할 수 있고, 회수 후 즉시 숨김/삭제와 OG 이미지 제거가 거부되어야 한다. 권한 부여/회수, 게시판 운영권한으로 수행한 게시글·댓글 숨김/삭제, 게시판 운영권한 OG 이미지 제거는 감사 로그에 남아야 한다.
`/admin/community/board-groups`와 `/admin/community/boards`에서 게시글 수정/삭제 잠금 댓글 수, 게시글/댓글 본문 최소·최대 길이, 목록 본문 요약 사용/길이, 목록 페이지당 글 수, 목록 기본 정렬을 저장하고 게시판의 그룹/전체 적용 범위가 의도한 대상에만 반영되는지 확인한다. 조작된 POST로 최소 길이가 최대 길이보다 큰 값, 허용되지 않은 정렬 key, 범위를 벗어난 페이지당 글 수를 제출하면 서버가 저장하지 않아야 한다. 공개 글 작성/수정과 댓글 작성/수정은 본문 길이 제한을 서버에서 거부해야 하며, 댓글 수가 잠금 기준 이상인 게시글은 작성자 수정/삭제 POST와 비회원 비밀번호 수정/삭제 흐름 모두 차단되어야 한다. 기본 스킨 목록은 게시판 설정의 페이지당 글 수, `latest`/`oldest`/`views`/`comments` 정렬, 본문 요약 표시 여부와 길이를 따라야 한다.
로고 매니저에서 용도별 상시 로고를 등록하면 사용자화면 PC/모바일 로고, 관리자 사이드바, 파비콘 용도에 반영되는지 확인. 공개 레이아웃 로고는 사용처의 `전체` 상단/하단과 개별 레이아웃 제공 모듈 상단/하단을 저장할 수 있어야 하며, 같은 슬롯에서 개별 모듈 지정이 `전체` 지정보다 우선하고 같은 우선순위에서는 전체 기간이 더 짧은 로고가 우선인지 확인한다. 사용자화면 PC 로고만 등록하면 모바일 헤더도 PC 로고를 사용하고, 모바일 로고만 등록하면 PC 헤더도 모바일 로고를 사용해야 한다. PC/모바일 로고가 모두 있으면 모바일 폭에서 모바일 로고가 우선 적용되어야 한다. 관리자 사이드바 로고를 등록하면 사이트명 텍스트 없이 로고만 표시되고, 접힌 상태에서는 앱아이콘이 있으면 앱아이콘을 먼저 표시하고, 앱아이콘이 없으면 같은 사이드바 로고가 검정 박스 없이 원본 비율로 표시되어야 한다. 같은 용도에 상시 로고와 현재 기간 로고가 함께 있으면 기간 로고가 우선이고, 현재 기간 로고가 여러 개이면 전체 기간이 더 짧은 로고가 우선인지 확인. 기간이 끝난 뒤에는 상시 로고로 되돌아가는지 확인. 활성 모듈이 `logo-positions.php`를 제공하는 경우 해당 모듈 후보가 로고 배치 추가 화면의 로고 용도 선택지에 표시되는지 확인. 기존 로고 배치를 수정할 때 파일을 선택하지 않으면 기존 이미지가 유지되고, 새 파일을 선택하면 파일 참조가 교체되며 감사 로그에 `logo_manager.logo.updated`와 이미지 교체 여부가 남는지 확인. 관리 버튼은 파비콘 용도에서 아이콘 세트가 맨 앞에 표시되고, 삭제 버튼은 로고 배치와 생성된 아이콘 세트 행 및 저장소 파일 정리를 실행하며 `logo_manager.logo.deleted` 감사 로그를 남기는지 확인한다. `public.app_icon` 용도에서는 사용자 화면 심볼 스위치가 활성화되고 저장값이 심볼 helper에서 반환되는지, 사용자화면 PC/모바일 로고가 모두 없을 때 기본 공개/콘텐츠/커뮤니티/퀴즈 레이아웃의 브랜드 영역에 앱아이콘과 사이트명이 함께 표시되는지, 다른 용도에서는 스위치가 꺼지고 조작된 POST 값도 저장되지 않는지 확인. 심볼 스위치는 파비콘 head link 조건과 별도이므로 심볼을 켠 앱아이콘은 favicon head link로 출력되지 않고, 심볼을 끈 활성 파비콘도 head link로 출력되어야 한다. 파비콘 등록 시 `앱아이콘으로도 사용`, 앱아이콘 등록 시 `파비콘으로도 사용` 스위치를 켜면 별도 저장본의 다른 용도 로고가 함께 생성되어야 한다. 활성 파비콘 1개를 중지하면 관리자/공개/콘텐츠/커뮤니티/퀴즈 head에서 해당 URL의 `icon`/`apple-touch-icon` 링크가 제거되고, 다른 활성 후보가 없으면 `icon`과 `apple-touch-icon` link가 모두 출력되지 않아야 하며 `/favicon.ico`는 no-store 404로 응답해야 한다. 완전 삭제 POST는 `Clear-Site-Data: "cache"`로 이전 투명 아이콘 캐시 정리를 요청해야 한다. 같은 용도의 다른 활성 후보가 있으면 정렬 기준대로 그 후보가 적용될 수 있다. 로고 배치 목록은 `현재 적용`과 현재 시각에 적용 가능한 `적용 후보`를 구분해 보여야 하며, 기간이 지났거나 아직 시작 전인 사용 상태 로고에는 적용 후보 배지를 붙이지 않아야 한다. 탭 아이콘은 브라우저 캐시나 루트 `/favicon.ico` fallback으로 늦게 바뀔 수 있으므로 smoke 판정은 HTML head 출력과 `/favicon.ico` 응답 기준으로 한다. 로컬 PNG/JPEG/WebP 파비콘 원본에서는 아이콘 세트 모달의 생성 크기 스위치가 줄바꿈 목록으로 표시되고 전체 선택 스위치가 개별 크기 스위치를 모두 활성화/비활성화하는지 확인한다. 16/32/48/180/192/512 PNG variant를 생성하며, 생성 후 즉시 사용을 선택하면 공개 head에 사이즈별 `icon`/`apple-touch-icon` 링크가 출력되는지 확인. SVG 또는 S3 원본은 생성 불가 안내가 표시되고 기존 단일 favicon fallback이 유지되어야 한다
커뮤니티 게시글 또는 게시판 대상 쿠폰이 있으면 게시글 유료 열람과 첨부 직접 접근에서 금액성 자산 차감보다 쿠폰 사용이 먼저 적용되는지 확인. `once` 정책에서는 같은 세션/대상 중복 차감과 중복 쿠폰 사용이 없어야 한다
커뮤니티 환경설정 저장은 레벨 사용, 자동 재계산, 최대 레벨, 레벨 점수, 커뮤니티 공개 레이아웃, 커뮤니티 공개 테마, 시리즈 기능 사용, 게시글 에디터, 본문 URL 자동 링크, 개인정보 수집 및 이용동의 기본값, 복합 자산 차감 선택과 금액을 함께 바꿔 `sr_module_settings` 값이 갱신되는지 확인한다. 시리즈 기능을 끄면 커뮤니티 시리즈 생성/연결/관리/스크랩/공개 내비게이션을 사용할 수 없고, 커뮤니티 메인 화면에서도 시리즈 섹션이 보이지 않아야 한다. 레벨 점수 입력은 자동 재계산을 사용할 때만 보이는지 확인한다. 개인정보 동의 사용 시 제목, 본문, 버전, 적용 대상 하나 이상이 서버에서 필수로 검증되어야 하며, 동의 후 제출한 게시글/댓글은 관리자 목록에서 동의 증적 수와 최근 동의 시각이 표시되어야 한다. 최대 레벨을 늘릴 때는 1차 안내 모달과 2차 확인 문구 입력을 거친 뒤 부족한 레벨 행이 기본 최소 점수로 자동 추가되는지 확인한다. `/admin/community/levels`는 레벨 미사용 상태에서 환경설정 링크가 있는 안내를 표시해야 하며, 재계산 모달은 부하 안내 확인 단계와 확인 문구 입력 단계를 거친 뒤 배치 재계산 진행상태가 표시되는지 확인한다. 복합 자산 차감에서 포인트/적립금/예치금을 모두 해제하고 저장하면 다시 체크되지 않아야 한다. 저장 실패가 발생하면 화면 검증 메시지 또는 `storage/logs/error.log`에 원인이 남아야 한다
쪽지 환경설정 저장은 `/admin/message/settings`에서 쪽지 사용 여부, 발신 정책, 수신 정책, 발신/수신 회원 그룹, 회원별 수신 설정 사용 여부와 기본 수신 허용값, 발송 제한 시간/건수를 저장하는지 확인한다. 일반 회원은 본인 계정이 현재 수신 가능한 상태여야 쪽지를 발신할 수 있어야 하며, 회원별 수신 설정을 끄거나 수신 그룹 정책에서 제외되면 쪽지 쓰기가 거부되어야 한다.
커뮤니티 자산 관리자 설정을 바꾼 환경에서는 커뮤니티 전역과 게시판 수정 화면의 `자산 변경 이력` 링크에서 대상별 변경 로그가 보이는지 확인. 게시판 그룹에서 새 게시판을 추가해도 게시판 그룹 설정이 입력폼에 복사되지 않고, 게시판 그룹 관리 화면은 기본 정보만 제공하는지 확인. 게시판 수정 화면에서 `그룹`/`전체` 적용을 선택하면 현재 편집값이 대상 게시판에 한 번 복사된다. 게시판을 다른 그룹으로 옮기더라도 저장된 게시판 자산 설정은 현재 게시판 값으로 유지되어야 한다
CKEditor 플러그인을 활성화한 환경에서는 콘텐츠 본문, 커뮤니티 게시글, 팝업레이어 본문, 관리자 본문 textarea가 설정에 따라 에디터로 강화되는지 확인한다. 콘텐츠와 팝업레이어처럼 본문 형식을 저장하는 모듈은 CKEditor 선택만으로 `body_format=html` 저장 경로를 타야 하며, 프론트 hidden field만 신뢰하지 않아야 한다. 커뮤니티 게시글은 `sr_community_posts.body_format`을 저장하지 않고 커뮤니티 환경설정 또는 게시판 자체 에디터 설정으로 출력 형식을 결정해야 하며, 게시판 그룹 에디터 설정은 참조하지 않아야 한다. 콘텐츠 생성/수정 화면은 기본 `textarea`와 콘텐츠별 명시 에디터 선택을 저장하고, 라디오 변경 시 textarea, 직접 HTML, CKEditor, Markdown 입력 모드와 도움말이 즉시 전환되는지 확인한다. 다시 열었을 때는 해당 콘텐츠의 선택값으로 본문 textarea가 강화되어야 한다. 직접 HTML 입력 모드를 선택한 경우에는 플러그인 없이도 HTML 저장/출력 과정에서 허용 태그와 속성만 남아야 하며 CKEditor 전용 본문 reset stylesheet를 호출하지 않아야 한다. Markdown 입력 모드는 공개 출력에서 제한된 Markdown 렌더러를 거쳐 HTML로 표시되는지 확인한다. 직접 호스팅 모드는 `modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js`와 `ckeditor5.css`를 로드해야 한다. CKEditor 설정 화면의 기본 툴바 구성은 명시 preset이 없는 CKEditor textarea의 fallback으로 적용되고, 콘텐츠 환경설정의 툴바 구성은 콘텐츠 본문 입력 화면에, 커뮤니티 환경설정의 툴바 구성은 커뮤니티 게시글 작성/수정 화면에 적용되는지 확인한다. CKEditor asset 로딩을 실패시킨 경우에는 일반 textarea 제출이 유지되어야 한다. 악성 HTML은 저장/출력 과정에서 허용 태그와 속성만 남아야 한다. 콘텐츠/커뮤니티/팝업레이어 복사 경로도 기존 HTML을 그대로 신뢰하지 않고 새 레코드 저장 전과 본문 이미지/임베드 참조 재작성 후 최종 본문을 다시 정화해야 한다. HTML Purifier가 배치된 환경에서는 Purifier 경로와 내부 fallback canonicalizer가 함께 payload fixture를 통과해야 하며, Purifier가 없는 환경에서는 내부 fallback sanitizer fixture가 통과해야 한다. 콘텐츠/커뮤니티/팝업레이어 본문 이미지 업로드는 권한, CSRF, upload token을 요구하고, upload token은 허용 길이를 넘으면 잘라서 검증하지 않고 거부해야 한다. 저장 전 temporary 이미지는 업로드 권한이 있는 현재 사용자에게 보이며, 저장 후 정화된 HTML에 남은 프록시 URL만 소유 모듈의 로컬 경로로 이동되어야 한다. 각 업로드 action은 만료된 임시 본문 이미지를 소량 opportunistic cleanup으로 정리해야 한다. 유료/비공개 콘텐츠와 권한 제한 게시글의 본문 이미지 프록시는 소유 모듈 접근 정책을 우회하지 않아야 하며, 본문에서 제거되거나 레코드가 삭제된 이미지는 소유 모듈 저장 경로 기준으로 정리되어야 한다. 관리자 설정형 rich textarea에는 소유 모듈이 subject key와 삭제 정책을 명시한 경우에만 upload endpoint가 붙는지 확인한다.

Markdown Editor 플러그인은 `php .tools/bin/check-markdown-editor-runtime.php`로 renderer 계약, full/inline/plain 렌더링, profile hash, style asset URL, `github-markdown-css` 기반 기본 stylesheet, `.markdown-editor-body` 안에 선언된 모든 `--md-*` 색상 토큰, 이전 `--sr-*` 저장값의 `--md-*` 변환, 관리자 미리보기와 공개 출력 CSS 및 light/dark 토큰 값 일치, 시각 컨트롤과 실제 CSS property의 `sr-control` 양방향 연결, scoped stylesheet 검증, Markdown 비활성 저장 차단 fixture를 확인한다. 설치 DB가 있는 로컬/staging에서는 플러그인 활성 상태에서 콘텐츠·커뮤니티·팝업레이어·약관 문서의 Markdown 저장과 공개 출력이 같은 renderer stylesheet를 로드하는지 확인한다. 공개 화면에서 `--md-text`, `--md-info`, 보조 글자색, 테두리, 표면 및 상태 토큰이 `.markdown-editor-body` 밖에 선언되지 않고, light/dark 공통 `--color-*` 값에 따라 계산되는지 확인한다.

`/admin/markdown-editor/settings`에서는 원문과 렌더링 결과 오른쪽에 304px 속성 인스펙터가 표시되어야 한다. 데스크톱에서는 원문·렌더링 작업 영역과 인스펙터가 같은 grid 행에서 같은 높이로 표시되고 각 영역 내부가 스크롤되어야 하며, 1180px 이하에서는 인스펙터가 작업대 아래로 이동해 자동 높이를 사용해야 한다. 렌더링된 제목·문단·링크·목록·인용·코드·표·구분선을 클릭하면 인스펙터가 같은 대상으로 바뀌고, 선택 요소 제목과 초기화 아이콘 및 `Margin`, `Padding`, `Stroke`, `Typography`, `Fill` 등의 접이식 섹션을 표시해야 한다. 섹션은 Figma 계열 패널처럼 경계선으로 구분되고 입력은 2열 그리드를 우선한다. 속성 값을 바꾸면 CSS 원문과 실제 렌더링 결과가 동기화되고 기본 모드라면 커스텀 모드로 전환되어야 한다. 갱신 중에는 상태 문구가 렌더링 결과 전체를 덮는 반투명 mask 가운데 표시되고, 완료 후 mask가 사라져야 하며 오류도 같은 mask에서 danger 색으로 표시되어야 한다. 기존 가로 컨텍스트 툴바와 popover 속성 메뉴는 표시하지 않는다.

원문·렌더링 패널 헤더의 펼치기는 각각 단독 보기와 분할 복원을 제공하고 icon/aria-label/title도 상태에 맞게 바뀌어야 한다. 관리자 다크 모드에서는 원문, 스타일 인스펙터, CSS 모달, 하단 제출 영역이 `.markdown-editor-settings-form` 안에만 선언된 의미 토큰을 통해 관리자 색상 모드를 따라야 하며, 이 토큰이 다른 관리자 화면으로 퍼지지 않아야 한다. 렌더링 결과는 설정 화면 진입 시 현재 관리자 `data-theme`의 실제 light/dark 상태로 시작하고, 이후에는 자기 헤더의 아이콘 버튼으로 독립 전환되어야 한다. 렌더링 결과 헤더에는 CSS, light/dark, 펼치기 아이콘이 함께 표시되고 상태가 바뀔 때 dark/light 아이콘과 aria-label/title이 함께 바뀌어야 한다. CSS 아이콘은 전체 CSS textarea와 닫기 버튼만 있는 전체화면 모달을 열어야 하고 전체 초기화 버튼이나 설명·상태 문구를 표시하지 않아야 한다. 하단 스티키 제출 영역에는 GitHub 참고 링크를 표시하지 않고, 저장 버튼 앞에 `기본`·`커스텀` 라디오 토글을 표시해야 한다. H1-H6 기본 크기는 각각 `32px`, `26px`, `24px`, `20px`, `18px`, `16px`여야 한다.

표·체크 목록·코드 블록 문법은 선택 옵션 없이 항상 렌더링되어야 한다. 표 fixture는 헤더·구분선·복수 본문 행을 하나의 `<table>`로 묶고 구분선 행을 출력하지 않으며 콜론 정렬을 각 `<th>`·`<td>`에 반영하는지 확인한다. 공개 콘텐츠·커뮤니티 화면에서는 마크다운 제목 색이 화면 모듈 규칙에 덮이지 않고, 일반 본문·제목은 사이트 기본 글꼴을 상속해야 한다. `기본`을 저장하면 사용자 CSS를 보존하면서 번들 원본이 공개 출력되어야 하며, 범위를 벗어난 selector, 외부 URL, at-rule, `</style` 종료 문자열은 거부되어야 한다.

설치 DB 없이 브라우저 asset 로딩과 upload adapter request contract만 확인할 때는 로컬 dev-router를 띄운 뒤 다음 Playwright smoke를 실행한다. 이 명령은 `.tools/browser-qa/tests/ckeditor-browser-smoke.spec.js`를 실행하며, self-hosted CKEditor JS/CSS와 산란 loader를 실제 브라우저에서 로드하고, 초기화 성공 시 `body_format=html` hidden input이 붙는지와 번들 로딩 실패 시 textarea fallback이 유지되는지를 확인한다. 또한 mock upload endpoint로 CKEditor upload adapter가 이미지 field, `csrf_token`, `upload_token`, 커뮤니티 개인정보 동의 field를 multipart 요청에 포함하고, 서버 성공/오류 JSON을 올바르게 처리하는지 확인한다. 실제 업로드 action 권한, 저장 HTML sanitizer, 임시/저장 후 본문 이미지 접근은 설치 DB가 있는 CKEditor smoke에서 별도로 확인한다.

최초 실행에서는 `npm ci --prefix .tools/browser-qa`와 `.tools/browser-qa/node_modules/.bin/playwright install chromium`으로 잠금 파일 기준 패키지와 번들 Chromium을 준비한다. 기본 프로젝트는 이 번들 브라우저를 사용한다. 시스템 Chrome과 별도로 비교할 때만 `SR_BROWSER_QA_CHROMIUM_CHANNEL=chrome`을 지정한다.

```sh
SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8080 \
  npm --prefix .tools/browser-qa run test:ckeditor
```

설치 DB와 disposable 관리자 계정이 준비된 로컬/staging에서는 다음 smoke로 콘텐츠 관리자 본문 textarea의 CKEditor upload 속성, 서버 업로드 action, 저장 HTML sanitizer, 임시 이미지 비로그인 차단, 저장 후 공개 이미지 접근, 저장 후 최종 본문 이미지 URL을 확인한다. 저장 HTML sanitizer 판정은 공개 레이아웃의 정상 script asset이 아니라 콘텐츠 본문 영역에 차단 payload가 남는지를 기준으로 한다. 같은 하니스는 유료 공개 콘텐츠도 저장해 접근권 부여 전 비로그인/로그인 세션의 본문 이미지 접근이 차단되고, 테스트 접근권 부여 뒤 본문 이미지가 열리는지 확인한다. 별도 draft 콘텐츠도 저장해 관리자 미리보기에서는 본문 이미지가 보이고, 비로그인 사용자는 draft 페이지와 저장 후 본문 이미지에 접근할 수 없는지 확인한다. 이 스크립트는 공개/유료/draft 콘텐츠와 본문 이미지를 만들고, 설치 DB를 읽을 수 있는 환경에서는 테스트 접근권을 부여하므로 운영 DB에서 실행하지 않는다. 하니스 자체는 `check-installed-gate-status.php`의 mock HTTP fixture로 로그인, 업로드, 저장, 이미지 접근 확인 흐름을 점검한다. `check-ckeditor-assets.php`는 SQLite fixture로 공개 무료 콘텐츠 본문 이미지 허용, 유료 콘텐츠 비로그인 차단, 비공개 콘텐츠 관리자 접근 분기를 확인한다.

```sh
SR_SMOKE_ALLOW_MUTATION=1 \
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_ADMIN_IDENTIFIER=admin \
SR_SMOKE_ADMIN_PASSWORD=12341234 \
php .tools/bin/smoke-ckeditor-upload-save.php
```
리액션 모듈이 활성화된 설치 DB에서는 `/admin/reactions`의 `리액션 정의`, `Preset 관리`, `레코드 점검` 하위 화면이 분리되어 열리는지 확인한다. 정의 화면에서는 새 리액션을 이모지, Material icon key, JPG/PNG/WebP 이미지 업로드 방식으로 각각 저장하고 공개 위젯에서 같은 아이콘이 표시되는지 확인한다. 이미지 아이콘은 512px 이하, 1MB 이하 파일만 허용되어야 하며, 허용되지 않는 파일이나 중복 key처럼 정의 저장 검증이 실패하는 요청 뒤에는 새 업로드 파일이 남지 않아야 한다. Preset 관리에서는 공개 노출 key 수가 기본 6개와 hard safety cap 12개 기준을 따르는지, 콘텐츠 환경설정/콘텐츠 그룹/개별 콘텐츠, 커뮤니티 환경설정/게시판 그룹/게시판, 퀴즈·설문 환경설정/개별 설정에서 선택한 preset 상속 순서가 실제 공개 버튼에 반영되는지 확인한다. 레코드 점검에서는 회원/대상/key 필터가 목록에 반영되고, 사용 중지 key의 기존 레코드를 보관, 삭제, 병합할 때 영향 수 확인과 감사 로그 metadata가 남는지 확인한다. 자기 글/댓글/콘텐츠/퀴즈/설문에는 신규 리액션 write가 차단되고, 알림도 생성되지 않아야 한다.
퀴즈와 설문 관리자 생성/수정 화면에서는 대표/OG 이미지 URL 입력과 JPG/PNG/WebP 업로드를 저장할 수 있고, 공개 목록과 상세 화면 상단에 이미지가 표시되며 상세 화면의 공유 이미지 metadata에도 반영되는지 확인한다. 이미지 URL은 안전한 내부 경로 또는 HTTP(S) URL만 저장되어야 하며, 값이 비어 있으면 공유 metadata는 사이트 기본 OG 이미지를 사용해야 한다. 업로드 이미지는 각 모듈의 `/quiz/cover-image`, `/survey/cover-image` 프록시로 열려야 한다.
보유 쿠폰이 21건 이상인 로컬 더미 계정에서는 `/account/coupons`의 다음 페이지와 마지막 부분 페이지까지 이동할 수 있고, 미래 시작 활성 쿠폰도 보유 목록에는 남아야 한다. 더 최신인 무관 쿠폰을 300건 이상 가진 계정에서도 특정 콘텐츠·커뮤니티 대상에 맞는 오래된 쿠폰이 결제 후보에서 누락되지 않는지 확인한다.

알림 모듈이 활성화된 환경에서는 포인트/적립금/예치금 거래와 쿠폰 지급/사용/상태 변경/사용 환불/발급 환불 후 회원 대상 알림이 생성되는지 확인. 회원 보안 이벤트는 `/admin/member-notification-templates`의 템플릿과 채널 설정을 따라야 하며, 이메일 인증 완료, 비밀번호 변경/재설정 완료, 2차 인증 설정/복구코드 재발급/해제, OAuth 연결/해제 성공 뒤 `member.security.*` 알림이 생성되는지 확인한다. 같은 화면에서는 `member.email_verification`, `member.password_reset`, `member.login_mfa_email_code` 메일 템플릿이 함께 보이고 사용자 수정 저장과 기본값 복원이 동작해야 한다. `/admin/delivery-templates`에서는 회원 메일 계약과 `policy_documents.version_notice` 계약이 보이고 사용자 수정 저장과 기본값 복원이 동작해야 한다. 쿠폰 알림은 `/admin/coupons/notification-templates`의 케이스별 사용 여부와 채널 설정을 따라야 하며, 켜진 케이스에서 이메일 채널을 선택하면 회원 이메일로 delivery가 queue되어야 한다. 쿠폰 알림/메일 관리 화면은 이메일 채널이 쿠폰 지급이나 사용 중지 처리에서 대량 발송될 수 있음을 페이지 안내로 알려야 하고, 쿠폰 지급·사용 중지·지급 취소·발급 환불·사용 환불 실행 위치도 해당 이메일 채널이 켜져 있으면 운영자에게 주의를 보여야 한다. 사용 중지 회수 안내는 알림 이벤트 템플릿 `coupon/issue.definition_disabled`를 사용한다. 외부 푸시는 알림 모듈 provider와 회원별 수신처가 준비된 경우에만 queue되어야 한다. 알림 모듈을 비활성화하거나 설치하지 않은 환경에서는 각 모듈의 알림/메일 관리 메뉴가 관리자 메뉴에서 숨겨지고, 해당 URL 직접 접근은 관리 화면을 렌더링하지 않아야 하며, 쿠폰 사용 중지와 같은 원 업무는 알림 실패 없이 성공해야 한다. 포인트 환경설정에서 유효기간을 1일 이상으로 저장한 뒤 `grant` 지급 거래를 만들면 거래 목록과 회원 화면에 유효기간이 표시되고, 유효기간 입력 옆에 `일` 단위가 표시되는지 확인한다. 사용/차감 거래가 가장 먼저 만료되는 지급분의 만료 가능 잔여량을 줄이고 `sr_point_expiration_consumptions`에 소비 매핑을 남기는지도 확인한다. 적립금 환경설정에서 유효기간을 1일 이상으로 저장한 뒤 `grant` 지급 거래를 만들면 거래 목록과 회원 화면에 유효기간이 표시되고, 사용/차감 거래가 `sr_reward_expiration_consumptions`에 소비 매핑을 남기는지도 확인한다. 포인트 환불 모달의 환불 유효기간 기본값은 `환불 참조 원거래의 유효기간`이어야 하며, 환불 건마다 `환불 시점부터 유효기간 계산`으로 바꿀 수 있어야 한다. 사용/차감 거래를 환불할 때 기본값은 소비 매핑의 원 지급 유효기간을 따라야 하고, 여러 유효기간 지급분이 복원되면 환불 거래가 유효기간별로 나뉘어야 하며, 이미 환불한 수량과 합쳐 원거래 수량을 넘으면 서버에서 거부되어야 한다. 포인트/적립금/예치금 조정 모달에는 환불 거래 유형이 일반 선택지로 보이지 않아야 하고, 참조 없는 환불 POST, 양수 원거래 환불 POST, 원거래 잔여 환불 가능액을 넘는 환불 POST는 서버에서 거부되어야 한다. 콘텐츠 파일 다운로드 수동 환불 모달도 포인트 환불 유효기간 기준을 환불 건마다 선택할 수 있어야 한다. 포인트의 기한이 지난 지급분은 `/admin/points/settings`의 `수동 만료 실행` 버튼, `php .tools/bin/expire-points.php` 실행, 또는 다음 포인트 거래 전에 `expire` 거래로 차감되어야 하며, 적립금의 기한이 지난 지급분은 다음 적립금 거래 전 또는 회원 적립금 화면 조회 시 `expire` 거래로 차감되어야 하며, 기존 업데이트 전 거래처럼 `expires_at`이 없는 거래는 자동 만료되지 않아야 한다. 적립금 관리자 조정에서 지급 원거래에는 환불 버튼이 보이지 않고 회수 버튼만 제공되어야 한다. 예치금 지급/예치 원거래에는 환불 버튼이 보이지 않아야 하며, 회원에게 예치금을 내보내는 처리는 출금 또는 예치금 환불 신청 완료 흐름으로 처리되어야 한다. 공개 상단 회원 드롭다운은 쿠폰·이용권 보유 수와 쿠폰함 진입 링크를 표시하고, 적립금 출금 신청, 예치금 환불 신청, 환전 신청이 실제로 가능한 조건에서만 해당 진입 링크를 표시하며, 클릭 시 각 회원 영역으로 이동해야 한다. `회수` 유형은 음수 금액만 허용하고, 회수 대상 원거래를 참조해야 하며, 같은 대상의 누적 회수액이 원거래 금액을 넘으면 서버에서 거부되어야 한다. 회수 성공 후 회원 알림 제목이 적립금 회수로 생성되어야 한다. 알림 모듈을 비활성화한 환경에서는 같은 자산 처리가 알림 실패 없이 성공해야 한다. 포인트/적립금/예치금 관리자 잔액 조정 모달에는 별도 대액 조정 승인자/승인 사유 입력과 연결 기록 유형/ID 입력이 보이지 않아야 하며, 입력 사유가 감사 로그 metadata의 `approval_note`에 저장되는지 확인한다. 1,000,000 초과 조정은 대상 회원과 입력 사유를 감사 로그 metadata에 남겨야 한다. 10,000,000 초과 1회 조정 또는 관리자별 일일 10,000,000 초과 조정은 서버에서 거부되는지 확인한다.
잔액 조정과 권한 추가처럼 모달 안에서 회원 검색 스택 모달을 여는 화면은 회원 검색 모달을 닫거나 검색 결과를 선택했을 때 이전 단계 모달이 열린 상태로 돌아와야 한다.
설치 DB에서 `/admin/assets/reconciliation`이 열리고 활성 `member-assets.php` 계약의 `balance_table`/`transaction_table` 대상별 잔액 행, 거래 합계, 마지막 거래 잔액 점검 결과가 read-only로 표시되는지 확인한다. 같은 환경에서 `php .tools/bin/reconcile-assets.php` 결과와 관리자 화면 요약이 일치해야 한다. 새 금액성 자산 모듈이 계약을 제공하면 `asset_ledger` 수정 없이 대상에 포함되어야 하며, 계약에 필요한 테이블 필드가 없으면 조용히 생략하지 않고 오류로 보여야 한다. 불일치가 있는 더미 데이터에서는 자동 정정 없이 유형과 계정 ID만 표시되어야 한다.
콘텐츠 유료 열람/다운로드, 콘텐츠 완료 버튼, 커뮤니티 유료 열람/첨부 다운로드/작성·댓글 자산 처리의 중복 POST는 원장 거래 전에 `dedupe_key` unique가 있는 pending placeholder를 먼저 만들고, 성공 후 completed로 바뀌어야 한다. 같은 확인 token으로 두 번 제출하면 중복 원장 거래가 생기지 않아야 하며, 실패 또는 rollback 후에는 pending placeholder가 남아 다음 시도가 현재 상태로 재평가되어야 한다.

설치 DB와 더미 유료 대상이 준비된 로컬/staging에서는 같은 확인 token의 병렬 HTTP 제출을 다음 하니스로 확인할 수 있다. 이 스크립트는 mutation을 수행하므로 `SR_SMOKE_ALLOW_MUTATION=1`을 명시해야 하고 운영 DB에서 실행하지 않는다. public-looking base URL에서는 staging disposable 데이터임을 다시 확인하기 위해 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 요구한다. 대상 화면에서 `csrf_token`과 `asset_request_token`을 읽어 같은 세션으로 병렬 POST를 보내며, 기본 성공 HTTP 상태는 `200,201,204,302,303`이다. 모든 병렬 응답은 `SR_SMOKE_SUCCESS_STATUSES` 안에 있어야 하며, 500이나 예상 밖 4xx/3xx가 하나라도 있으면 실패한다. `SR_SMOKE_EXPECT_DEDUPE_TABLE`과 `SR_SMOKE_EXPECT_DEDUPE_KEY`를 주면 기본적으로 fresh dedupe key를 요구하고, 실행 전 row count 0개와 실행 후 정확히 1개를 확인한다. 하니스 자체는 `check-installed-gate-status.php`의 mock HTTP fixture로 로그인, form token 추출, 병렬 POST, status 집계 흐름을 점검하지만, 설치 DB dedupe row count나 실제 원장 중복 방지 증거를 대체하지 않는다.

```sh
SR_SMOKE_ALLOW_MUTATION=1 \
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_IDENTIFIER=writer@example.com \
SR_SMOKE_PASSWORD='password' \
SR_SMOKE_FORM_PATH='/content/view?slug=paid-fixture' \
SR_SMOKE_POST_PATH='/content/view?slug=paid-fixture' \
SR_SMOKE_EXTRA_POST='asset_confirm=1' \
SR_SMOKE_SUCCESS_STATUSES=200,302,303 \
SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs \
SR_SMOKE_EXPECT_DEDUPE_KEY='content:view:fixture' \
SR_SMOKE_EXPECT_DEDUPE_FRESH=1 \
php .tools/bin/smoke-asset-idempotency-http.php
```

커뮤니티 게시글이나 첨부 다운로드를 대상으로 할 때는 `SR_SMOKE_FORM_PATH`, `SR_SMOKE_POST_PATH`, `SR_SMOKE_EXTRA_POST`, `SR_SMOKE_SUCCESS_STATUSES`, `SR_SMOKE_EXPECT_DEDUPE_TABLE`, `SR_SMOKE_EXPECT_DEDUPE_KEY`를 해당 대상의 확인 form, 성공 응답, dedupe key에 맞게 바꾼다. 확인 token field 이름이 다르면 `SR_SMOKE_TOKEN_FIELD`로 지정한다. 이미 존재하는 dedupe key를 의도적으로 재검증해야 하는 특수 상황에서만 `SR_SMOKE_EXPECT_DEDUPE_FRESH=0`을 지정한다.
/community/edit?id=1 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/edit 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/hide 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/edit 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/hide 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/report 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/content/comment 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
알림 모듈이 활성화된 환경에서 커뮤니티/콘텐츠/퀴즈/설문 댓글 textarea에 `@`를 입력하면 로그인 회원용 `/member/mention-search` 후보가 공개 이름과 hash prefix만 반환하고, 이메일/내부 계정 ID/가입일을 노출하지 않는지 확인한다. 후보 선택으로 삽입된 `@공개이름#prefix`는 현재 공개 이름과 public account hash prefix가 함께 단일 활성 회원에 일치할 때만 `module_key=community/content/quiz/survey, event_key=comment.mention` 템플릿 기반 사이트 알림을 생성해야 한다. 동명이인에게 `@공개이름`만 입력한 모호한 멘션은 단일 대상 알림을 만들지 않아야 하며, 자기 자신과 글/콘텐츠 작성자는 멘션 대상에서 제외되어야 한다. 비밀 댓글은 멘션 알림을 만들지 않아야 한다. 알림 모듈이 비활성화되었거나 템플릿이 누락된 환경에서는 댓글 저장이 실패하지 않아야 한다. `/admin/audit-logs`에서는 댓글 작성 감사 로그에 작성자 알림 생성 여부가 남고, 댓글 작성/수정 감사 로그에는 멘션 후보 수, 실제 멘션 알림 생성 수, 멘션 대상 공개 해시가 남는지 확인한다. `php .tools/bin/check-mention-ux.php`, `php .tools/bin/check-quiz-consistency.php`, `php .tools/bin/check-survey-consistency.php`로 prefix 파서, 후보 API 연결, 비밀 댓글 UI 제어 정합성을 확인한다.
/community/scraps 비로그인 접근이 로그인 흐름으로 막히는지 확인
POST /community/scrap 비로그인 접근이 로그인 흐름으로 막히는지 확인. `target_type=series` 시리즈 스크랩 POST도 같은 로그인/CSRF 흐름을 따라야 한다.
/messages 비로그인 접근이 로그인 흐름으로 막히는지 확인
/message?id=1 비로그인 접근이 로그인 흐름으로 막히는지 확인
/message/write 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/message/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/admin/community/boards 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/community/reports 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/community/reports에서 신고 상태 저장 시 대상 조치 없음/게시글 숨김/댓글 숨김/게시자 정지/삭제+게시자 정지/숨김+게시자 정지 등 대상 유형별 허용 조치만 서버에서 처리되는지 확인
신고 임계치 자동 임시 조치를 켜는 staging 검증에서는 기본값이 비활성인지 먼저 확인하고, 같은 게시글/댓글에 활성 `sr_community_report_auto_actions.active_target_uid`가 둘 이상 생기지 않는지 확인한다. 확정/해제 같은 terminal 상태 처리 후에는 활성 UID가 비워져야 한다.

커뮤니티 계정 guard 검증에서는 publication hold와 confirmed hold 기본값이 비활성인지 확인하고, 같은 계정/guard type에 활성 `sr_community_account_guards.active_guard_uid`가 둘 이상 생기지 않는지 확인한다. released/expired/cancelled 전이 후에는 활성 UID가 비워져야 하며, 만료된 guard는 작성 경로의 현재 guard 판정에서 제외되어야 한다.
/admin/community/posts 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/sitemap.xml 응답이 200이면 sitemap XML 루트가 있고 404여도 PHP 오류가 노출되지 않는지 확인
/assets/layout.css 정적 파일 응답과 공통 공개 layout header/main/footer 확인
/assets/module.css 정적 파일 응답과 초기 공개 화면 스타일 확인
/assets/editor-md.css 정적 파일 응답과 Markdown 본문 reset 확인
/assets/editor-ck.css 정적 파일 응답과 CKEditor 본문 reset 확인
/assets/theme/sample.css 정적 파일 응답과 초기화면 sample theme 스타일 확인
/assets/public-layout.js 정적 파일 응답과 공통 공개 layout 스크롤 header 동작 기준 확인
/assets/common.css 정적 파일 응답과 공개 UI kit scope 및 홈 화면 primitive 확인
/assets/ui-kit-layout.css 정적 파일 응답과 `/ui-kit` 미리보기 helper 확인
/modules/admin/assets/ui-kit-layout.css 정적 파일 응답과 `/admin/ui-kit` 미리보기 helper 확인
/modules/community/theme/basic/assets/reset.css 정적 파일 응답과 커뮤니티 공개 foundation 확인
/modules/community/theme/basic/assets/layout.css 정적 파일 응답과 커뮤니티 공개 layout shell 확인
/modules/community/theme/basic/assets/module.css 정적 파일 응답과 커뮤니티 화면 wrapper 확인
/modules/community/theme/basic/assets/common.css 정적 파일 응답과 커뮤니티 UI kit primitive 확인
/modules/community/theme/basic/assets/ui-kit-layout.css 정적 파일 응답과 커뮤니티 UI kit 미리보기 helper 확인
/modules/content/theme/sample/assets/theme.css 정적 파일 응답과 콘텐츠 sample theme 스타일 확인
/modules/content/assets/layout.js 정적 파일 응답과 콘텐츠 layout 스크롤 header 동작 기준 확인
/modules/content/assets/module.js 정적 파일 응답과 콘텐츠 화면 전용 JavaScript 분리 기준 확인
/modules/community/theme/sample/assets/theme.css 정적 파일 응답과 커뮤니티 sample theme 스타일 확인
/modules/community/assets/layout.js 정적 파일 응답과 커뮤니티 layout 스크롤 header 동작 기준 확인
/modules/community/assets/module.js 정적 파일 응답과 커뮤니티 화면 전용 JavaScript 분리 기준 확인
/modules/member/skins/basic/skin.css 정적 파일 응답과 회원 basic skin CSS 공개 경로 확인
/modules/quiz/theme/basic/assets/layout.css 정적 파일 응답과 퀴즈 공개 layout shell 확인
/modules/quiz/theme/sample/assets/theme.css 정적 파일 응답과 퀴즈 sample theme 스타일 확인
/modules/quiz/assets/layout.js 정적 파일 응답과 퀴즈 layout 스크롤 header 동작 기준 확인
/modules/quiz/assets/module.js 정적 파일 응답과 퀴즈 화면 전용 JavaScript 분리 기준 확인
/modules/survey/theme/basic/assets/layout.css 정적 파일 응답과 설문 공개 layout shell 확인
/modules/survey/theme/sample/assets/theme.css 정적 파일 응답과 설문 sample theme 스타일 확인
/modules/survey/assets/layout.js 정적 파일 응답과 설문 layout 스크롤 header 동작 기준 확인
/modules/survey/assets/module.js 정적 파일 응답과 설문 화면 전용 JavaScript 분리 기준 확인
/database/core/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/member/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/community/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/community/module.php 직접 접근에서 커뮤니티 모듈 코드가 노출되지 않는지 확인
/core/helpers.php 직접 접근에서 PHP 코드가 노출되지 않는지 확인
/core/request-bootstrap.php 직접 접근에서 PHP 코드가 노출되지 않는지 확인
/config/.gitignore 직접 접근에서 config 디렉터리 내용이 노출되지 않는지 확인
/config/config.php 직접 접근에서 DB 설정, 비밀번호, app key가 노출되지 않는지 확인
/storage/.gitignore 직접 접근에서 storage 디렉터리 내용이 노출되지 않는지 확인
/storage/installed.lock 직접 접근에서 설치 잠금 파일 내용이 노출되지 않는지 확인
/docs/deployment-protection.md 직접 접근에서 문서 내용이 노출되지 않는지 확인
/examples/sample_module/module.php 직접 접근에서 예제 모듈 코드가 노출되지 않는지 확인
/AGENTS.md 직접 접근에서 프로젝트 지침이 노출되지 않는지 확인
/README.md 직접 접근에서 루트 문서가 노출되지 않는지 확인
/.tools/bin/check.php 직접 접근에서 도구 코드가 노출되지 않는지 확인
/.git/HEAD 직접 접근에서 저장소 메타데이터가 노출되지 않는지 확인
/.env.local 직접 접근에서 환경변수 파일 변형이 노출되지 않는지 확인
```

설치 전 상태에서는 `/login`, `/admin`, 내부 경로 요청도 설치 화면으로 이어질 수 있다. 이 경우 200 또는 redirect는 허용한다. 중요한 기준은 PHP fatal error가 노출되지 않고, 보호되어야 할 내부 파일의 실제 내용이 직접 노출되지 않는 것이다.

## 인증 커뮤니티 스모크 점검

커뮤니티와 쪽지 모듈이 설치되어 있고 테스트 계정이 준비된 환경에서는 인증 흐름까지 확인한다. 이 점검은 게시글, 댓글, 스크랩, 쪽지, 신고, 관리자 처리 데이터를 실제로 만든다. 운영 DB가 아닌 로컬 또는 스테이징 DB에서 실행한다.

최소 실행은 작성자 계정만 필요하다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_ALLOW_MUTATION=1 \
SR_SMOKE_IDENTIFIER=writer@example.com \
SR_SMOKE_PASSWORD='password' \
php .tools/bin/smoke-community-auth.php
```

스크립트는 mutation 안전장치로 기본 실행을 거부하고 `SR_SMOKE_ALLOW_MUTATION=1`을 요구한다. public-looking base URL에서는 staging disposable 데이터임을 다시 확인하기 위해 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 요구한다.

## 개인정보 Export/Cleanup 스모크

로컬 또는 스테이징에서 탈퇴시켜도 되는 disposable 계정이 준비되어 있으면 개인정보 사본 제공과 탈퇴/익명화 흐름을 함께 확인한다. 이 검사는 대상 계정을 실제로 탈퇴 처리하므로 운영 DB에서 실행하지 않는다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_ALLOW_MUTATION=1 \
SR_SMOKE_IDENTIFIER=privacy_smoke \
SR_SMOKE_PASSWORD='password' \
php .tools/bin/smoke-privacy-export-cleanup.php
```

스크립트는 `/account/privacy-export`의 JSON 구조와 `member` module export를 확인한 뒤 `/account/withdraw`를 POST하고, 기존 자격증명으로 다시 로그인할 수 없는지 확인한다. public-looking base URL에서는 staging disposable 데이터임을 다시 확인하기 위해 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 요구한다. 탈퇴 확인 문구가 바뀐 환경은 `SR_SMOKE_WITHDRAW_CONFIRM_TEXT`로 지정한다.

## 읽기 참조 계약 스모크

마일스톤 13 읽기 참조 계약을 검증할 때는 다음 흐름을 확인한다.

- 발급/사용 이력이 있는 쿠폰 정의도 운영자가 `지급 중지` 또는 `사용 중지`로 전환할 수 있고, 참조 현황은 차단 대신 확인 정보로 남는다.
- 퀴즈 쿠폰 보상 정책이 쿠폰 정의를 참조하고 있으면 서버가 최신 `coupon-references.php` 결과로 비활성화 영향을 표시한다.
- 설문 쿠폰 보상 정책이 쿠폰 정의를 참조하고 있으면 서버가 최신 `coupon-references.php` 결과로 비활성화 영향을 표시한다.
- 콘텐츠나 커뮤니티 설정에서 직접 선택한 배너/팝업레이어가 있으면 해당 배너/팝업레이어 삭제 POST가 차단된다.
- 적립금/예치금/콘텐츠/커뮤니티/회원 자동 규칙에서 쓰는 enabled 회원 그룹은 비활성 또는 보관 상태로 바꾸는 POST가 차단된다.
- 제목 접미사나 기본 설명이 기존 사이트명 그대로인 상태에서 사이트명을 바꾸면 같은 저장 요청에서 새 사이트명으로 함께 보정된다. 로고 alt text처럼 다른 모듈 설정에 기존 사이트명이 직접 들어 있어도 사이트명 변경 POST는 저장되고, 리다이렉트 후 토스트와 이전 사이트명 기준 참조 현황으로 후속 확인을 안내한다. malformed 계약 파일, 누락 callable, 잘못된 row 같은 사이트명 참조 계약 오류는 저장을 차단한다.
- `php .tools/bin/check-read-reference-contracts.php`가 통과하고, `php .tools/bin/check.php` 통합 점검에도 포함된다.
- 보상/접근권 중복 방지 기준은 `php .tools/bin/check-reward-abuse-standards.php`가 통과하고, `php .tools/bin/check.php` 통합 점검에도 포함된다.

## 퀴즈 보상 전용 E2E

퀴즈 마일스톤을 검증할 때는 로컬 또는 스테이징에서 관리자 테스트 계정을 사용해 다음 명령을 실행한다. 이 검사는 퀴즈를 생성하고 제출 기록과 보상 지급을 만든 뒤 가능한 경우 생성 퀴즈를 소프트삭제하므로 운영 DB에서 실행하지 않는다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_ALLOW_MUTATION=1 \
SR_SMOKE_ADMIN_IDENTIFIER=admin \
SR_SMOKE_ADMIN_PASSWORD='12341234' \
php .tools/bin/smoke-quiz-e2e.php
```

활성 자산 보상 후보를 명시해야 하면 `SR_SMOKE_QUIZ_REWARD_MODULE=point`처럼 지정한다. 스크립트는 mutation 안전장치로 기본 실행을 거부하고 `SR_SMOKE_ALLOW_MUTATION=1`을 요구한다. public-looking base URL에서는 staging disposable 데이터임을 다시 확인하기 위해 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 요구한다. 관리자 퀴즈 생성, 복수/단일 선택 제출, 통과 결과, 보상 지급, 회원당 1회 재응시 차단을 확인한다.

적립금 보상 회수는 `/admin/quiz/attempts`에서 grant별 회수 가능액과 회수 버튼이 보이는지 확인한다. 회수 모달은 `intent=reclaim_reward`, `grant_id`, `amount`, `reason`, `return_to`를 보내며, 서버가 CSRF와 편집 권한을 확인하고 grant 기준 원장 거래, 회수 가능액, 적립금 `reclaim` 참조를 트랜잭션 안에서 다시 검증해야 한다.

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
작성자 로그인 후 /messages 접근
자유 게시판 게시글 작성과 상세 화면 제목 확인
작성자 게시글 수정과 상세 화면 수정 제목/본문 확인
댓글 작성과 상세 화면 댓글 본문 확인
게시글 스크랩 추가와 스크랩 목록 노출, 해제 후 목록 미노출 확인. 시리즈 스크랩은 게시글 스크랩과 별도 목록으로 표시되고 해제 후 목록에서 빠지는지 확인
게시글·시리즈 스크랩을 각각 21건 이상 만든 로컬 더미 계정에서 두 목록의 두 번째 페이지를 독립적으로 이동하고, 한 목록의 페이지 이동이 다른 목록 페이지를 보존하며 해제 후 가능한 현재 스크랩 페이지로 돌아오는지 확인
수신자 닉네임을 타이핑해 자동완성 회원을 선택하고, 여러 수신자를 추가했을 때 각 수신자에게 쪽지가 생성되는지 확인
수신자 계정 지정 시 쪽지 발송과 보낸 쪽지 본문 확인
수신자 비밀번호 지정 시 수신자 로그인 후 받은 쪽지 본문 확인
보낸 쪽지 삭제 후 보낸 쪽지함 미노출과 발신자 404 응답 확인
받은 쪽지와 보낸 쪽지를 각각 21건 이상 만든 로컬 더미 계정에서 두 번째 페이지와 마지막 부분 페이지까지 이동하고, 두 번째 페이지의 쪽지를 삭제한 뒤 같은 편지함과 가능한 현재 페이지로 돌아오는지 확인
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
7. /admin/modules에서 설치 가능 모듈/플러그인과 설치된 모듈/플러그인이 카드 그리드가 아닌 목록 테이블로 표시되고, 헤더 정렬과 설치 버전/코드 버전 확인이 동작하는지 확인
8. /admin/updates에서 미적용 SQL 목록 또는 없음 확인
9. /admin/audit-logs에서 이벤트와 대상 유형을 한국어 선택 컨트롤로 고를 수 있고, 대상 식별값, 처리자 유형, IP, 결과, 날짜 필터가 상세검색 안에서 동작하며 초기화 버튼으로 표시된 필터가 비워지고 metadata 상세 모달이 민감값을 마스킹한 채 열리는지 확인
10. /account에서 계정 화면 진입
11. 로그아웃 후 /admin 접근 시 로그인 흐름 확인
```

선택 모듈이 포함된 배포본은 다음 항목을 추가로 확인한다.

```text
선택 모듈 체크 후 설치 완료
서비스 도메인 모듈 카드에서 초기화면으로 설정 체크를 선택한 경우 site.home_path가 저장되고 / 접속 시 해당 경로로 이동
/admin/settings 화면 섹션에서 기본 홈페이지 / 접속, 콘텐츠/커뮤니티/퀴즈/설문 초기화면 선택과 fallback 확인
선택 모듈 관리자 메뉴 노출
선택 모듈의 GET 관리자 path가 500 없이 열림
```

배너, 팝업레이어, 커뮤니티 모듈이 함께 설치된 배포본은 관리자 대상 선택 lookup을 추가로 확인한다.

```text
/admin/banners 또는 /admin/popup-layers에서 커뮤니티 게시글 노출 위치 선택
빈 검색 최신순 20개 결과와 더 보기 동작 확인
게시글 ID 숫자 검색이 정확 조회로 동작하는지 확인
게시판 필터와 상태 필터가 결과를 좁히는지 확인
텍스트 1자 검색이 안내만 보여주고 넓은 검색을 실행하지 않는지 확인
더 보기 후 선택한 대상 요약이 폼에 표시되는지 확인
존재하지 않는 ID와 삭제된 게시글 ID 저장이 서버 검증에서 거부되는지 확인
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

실제 HTTP 경로 기반 더미 데이터 시더는 로컬 또는 staging disposable 데이터에서만 다음처럼 실행한다.

```sh
SR_SEED_ALLOW_MUTATION=1 \
SR_SEED_BASE_URL=http://127.0.0.1:8080 \
SR_SEED_ADMIN_IDENTIFIER=admin \
SR_SEED_ADMIN_PASSWORD='password' \
php .tools/bin/seed-dummy-http.php
```

기본 실행은 회원, 콘텐츠, 커뮤니티 게시글, 배너, 팝업레이어, 쿠폰, 알림에 더해 테스트용 콘텐츠 다운로드 파일, 커뮤니티 첨부 다운로드, 퀴즈, 설문을 만든다. 기초 레코드는 HTTP 등록 경로로 만들고, 회원은 `/register` 생성이 목표 수량에 못 미치면 관리자 세션의 `/admin/members/save`로 나머지를 보충한다. 다운로드 파일과 퀴즈/설문 조합은 제한된 DB/storage fixture로 얹는다. 다운로드는 무료/포인트 차감/적립금 차감, 퀴즈와 설문은 보상 없음/포인트 보상/적립금 보상 대표 변형이 섞이도록 구성한다. 이 풍부한 변형을 제외하려면 `SR_SEED_SKIP_RICH_FIXTURES=1`을 함께 지정한다.

시더는 각 POST 응답의 관리자 오류, 공개 피드백 오류, 회원가입 오류 목록을 발견하면 즉시 실패로 보고해야 한다. 도메인별 생성 전/후 카운트가 기대 증가량보다 작으면 성공 redirect처럼 보인 응답도 실패로 본다.

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

## 커뮤니티 보상 미회수 스모크

커뮤니티 모듈이 설치된 local/staging 환경에서만 보상 미회수 흐름을 점검한다. production 데이터로 destructive 또는 mutating smoke를 실행하지 않는다.

확인 항목:

- 게시글/댓글 작성 보상 지급 후 잔액을 회수 대상 금액보다 낮게 만든 dummy 계정 준비
- 관리자 단건 숨김/삭제와 일괄 숨김에서 본래 상태 변경은 성공하고 `sr_asset_recovery_failures` open row가 생성되는지 확인
- unknown failure는 상태 변경과 미회수 row가 함께 rollback되는지 정적 또는 fixture로 확인
- `/admin/assets/recovery-failures`에서 status, asset, subject, account/date 필터가 동작하고 open row에만 재회수/해소/취소 action이 보이는지 확인
- `/admin/community/recovery-failures` legacy route는 공통 미회수 큐로 redirect되는지 확인
- 재회수 전액 성공은 기존 open row를 `recovered`로 닫고, 부분 성공은 recovered/unrecovered 금액을 갱신한 뒤 open 상태를 유지하는지 확인
- 수동 `manually_resolved`/`cancelled`는 확인 문구와 관리자 사유를 서버에서 검증하고 원장 거래를 만들지 않는지 확인

## 콘텐츠·퀴즈·설문 삭제 상태와 영구 삭제 스모크

#404 기준의 자동 검사 또는 설치 DB smoke는 일반 삭제와 영구 물리 삭제를 구분해 확인한다. 일반 삭제는 원문 redaction과 소유 저장소 파일 삭제 시도, cleanup failure 기록을 확인하고, 영구 삭제는 삭제됨 전용 보기에서만 본체와 삭제 배정 하위 row를 제거하는지 확인한다.

- 콘텐츠 deleted 상태에서 edit GET, save POST, copy POST, batch status POST가 모두 fail-closed 되는지 확인
- 퀴즈 `status = archived`이지만 `deleted_at IS NULL`인 정상 보관 항목이 영구 삭제 후보에 포함되지 않는지 확인
- 콘텐츠·퀴즈·설문 삭제됨 보기에서 삭제 판정값, 보존 로그 카운트, cleanup failure/pending 카운트가 보이는지 확인
- 영구 삭제 후 본체 row와 삭제 배정 하위 row가 사라지고, 보존 배정 로그가 snapshot 기준으로 조회되는지 확인
- 일반 삭제 저장소 삭제 실패를 주입해 cleanup failure/pending row가 본체 JOIN 없이 재시도 가능한지 확인
- `sr_content_files.content_id = 0` 미연결 파일 row가 영구 삭제 고아 검사에서 오탐되지 않는지 확인
