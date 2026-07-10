# 우선순위 보강 검증 기록 - 2026-07-10

이번 기록은 1.0 릴리스 후보 판정이 아니라, P0/P1 우선순위 보강 작업을 disposable 로컬 설치 환경에서 검증한 기록이다. 실제 배포 후보 commit과 staging 환경 검증은 별도 릴리스 기록으로 남긴다.

## 대상

| 항목 | 값 |
| --- | --- |
| 실행 날짜 | 2026-07-10 |
| 기준 commit | `e462efaa26a8` 이후 작업 트리 변경 |
| 브랜치 | `main` |
| 작업 트리 상태 | `dirty` |
| PHP 버전 | `8.1.2` |
| DB | MariaDB `10.6.23` |
| base URL | disposable 로컬 PHP 서버 `http://127.0.0.1:18762` |
| 설치 상태 | 32개 번들 모듈을 설치한 새 로컬 DB |

## 범위

검증 대상:

- 새 설치와 업데이트 적용, 설치 DB read-only 운영 게이트, 기본 HTTP와 관리자 화면
- 커뮤니티 인증 쓰기, 퀴즈, 금액성 중복 방지, 개인정보 export/cleanup mutation
- CKEditor 48.3.0 직접 호스팅 자산, 브라우저 fallback/upload adapter, 서버 업로드·저장·sanitizer·본문 이미지 접근
- MariaDB 실제 쿼리와 10,000개 게시글 fixture의 커뮤니티 홈 feed 성능
- 외부 의존성 버전·무결성·라이선스 고지와 릴리스 패키지 포함 정책

제외 또는 별도 후속 대상:

- 실제 Apache/nginx staging 배포와 외부 OAuth·본인확인·captcha provider
- 운영 메일·push provider의 실제 전달, 유료 본문 이미지의 별도 브라우저 대조
- 실제 릴리스 zip checksum과 clean commit 기준 최종 후보 판정

## 정적 점검

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `git diff --check` | 통과 | CKEditor 공식 번들의 문자열 내부 공백은 `.gitattributes`로 vendored 파일에만 예외 적용 |
| `php .tools/bin/check-ckeditor-assets.php` | 통과 | 48.3.0 버전, SHA-256, 라이선스, self-hosted fallback 확인 |
| `php .tools/bin/check-dependency-policy.php` | 통과 | 제3자 고지와 dependency policy 연결 확인 |
| `php .tools/bin/check-release-package-policy.php` | 통과 | vendored 자산 포함·금지 경로 정책 확인 |
| `php .tools/bin/release-preflight.php` | 통과 | HTML Purifier 4.19.0, CKEditor 48.3.0, 패키지 manifest 생성 확인 |
| `php .tools/bin/check.php` | 통과 | 이 기록을 포함한 최종 통합 게이트에서 재검증 |

## 릴리스 후보 필수 설치 DB 게이트

| 게이트 | 결과 | 환경 | 메모 |
| --- | --- | --- | --- |
| 새 설치 또는 업데이트 적용 | 통과 | disposable 새 설치 MariaDB | 169개 테이블과 32개 모듈 설치, update pending 생성·적용·해제와 schema version 복원 확인 |
| `php .tools/bin/reconcile-assets.php` | 통과 | disposable 설치 DB | read-only reconciliation 불일치 없음 |
| `php .tools/bin/ops-status.php` | 통과 | disposable 설치 DB | queue/cron/배치 상태 조회 성공 |
| `php .tools/bin/expire-points.php --dry-run` | 통과 | disposable 설치 DB | 원장 변경 없이 만료 preview 확인 |
| /admin/assets/reconciliation | 통과 | disposable 설치 DB + 관리자 | 로그인 세션 read-only 화면과 기대 문구 확인 |
| /admin/operations | 통과 | disposable 설치 DB + 관리자 | 로그인 세션 read-only 화면과 기대 문구 확인 |
| 기본 HTTP smoke | 통과 | disposable 로컬 서버 | 커뮤니티 설치 기대 모드 포함 route, 보안 헤더, 보호 경로 확인 |
| 인증 smoke | 통과 | disposable 더미 회원 | 로그인, 글·댓글·쪽지·스크랩·신고와 관리자 moderation 흐름 확인 |
| 퀴즈 E2E smoke | 통과 | disposable 더미 데이터 | 생성, 제출, 보상, 재응시 차단 확인 |
| 자산/쿠폰/유료 접근권 mutation smoke | 통과 | disposable 유료 콘텐츠 | 같은 확인 token 6개 병렬 POST에서 dedupe row 1개 확인, 쿠폰/접근권 runtime fixture 통과 |
| 개인정보 export/cleanup smoke | 통과 | 탈퇴 전용 disposable 계정 | export JSON, 익명화·탈퇴, 기존 세션과 자격증명 접근 차단 확인 |
| CKEditor asset/fallback browser smoke | 통과 | Playwright Chromium + 사용자 로컬 GUI 런타임 | self-hosted 초기화, 누락 fallback, upload adapter 성공/오류 4개 시나리오 통과 |
| CKEditor upload/save browser smoke | 통과 | disposable 설치 DB + 관리자 | 업로드, CSRF, 저장, sanitizer, 공개/draft 이미지 접근 정책 확인 |
| 성능 수동 점검 | 통과 | MariaDB 실제 설치 DB와 10,000개 게시글 fixture | 실제 DB latest cold 65.661ms/warm 2.166ms, popular cold 48.715ms/warm 3.886ms; 10k fixture latest cold 3.030ms/warm 1.430ms, popular cold 58.544ms/warm 1.401ms |

