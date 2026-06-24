# 이슈 369 커뮤니티 홈 피드 측정 하니스 기록

작성일: 2026-06-24

관련 이슈: #369

## 목적

영속 feed cache table을 만들기 전에 현재 통합 홈 피드 쿼리의 비용을 fixture/EXPLAIN 형식으로 기록할 수 있는 하니스를 추가했다. 하니스는 실제 홈 최신글/인기글 조회와 같은 feed query helper를 사용한다. 이 기록은 SQLite fixture 결과, MariaDB fixture 결과, 로컬 개발 DB target 측정 결과를 구분해 남긴다.

## 실행 명령

```bash
SR_COMMUNITY_FEED_MEASURE_POSTS=10000 SR_COMMUNITY_FEED_MEASURE_BOARDS=20 php .tools/bin/measure-community-home-feed.php
```

대표 MySQL/MariaDB 로컬 또는 staging DB에서는 읽기 전용 계정과 명시 DSN으로 같은 출력 형식을 남긴다.

```bash
SR_COMMUNITY_FEED_MEASURE_DSN='mysql:host=127.0.0.1;dbname=saanraan;charset=utf8mb4' \
SR_COMMUNITY_FEED_MEASURE_USER='readonly_user' \
SR_COMMUNITY_FEED_MEASURE_PASSWORD='readonly_password' \
SR_COMMUNITY_FEED_MEASURE_TABLE_PREFIX='sr_' \
SR_COMMUNITY_FEED_MEASURE_BOARD_IDS='1,2,3' \
php .tools/bin/measure-community-home-feed.php
```

`SR_COMMUNITY_FEED_MEASURE_BOARD_IDS`를 생략하면 `status = enabled`이고 `read_policy = public`인 게시판을 `SR_COMMUNITY_FEED_MEASURE_BOARD_LIMIT`만큼 자동 선택한다.

개발 DB에 공개 baseline 게시글이 부족하면 설치 DB에 삭제 가능한 성능 fixture를 먼저 만든 뒤 같은 측정을 실행한다. 이 fixture는 실제 글 작성 흐름을 검증하는 도구가 아니라 홈 피드 read query의 대표 row 수와 실행계획을 만들기 위한 도구다. 운영 DB에서는 실행하지 않는다.

```bash
SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1 \
SR_COMMUNITY_FEED_FIXTURE_RUN_KEY=sr369_local \
SR_COMMUNITY_FEED_FIXTURE_POSTS=10000 \
SR_COMMUNITY_FEED_FIXTURE_BOARDS=20 \
php .tools/bin/seed-community-feed-fixture.php seed
```

config DB를 읽어 측정한다.

```bash
SR_COMMUNITY_FEED_MEASURE_CONFIG=1 \
php .tools/bin/measure-community-home-feed.php
```

측정 후 fixture는 같은 `run_key`로 삭제한다.

```bash
SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1 \
php .tools/bin/seed-community-feed-fixture.php cleanup sr369_local
```

같은 `run_key`가 이미 있으면 seed는 기본적으로 중단한다. 반복 측정에서 교체가 필요할 때만 로컬/staging disposable DB에서 `SR_COMMUNITY_FEED_FIXTURE_REPLACE=1`을 함께 사용한다.

운영/스테이징 데이터를 읽을 수 없고 MariaDB 문법/실행계획만 확인해야 할 때는 임시 DB에 fixture를 명시적으로 생성할 수 있다. 이 모드는 DSN 대상의 `sr_community_*`, `sr_member_accounts` fixture table을 drop/create하므로 임시 DB에서만 사용한다.

```bash
SR_COMMUNITY_FEED_MEASURE_DSN='mysql:unix_socket=/tmp/sr369/mariadb.sock;dbname=sr369_fixture;charset=utf8mb4' \
SR_COMMUNITY_FEED_MEASURE_USER='root' \
SR_COMMUNITY_FEED_MEASURE_FIXTURE=1 \
SR_COMMUNITY_FEED_MEASURE_POSTS=10000 \
SR_COMMUNITY_FEED_MEASURE_BOARDS=20 \
php .tools/bin/measure-community-home-feed.php
```

