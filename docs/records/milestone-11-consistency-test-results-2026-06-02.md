# 마일스톤 11 정합성 테스트 결과 기록 - 2026-06-02

## 범위

마일스톤 11의 정합성 검증 이슈 #142부터 #150까지를 대상으로 로컬 설치 환경에서 자동 검사와 HTTP 스모크 테스트를 수행했다.

대상 환경:

- 브랜치: `main`
- 데이터베이스: 로컬 MariaDB 컨테이너 `saanraan-m10-db`
- 설치 상태: 전체 번들 모듈 설치 및 활성화
- HTTP 스모크 기준 URL: `http://host.docker.internal:8088`

## 추가 자동 검사

마일스톤 11 전용 검사로 `.tools/bin/check-milestone-11-consistency.php`를 추가했다.

검사 항목은 다음 범위를 포함한다.

- #142: 공통 정합성 fixture와 owner 관리자 존재 확인
- #143: `sr_` 테이블 namespace, schema updater idempotency, schema version 기록, 주요 컬럼 존재
- #144: 관리자 권한 경계, 일반 회원의 관리자 권한 차단, 공개 회원 식별자 hash 처리
- #145: 공개/숨김/예약 콘텐츠의 sitemap 노출 조건
- #146: legacy 링크 카드 token 저장 거부와 link ref 원장 비움 유지
- #147: point/reward/deposit 원장 합계, 쿠폰 접근권 dedupe
- #148: 감사 로그 기록, site/email 알림 delivery row, 민감 metadata scan
- #149: 개인정보 export module contract 포함, cleanup contract 실행
- #150: 메뉴 후보 URL 안전성, sitemap 중복 제거, robots sitemap 노출, banner/popup 활성 기간과 출력 escape

실행 결과:

```text
Milestone 11 consistency checks completed: 28 assertions.
```

## 실행한 검사

통과:

- `git diff --check`
- `.tools/bin/php -l .tools/bin/check-milestone-11-consistency.php`
- `for f in .tools/bin/check-*.php; do .tools/bin/php "$f"; done`
- `SR_SMOKE_EXPECT_COMMUNITY=1 .tools/bin/php .tools/bin/smoke-http.php http://host.docker.internal:8088`
- `.tools/bin/php .tools/bin/smoke-community-auth.php http://host.docker.internal:8088 writer_m10 'SaanraanM10!' free recipient_m10 0 reporter_m10 'SaanraanM10!' admin_m10 'SaanraanM10!' 'SaanraanM10!'`

참고:

- `.tools/bin/php .tools/bin/check.php`는 컨테이너 내부에 `git` 실행 파일이 없어 내부 `git diff --check` 단계만 실패했다.
- 같은 `git diff --check`는 호스트에서 통과했고, `check.php`가 실행한 나머지 PHP 검사들은 모두 통과했다.
- 전체 `check-*.php` 루프는 새 마일스톤 11 검사까지 포함해 통과했다.

## 이슈별 판정

| 이슈 | 판정 | 확인 내용 |
| --- | --- | --- |
| #142 | 통과 | 공통 fixture와 자동 검증 기준을 별도 스크립트로 구성했다. |
| #143 | 통과 | 설치 테이블 namespace, schema version, update idempotency, 주요 컬럼을 확인했다. |
| #144 | 통과 | 관리자 권한 경계와 회원 공개 식별자 hash 처리를 확인했다. |
| #145 | 통과 | 공개/숨김/예약 상태의 sitemap 노출 조건을 확인했다. |
| #146 | 통과 | 링크 카드 token 저장 거부와 link ref 원장 비움 유지를 확인했다. |
| #147 | 통과 | 자산 원장 합계와 쿠폰 접근권 dedupe를 확인했다. |
| #148 | 통과 | 감사 로그, 알림 delivery, 민감 metadata 누출 방지를 확인했다. |
| #149 | 통과 | 개인정보 export와 cleanup contract 실행을 확인했다. |
| #150 | 통과 | 메뉴 후보, sitemap/robots, banner/popup 공개 조건과 escape를 확인했다. |

## GitHub 이슈 상태

검사 시점에 마일스톤 11 이슈 #142부터 #150은 모두 `OPEN` 상태였다. 이번 작업에서는 사용자가 이슈 닫기를 요청하지 않았으므로 이슈 상태를 변경하지 않았다.

## 결론

마일스톤 11 범위의 자동 정합성 검사, 전체 로컬 검사, HTTP 스모크, 인증 커뮤니티 스모크가 모두 통과했다. 현재 확인된 기능 실패는 없다.
