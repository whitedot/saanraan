# 이슈 369 커뮤니티 홈 피드 측정 하니스 기록

작성일: 2026-06-24

관련 이슈: #369

## 목적

영속 feed cache table을 만들기 전에 현재 통합 홈 피드 쿼리의 비용을 fixture/EXPLAIN 형식으로 기록할 수 있는 하니스를 추가했다. 이 기록은 SQLite fixture 결과이며, MySQL/MariaDB 로컬 또는 staging 측정을 대체하지 않는다.

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

## 판정

- 이 결과는 SQLite in-memory fixture 증거일 뿐이며 영속 cache table 구현 착수 근거가 아니다.
- 홈 피드 영속 cache table, DB row lock/refresh, write path generation bump, 홈 이전, 관리자 설정은 대표 MySQL/MariaDB 로컬 또는 staging 데이터에서 통합 쿼리 이후에도 병목이라는 증거가 나온 뒤 진행한다.
- 측정 전에는 이미 추가한 key/context helper, card snapshot helper, snapshot contract checker처럼 schema 없는 작업만 선행 범위로 둔다.

## 후속

대표 MySQL/MariaDB 환경에서 같은 하니스의 명시 DSN 모드 또는 동등 SQL로 #361 기록 형식의 `EXPLAIN`, cold/warm 응답 시간, 반환 row 수를 남긴다.
