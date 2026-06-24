# 마일스톤 32 커뮤니티 대용량 쿼리 측정 계획

작성일: 2026-06-24

관련 이슈: #361

## 목적

이 기록은 커뮤니티 대용량 운영 후보를 바로 스키마, summary, cache로 키우기 전에 로컬 또는 staging fixture에서 확인할 측정 항목과 판단 기준을 고정한다. production 데이터와 release-sensitive credential은 사용하지 않는다.

## 실행 순서

1. 호스팅 능력 probe를 먼저 수행한다.
2. fixture 크기와 데이터 분포를 기록한다.
3. 쿼리별 `EXPLAIN`, 응답 시간, 반환 row 수, 후보 row 수를 같은 형식으로 남긴다.
4. 측정 결과를 인덱스, 기존 쿼리 경량화, TTL cache, summary/counter, keyset pagination, 검색 정책 중 하나로 분류한다.
5. #360 counter/summary 계약은 이 기록의 관련 측정 결과를 근거로 삼되, 측정 의존 계열과 인덱스 추론 기반 보류 계열을 구분한다.

## 호스팅 능력 probe

검색 품질을 측정하기 전에 다음을 먼저 확인한다.

- MySQL 또는 MariaDB 버전
- InnoDB FULLTEXT 지원 여부
- ngram parser 지원 여부
- `ngram_token_size`, `innodb_ft_min_token_size`, `innodb_ft_server_stopword_table` 같은 FULLTEXT 설정 조회/변경 가능 여부
- 커뮤니티 게시글 제목/본문 후보 테이블에 FULLTEXT 또는 ngram FULLTEXT 인덱스를 생성할 수 있는 권한
- 공유호스팅 운영 환경에서 인덱스 생성 작업이 허용되는 시간과 lock 영향

ngram/FULLTEXT 배포가 불가능하면 LIKE 기반 범위 축소, normalized search helper, prefix 검색, 외부 검색 연동 같은 대안을 후속 후보로 남긴다.

## Fixture 기준

최소 세 단계 fixture를 둔다.

| 단계 | 게시글 | 댓글 | 첨부 | 신고/로그 | 시리즈 |
| --- | ---: | ---: | ---: | ---: | ---: |
| small | 10,000 | 30,000 | 10,000 | 5,000 | 500 series / 5,000 items |
| medium | 100,000 | 300,000 | 100,000 | 50,000 | 5,000 series / 50,000 items |
| large | 500,000 | 1,000,000 이상 | 500,000 | 200,000 이상 | 20,000 series / 200,000 items |

각 fixture는 다음 분포를 포함한다.

- board별 글 수가 균등한 경우와 특정 board가 대부분을 차지하는 경우
- `published`, `hidden`, `deleted`, `pending` 상태가 섞인 게시글
- `published`, `hidden`, `deleted` 상태가 섞인 댓글
- active 이미지, active 비이미지, inactive 이미지 첨부가 섞인 첨부
- 신고 상태별 분포와 숫자 검색/문자 검색이 모두 가능한 reward log
- active/hidden/removed 시리즈 아이템

## 기록 형식

각 쿼리는 다음 형식으로 기록한다.

```text
query-id:
fixture-step:
fixture-size:
db-version:
hosting-probe:
sql-summary:
bind-summary:
index-candidates:
explain:
rows-examined-or-estimate:
response-ms-cold:
response-ms-warm:
returned-rows:
decision:
follow-up:
```

`EXPLAIN ANALYZE`를 사용할 수 있으면 함께 기록하되, 공유호스팅에서 사용할 수 없으면 일반 `EXPLAIN`과 wall time을 남긴다.

## 측정 대상

### Public 게시판 목록

- 기본 최신순
- 카테고리 필터
- 키워드 검색
- 조회순
- 댓글순
- 후반 페이지 offset
- 대표 이미지 조회가 필터 후 첫 active image attachment를 유지하는지

판단 기준:

- `(board_id, status, id)` 또는 `(board_id, category_id, status, id)` 계열 인덱스로 최신순이 충분하면 schema/cache를 늘리지 않는다.
- 후반 페이지 비용이 커지면 keyset pagination 후보로 넘긴다.
- 댓글순/조회순이 hot counter나 summary를 요구하면 #360/#362 결정과 연결한다.

