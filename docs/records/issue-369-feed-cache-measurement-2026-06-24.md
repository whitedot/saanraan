# 이슈 369 커뮤니티 홈 피드 측정 하니스 기록

작성일: 2026-06-24

관련 이슈: #369

## 목적

영속 feed cache table을 만들기 전에 현재 통합 홈 피드 쿼리의 비용을 fixture/EXPLAIN 형식으로 기록할 수 있는 하니스를 추가했다. 하니스는 실제 홈 최신글/인기글 조회와 같은 feed query helper를 사용한다. 이 기록은 SQLite fixture 결과이며, MySQL/MariaDB 로컬 또는 staging 측정을 대체하지 않는다.

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

## 판정

- 이 결과는 SQLite in-memory fixture 증거일 뿐이며 영속 cache table 구현 착수 근거가 아니다.
- MariaDB fixture 결과는 MySQL/MariaDB 문법과 실행계획 smoke 증거이며, 운영 또는 대표 staging 데이터 측정을 대체하지 않는다.
- 홈 피드 영속 cache table, DB row lock/refresh, write path generation bump, 홈 이전, 관리자 설정은 대표 MySQL/MariaDB 로컬 또는 staging 데이터에서 통합 쿼리 이후에도 병목이라는 증거가 나온 뒤 진행한다.
- 측정 전에는 이미 추가한 key/context helper, card snapshot helper, snapshot contract checker처럼 schema 없는 작업만 선행 범위로 둔다.

## 후속

대표 MySQL/MariaDB 환경에서 같은 하니스의 명시 DSN 모드 또는 동등 SQL로 #361 기록 형식의 `EXPLAIN`, cold/warm 응답 시간, 반환 row 수를 남긴다.