## 결과 요약

```text
fixture-step: sqlite-local
fixture-size: posts=10000 boards=20
db-version: sqlite 3.37.2
hosting-probe: sqlite fixture only

query-id: community.home.latest
response-ms-cold: 9.053
response-ms-warm: 4.3
returned-rows: cold=10 warm=10

query-id: community.home.popular
response-ms-cold: 1.205
response-ms-warm: 1.116
returned-rows: cold=5 warm=5
```

## MariaDB fixture 결과

```text
fixture-step: mariadb-fixture
fixture-size: posts=10000 boards=20
db-version: mysql 10.6.23-MariaDB-0ubuntu0.22.04.1
hosting-probe: explicit DSN fixture measurement on MySQL/MariaDB syntax

query-id: community.home.latest
explain-summary: p uses idx_sr_community_posts_status_id range; attachment derived table uses temporary/filesort
response-ms-cold: 4.052
response-ms-warm: 73.583
returned-rows: cold=10 warm=10

query-id: community.home.popular
explain-summary: p uses idx_sr_community_posts_view range; attachment derived table uses temporary/filesort
response-ms-cold: 2.329
response-ms-warm: 2
returned-rows: cold=5 warm=5
```

## MariaDB target 결과

로컬 개발 DB `sr_dev`에 `sr369_local` run key로 공개 baseline fixture를 생성한 뒤 config DB read-only 모드로 측정했고, 측정 후 같은 run key로 fixture를 삭제했다. 측정 시 기존 공개 게시판 1개와 fixture 게시판 20개가 함께 선택되어 `board_ids=21`이다.

```text
seed-summary: posts=10000 comments=3189 attachments=1196 boards=20 board_groups=1 elapsed_ms=4720.725
cleanup-summary: posts=10000 comments=3189 attachments=1196 boards=20 board_groups=1
fixture-step: target-readonly
fixture-size: board_ids=21
db-version: mysql 10.6.23-MariaDB-0ubuntu0.22.04.1
hosting-probe: config DB read-only measurement; production data is not required or recommended

query-id: community.home.latest
explain-summary: p scans PRIMARY index with Using where; attachment derived table scans 1196 rows with temporary/filesort
response-ms-cold: 3.975
response-ms-warm: 2.057
returned-rows: cold=10 warm=10

query-id: community.home.popular
explain-summary: p uses idx_sr_community_posts_status_updated range over estimated 5000 rows with filesort; attachment derived table scans 1196 rows with temporary/filesort
response-ms-cold: 19.078
response-ms-warm: 90.401
returned-rows: cold=5 warm=5
```

## 판정

- SQLite in-memory fixture 결과는 하니스 동작 증거로만 사용한다.
- MariaDB fixture 결과는 MySQL/MariaDB 문법과 실행계획 smoke 증거로만 사용한다.
- MariaDB target 결과는 반환 row가 있는 로컬 개발 DB 측정이므로 #369의 대표성 부족 상태는 해소했다.
- 인기글 쿼리는 10,000개 fixture에서 warm 90.401ms와 filesort가 관찰됐지만, 최신글 쿼리는 warm 2.057ms였다. 이 수치만으로 홈 피드 영속 cache table, DB row lock/refresh, write path generation bump, 홈 이전, 관리자 설정까지 바로 착수하지 않는다.
- 다음 구현 판단은 인기글 정렬/index 개선 또는 attachment 대표 이미지 derived table 축소처럼 좁은 쿼리 개선 후보를 먼저 비교한 뒤 결정한다.

## 후속

인기글 정렬과 대표 이미지 attachment join 비용을 별도 후보로 나눠 측정한다. 영속 cache table은 좁은 쿼리 개선 뒤에도 홈 피드 병목이 남는 경우에 다시 판단한다.