## 실패와 제한

| 항목 | 분류 | 판정 | 후속 |
| --- | --- | --- | --- |
| 시스템 전역 Chromium 공유 라이브러리 | 로컬 대체 | 사용자 로컬 설치로 해소 | sudo 비밀번호 없이 `$HOME/.local/share/saanraan-browser-runtime`에 설치했으며 브라우저 4개 시나리오 통과 |
| 유료 본문 이미지 브라우저 대조 | 별도 후속 | 일반 공개/draft 접근 정책까지 확인 | 실제 릴리스 후보 또는 staging에서 유료 접근권이 있는 계정과 없는 계정을 나눠 대조 |
| 실제 배포 서버 | 별도 후속 | 로컬 dev router 범위만 확인 | Apache/nginx staging에서 보호 경로와 보안 헤더 재검증 |

## 리스크별 릴리스 판정 연결

| 리스크 | 연결된 검증 증거 | 이번 판정 | 후속 |
| --- | --- | --- | --- |
| R-01 자산/쿠폰/유료 접근권 | reconciliation, 6개 병렬 POST dedupe, coupon/payment runtime fixture | 완화 | staging의 실제 상품·쿠폰 조합으로 최종 대조 |
| R-02 HTML sanitizer/CKEditor | CKEditor 48.3.0 해시, 브라우저 4개 시나리오, upload/save sanitizer smoke | 완화 | 유료 본문 이미지와 nonce 기반 CSP 결정 |
| R-03 공유호스팅 queue/cron/배치 | ops status, point expiration dry-run, 관리자 운영 화면 | 완화 | 실제 cron 주기에서 지연 신호 확인 |
| R-04 개인정보 export/cleanup 계약 | fixture와 disposable 회원 탈퇴 mutation smoke | 완화 | 운영 보존 데이터 접근 범위 수동 대조 |
| R-05 넓은 번들 모듈 표면 | 32개 모듈 새 설치, HTTP/auth/quiz/privacy/CKEditor 게이트 | 완화 | beta 모듈별 staging 기록 누적 |
| R-06 커스텀 요청/보안 contract | CSRF guard, 인증 smoke, 보안 헤더와 보호 경로 HTTP smoke | 완화 | 외부 provider callback 실환경 확인 |
| R-07 외부 의존성/vendored asset | HTML Purifier 4.19.0, CKEditor 48.3.0, 해시와 제3자 고지, preflight | 완화 | 실제 릴리스 zip에서 동일 버전과 라이선스 재확인 |
| R-08 배포 보호 | dev router 보호 경로와 HTTP 헤더 smoke | 조건부 | Apache/nginx staging 확인 필요 |
| R-09 저장소 문서 정합성 | dependency policy, smoke 문서, 제3자 고지, 검증 기록 | 완화 | 최종 commit 기준 링크와 상태표 재검증 |
| R-10 국내 CMS 대비 신뢰 증거 | 설치 DB와 브라우저·성능 수치가 있는 날짜별 기록 | 완화 | 릴리스 후보별 기록 누적 |
| R-11 성능/캐시 기준 | 실제 MariaDB와 10k fixture cold/warm 측정, 후보 index 비교 후 제거 | 완화 | 운영 데이터 분포에서 slow query 관찰 |

## 판정

최종 판정:

- 조건부 통과

릴리스 또는 배포 가능 여부:

- P0/P1 보강 묶음은 로컬 disposable 환경 기준으로 통과했다. 실제 1.0 릴리스 후보 판정은 clean commit과 staging 배포 기록에서 별도로 수행한다.

릴리스 후보 필수 설치 DB 게이트 미실행 여부:

- 없음

후속 작업:

- 실제 배포 서버 보호 규칙, 유료 본문 이미지, 외부 provider 흐름을 staging에서 확인한다.
- CSP `unsafe-inline` 제거는 nonce 도입과 인라인 스크립트 이동 범위를 먼저 고정한 뒤 별도 변경으로 처리한다.