### 전체 검색과 게시판 키워드 검색

- 제목 검색
- 본문 포함 검색
- 2자, 3자, 5자 한국어 검색어
- secret post 본문 제외 조건
- board scope가 있는 검색과 없는 검색

판단 기준:

- ngram/FULLTEXT가 호스팅 능력 probe에서 불가능하면 FULLTEXT 품질 측정을 후순위로 내리고 LIKE 기반 범위 축소 또는 외부 검색 대안을 기록한다.
- 2자 검색어가 품질 또는 비용 기준을 만족하지 못하면 최소 검색어 길이 정책 후보로 넘긴다.

### 관리자 posts/comments 목록

- 기본 목록
- status 필터
- board 필터
- 전체 검색
- field별 검색: 제목, 본문, 작성자, 게시판, 추가 필드
- 후반 페이지 offset

판단 기준:

- #357에서 줄인 count helper와 list helper의 alias, 필터, pagination 총계가 맞는지 함께 본다.
- status-only/global count는 #360 global/status counter 또는 TTL cache 후보와 중복되므로 과투자하지 않는다.
- field 검색이 특정 alias나 `EXISTS` 조건을 요구하면 count/list 양쪽의 실행 계획을 함께 기록한다.

### reports/series/publisher rewards 관리자 목록

- 기본 목록
- status 필터
- 문자 검색
- 숫자 검색
- 후반 페이지 offset

판단 기준:

- 숫자 검색이 base table 인덱스만으로 충분하면 join 또는 summary 후보로 넘기지 않는다.
- 문자 검색이 post title, attachment original name, board title, owner account를 참조할 때만 관련 join 비용을 본다.

### Status/global count

- comments `GROUP BY status`
- dashboard 전역 count
- reports 상태 count
- 관리자 posts/comments 상태별 count

판단 기준:

- 단일 status/global count가 반복 호출되어 병목이면 #360의 global/status TTL cache 또는 counter 후보로 넘긴다.
- 단순 인덱스 추가로 충분하면 summary/counter를 보류한다.

### Entity counter 후보

- post별 `published_comment_count`
- post별 `active_attachment_count`
- series별 `active_item_count`
- 목록 row별 반복 subquery와 group aggregate/derived table 비교
- 상태 술어 포함 count가 현재 인덱스를 타는지
- `(post_id, status)`, `(series_id, item_status)` 같은 복합 인덱스 추가만으로 counter 없이 충분한지

판단 기준:

- 상태 술어 포함 count가 복합 인덱스로 충분하면 counter 도입을 보류한다.
- 복합 인덱스와 derived table로도 목록 비용이 크면 #360 entity counter 채택 후보로 넘긴다.
- counter 후보로 넘길 때는 write path, drift, reconciliation, cron 없는 수동 재조정 경로가 함께 필요하다.

### 쪽지 unread count

- `recipient_account_id`
- `read_at IS NULL`
- `recipient_deleted_at IS NULL`
- 읽음 처리와 수신자 삭제 전후 비용

판단 기준:

- 인덱스만으로 충분하면 unread counter를 보류한다.
- counter가 필요하면 message unread counter는 게시글/댓글 activity counter와 별도 계열로 유지한다.

## 후속 분류 기준

| 결과 | 후속 |
| --- | --- |
| 현재 인덱스로 충분 | 구현 없음 또는 쿼리 경량화만 진행 |
| 복합 인덱스로 충분 | DB specification/update SQL 후보 작성 |
| 후반 페이지 offset 병목 | keyset pagination 후보 |
| status/global 반복 count 병목 | #360 global/status TTL cache 또는 counter 후보 |
| per-entity 반복 count 병목 | #360 entity counter 후보 |
| 검색 품질/비용 불충분 | 검색 정책 또는 검색 인덱스 후보 |
| 공유호스팅 능력 부족 | LIKE fallback 또는 외부 검색 대안 |

## 검증

이 계획은 문서 기준 작업이므로 변경 시 최소 `git diff --check`를 실행한다. 측정 도구나 fixture script를 추가하는 후속 작업에서는 `php .tools/bin/check.php` 또는 관련 verifier를 함께 실행한다.
