# 성능 베이스라인 증거표

이 문서는 공유호스팅 기준에서 산란이 최소한 어떤 성능 안전선을 유지하는지 정리한다. 목표는 대형 트래픽 성능을 보증하는 것이 아니라, 무제한 목록 렌더링, 잘못된 HTML 캐시, 웹 노출 캐시 경로 같은 회귀를 일찍 잡는 것이다.

## 기준표

| 영역 | 기준 | 자동 증거 |
| --- | --- | --- |
| 관리자 대형 목록 | 회원, 게시글, 댓글, 콘텐츠, 원장, 알림, 감사 로그처럼 커지는 목록은 페이지네이션을 사용한다. | `.tools/bin/check-performance-baseline.php` |
| 목록 쿼리 제한 | 관리자 목록 action은 `sr_admin_pagination_from_total()` 또는 `sr_admin_paginate_array()`를 사용하고, 조회 helper는 `LIMIT/OFFSET` 또는 pagination offset을 적용한다. | `.tools/bin/check-performance-baseline.php` |
| 페이지네이션 런타임 | 페이지 번호 파싱, 마지막 페이지 clamp, offset, 배열 slice, 필터 유지 URL, summary/HTML 상태를 fixture로 확인한다. | `.tools/bin/check-admin-pagination-runtime.php` |
| 인덱스 안전선 | 자산 원장, 쿠폰 사용, 알림 queue, 개인정보 대응 기록, 콘텐츠/커뮤니티 활동 로그, board copy job처럼 증가하는 테이블은 계정/상태/시각/dedupe/reference 조회 인덱스를 설치 SQL에 유지한다. | `.tools/bin/check-performance-baseline.php` |
| 캐시 경로 | 파일 캐시는 `storage/cache/` 아래에서만 허용하고, 현재 기본 허용 경로는 HTML Purifier 정의 캐시인 `storage/cache/htmlpurifier`와 공개 이미지 썸네일 캐시인 `storage/cache/thumbnails`다. 썸네일 캐시는 module key, hash prefix, variant, source version을 포함한 생성 파일명 패턴의 이미지 파일만 직접 접근을 허용하며, 관리자 캐시 화면은 이 경로를 파일 스캔 방식으로 조회/정리한다. | `.tools/bin/check-performance-baseline.php`, `.tools/bin/check-dependency-policy.php`, `.tools/bin/check-storage-helpers.php`, HTTP smoke |
| HTML 응답 캐시 | 관리자 HTML, 로그인 HTML, CSRF token 포함 화면, 개인정보 export 결과는 파일 캐시하지 않는다. | `docs/performance-policy.md`, `.tools/bin/check-performance-baseline.php` |
| 동적 HTML no-store | 동적 HTML 진입점은 `Cache-Control: no-store`를 보내고 HTTP smoke가 이를 확인한다. 직접 `Cache-Control` 헤더를 쓰는 파일은 이미지, 다운로드, 재계산 stream 같은 허용 응답 파일과 값으로 고정한다. | `sr_send_security_headers()`, `.tools/bin/smoke-http.php`, `.tools/bin/check-performance-baseline.php` |
| 다운로드 응답 헤더 | 다운로드/CSV/privacy export는 공통 helper로 no-store 또는 private no-store cache-control, optional content length, 안전한 filename disposition을 적용한다. `sr_download_cache_control()`은 허용 cache-control 값을 보존하고 빈 값/제어문자/위험 문자는 no-store 기본값으로 정규화한다. | `sr_send_download_headers()`, `.tools/bin/check-output-helpers.php`, `.tools/bin/check-paid-download-delivery.php`, `.tools/bin/check-performance-baseline.php` |
| 이미지 응답 헤더 | 공개 이미지, 관리자 아이콘, 아바타, 본문 이미지 proxy는 공통 helper로 public/private cache-control, optional content length, nosniff를 적용한다. 아바타 같은 공개 업로드 이미지는 `ETag`/`Last-Modified` 조건부 요청으로 `304 Not Modified`를 반환할 수 있다. SVG 로고는 추가 CSP도 helper allowlist를 통과한다. | `sr_send_file_headers()`, `.tools/bin/check-output-helpers.php`, `.tools/bin/check-performance-baseline.php` |
| 정적 asset 캐시 | 이미지, CSS, JS 같은 공개 정적 asset은 version query 또는 파일 변경 시각과 함께 브라우저 캐시를 사용할 수 있다. | `sr_stylesheet_tag()`, `sr_admin_stylesheet_tag()`, HTTP smoke |
| sitemap/export 상한 | sitemap과 개인정보 export처럼 모듈 데이터를 모으는 read-only 출력은 per-query 상한을 둔다. | `.tools/bin/check-performance-baseline.php` |
| 관리자 CSV export 상한 | 관리자 CSV export는 타입별 행 상한을 두고, 감사 로그 metadata에 실제 적용 상한을 남긴다. 설문 응답 export는 raw 5000행, analysis 20000행, codebook 10000행 상한을 고정하고 runtime fixture로 필터와 CSV cell escaping을 확인한다. | `.tools/bin/check-performance-baseline.php`, `.tools/bin/check-survey-export-runtime.php` |
| 고부하 작업 | 대량 복사, 재계산, 삭제 같은 관리자 작업은 부하 등급, 배치/작업 테이블형, lock/dedupe/drift 기준을 검토한다. 커뮤니티 게시판 전체 복사는 동기 복사 상한과 배치 전환/차단 규칙을 fixture로 고정한다. | `docs/admin-ui-guide.md`, `.tools/bin/check-performance-baseline.php`, `.tools/bin/check-community-board-copy-limits.php` |

## 관리자 목록 기준

다음 화면은 1.0 전 기본 성능 기준에서 페이지네이션 유지 대상이다.

| 영역 | 대표 화면 |
| --- | --- |
| 시스템 | `/admin/modules`, `/admin/roles`, `/admin/audit-logs` |
| 회원 | `/admin/members`, `/admin/member-groups` |
| 콘텐츠 | `/admin/content`, `/admin/content-groups`, `/admin/content/series`, `/admin/content/files`, `/admin/content/file-downloads` |
| 커뮤니티 | `/admin/community/posts`, `/admin/community/comments`, `/admin/community/boards`, `/admin/community/board-groups`, `/admin/community/reports`, `/admin/community/series` |
| 자산/권리 | `/admin/points`, `/admin/rewards`, `/admin/deposits`, `/admin/coupons`, `/admin/asset-exchange/logs` |
| 알림/개인정보 | `/admin/notifications`, `/admin/notification-deliveries`, `/admin/admin-notifications`, `/admin/privacy-requests` |
| 참여 | `/admin/quiz`, `/admin/quiz/attempts`, `/admin/surveys/responses`, `/admin/surveys/reward-logs` |
| 사이트 운영 | `/admin/banners`, `/admin/popup-layers`, `/admin/logo-manager` |

이 표는 모든 화면이 같은 쿼리 비용이라는 뜻이 아니다. 다만 행 수가 늘어날 수 있는 목록은 무제한 출력으로 회귀하지 않게 최소 기준을 둔다.

## 인덱스 안전선

다음 인덱스는 실제 실행 계획을 보증하지는 않지만, 공유호스팅에서 가장 먼저 터지는 무제한 scan 회귀를 막기 위한 정적 안전선이다. 이름과 컬럼 조합이 바뀌면 같은 조회 패턴을 대체한다는 근거를 남기고 `.tools/bin/check-performance-baseline.php`도 함께 고친다.

| 영역 | 유지 기준 |
| --- | --- |
| 포인트/적립금/예치금 | 잔액 `account_id` unique, 거래 `account_id, created_at`, 참조 `reference_type, reference_id`, 출금/환불 요청 `status, requested_at` |
| 포인트 만료 | 만료 대상 조회용 `expires_at, expires_remaining`, 만료 소비 연결용 source/consume transaction 인덱스 |
| 쿠폰 | 쿠폰 정의 `status, target_type, target_id`, 회원 쿠폰 `account_id, status, expires_at`, 사용 dedupe unique, 참조 회수 `reference_module, reference_type, reference_id` |
| 알림/운영 알림 | 회원 알림 `account_id, status, read_at`, delivery queue `channel, status`, 운영 알림 `status, severity, updated_at`, 이벤트 템플릿 unique |
| 개인정보 대응 기록 | `account_id`, `status`, `created_at` 기준 관리자 목록과 사본 제공 조회 |
| 콘텐츠 | 공개/관리 목록 `status, updated_at`, 댓글 thread, 유료 접근/완료 dedupe, 파일 다운로드 환불 상태, 접근권 unique |
| 커뮤니티 | 게시글 `board_id, status`, 댓글 thread, board copy job `status, stage, updated_at`, 자산 로그 dedupe, 접근권 unique |
| 환전 | 환전 묶음 unique, 계정별 이력, 순환 방지 조회, 상태별 관리자 이력 |

## 한계

- 이 기준은 실제 DB 실행 계획, 인덱스 선택, 브라우저 렌더링 비용을 증명하지 않는다.
- 설치 DB에서 느린 화면을 발견하면 릴리스 검증 기록에 화면, 필터, 행 수, 실행 시간을 남긴다.
- 대형 사이트 운영은 별도 캐시 서버와 worker를 도입하는 확장 설계가 필요할 수 있으며, 1.0 기본 요구사항은 아니다.
