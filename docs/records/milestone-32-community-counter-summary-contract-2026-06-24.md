# 마일스톤 32 커뮤니티 카운터·summary 계약

작성일: 2026-06-24

관련 이슈: #360

## 목적

커뮤니티 대용량 화면에서 반복 count가 병목 후보로 보이더라도 모든 count를 하나의 summary로 묶지 않는다. 이 기록은 계열별로 채택, 보류, 측정 의존 여부, 저장 위치 후보, write path 책임, drift 조정 기준을 분리해 후속 구현 이슈를 작게 나누기 위한 계약이다.

## 공통 원칙

- 코어나 회원 테이블에 커뮤니티 성능용 컬럼을 추가하지 않는다.
- 커뮤니티 도메인 count는 커뮤니티 모듈이 소유한다.
- 측정 의존 계열은 #361의 fixture/EXPLAIN 결과 없이 채택으로 확정하지 않는다.
- 인덱스 추론 기반으로 충분할 가능성이 높은 계열은 summary/counter를 보류하고 기존 쿼리와 인덱스 기준을 먼저 확인한다.
- counter를 채택하면 원본 write와 같은 transaction에서 갱신하는 것을 기본값으로 한다.
- 원본 write와 counter write를 같은 transaction에 넣을 수 없으면 작업 테이블형 reconciliation 또는 관리자 수동 재조정 경로를 함께 설계한다.
- drift 조정은 공유호스팅에서 cron이 없을 수 있으므로 관리자 수동 실행과 bounded batch를 기본 경로로 둔다.
- 신규 고부하 작업은 `docs/core-decisions.md`의 작업 테이블형 계약을 따른다. lock token, cursor/map, 대상 단위 멱등성, drift 기준, 감사 metadata를 명시하지 않는 장시간 재조정은 허용하지 않는다.

## 계열별 결정

| 계열 | 판단 | 근거 | 후속 |
| --- | --- | --- | --- |
| account-scoped counter | 보류 | 내 글/내 댓글, 레벨/그룹 rule은 `author_account_id`, `status`, account 기준 인덱스로 해결 가능성이 높다. | #361 측정 의존 채택으로 묶지 않고, 실제 느린 화면이 확인되면 인덱스 또는 좁은 helper부터 검토한다. |
| entity counter | 측정 전 보류 | post별 published comment, active attachment, series active item은 write path와 drift가 핵심이다. | #361의 per-post/per-series count와 상태 술어 인덱스 적합성 측정 뒤 채택 여부를 결정한다. |
| global/status counter 또는 TTL cache | 측정 전 보류 | dashboard 전역 count, reports 상태 count, 관리자 posts/comments 상태 count는 반복 호출될 수 있지만 단순 인덱스로 충분할 수 있다. | #361 status/global count 측정 뒤 TTL cache, counter, 인덱스 보강 중 하나로 분류한다. |
| message unread counter | 보류 | unread count는 `recipient_account_id`, `recipient_deleted_at`, `read_at IS NULL` 조건과 읽음/삭제 전이가 핵심이며 게시글 activity와 별도다. | #361 unread count 측정에서 인덱스로 부족한 경우에만 별도 unread counter 구현 이슈로 분리한다. |

## 계열 1: Account-Scoped Counter

대상:

- 좌측 사이드바 내 글/내 댓글
- 레벨/그룹 rule
- 계정 활동 기반 표시

현재 결정:

- counter를 채택하지 않는다.
- `author_account_id`, `status`, `id` 또는 생성/갱신 시각 계열 인덱스로 먼저 해결한다.
- 기능별 표시가 느려진다는 설치 DB 증거가 생기면 해당 화면의 helper와 인덱스만 좁게 검토한다.

채택 조건:

- account 기준 count가 하나의 요청에서 반복 호출되고, 인덱스가 있어도 fixture에서 병목으로 확인된다.
- 표시 최신성 요구가 명확하고, write path가 게시글/댓글 저장 transaction과 함께 갱신될 수 있다.

보류 사유:

- 이 계열은 계정별 조회 범위가 좁아 summary를 먼저 만들면 write path와 reconciliation 비용이 조회 비용보다 커질 가능성이 높다.

## 계열 2: Entity Counter

대상:

- post별 `published_comment_count`
- post별 `active_attachment_count`
- series별 `active_item_count`

현재 결정:

- #361 측정 전에는 채택하지 않는다.
- 상태 술어 포함 count가 현재 인덱스를 타는지 먼저 확인한다.
- `(post_id, status)`, `(series_id, item_status)` 복합 인덱스 또는 목록 단위 derived aggregate로 충분한지 먼저 본다.

채택 조건:

- 복합 인덱스와 derived aggregate로도 목록 row별 count 비용이 큰 것이 확인된다.
- 댓글 publish/hide/delete, 첨부 active/remove, 시리즈 아이템 active/hidden/removed 전이가 모두 같은 transaction에서 counter를 갱신할 수 있다.
- drift 조정용 bounded recalculation과 관리자 수동 재조정이 함께 설계된다.

저장 위치 후보:

- post별 count는 `sr_community_posts` 컬럼 후보로만 검토한다.
- series별 count는 `sr_community_series` 컬럼 후보로만 검토한다.
- 별도 범용 counter 테이블은 도메인 정책과 drift 책임이 흐려지므로 기본 후보에서 제외한다.

Reconciliation 기준:

- post/series id cursor 기반 bounded batch
- batch당 처리 상한
- 원본 count와 저장 count 불일치 수, 수정 수, 남은 수 기록
- 관리자 실행자와 실행 시각 감사 metadata

## 계열 3: Global/Status Counter 또는 TTL Cache

대상:

- dashboard 전역 count
- reports 상태 count
- 관리자 posts/comments 상태별 count

현재 결정:

- #361 측정 전에는 counter 또는 TTL cache를 채택하지 않는다.
- #357에서 이미 pagination count join을 줄였으므로 status-only/global count는 별도 표면으로 분리한다.
- 단순 `GROUP BY status`와 상태별 인덱스로 충분하면 구현하지 않는다.

TTL cache 후보 조건:

- count 최신성이 초 단위 실시간일 필요가 없고, 관리자 summary 표시 목적에 한정된다.
- cache key가 모듈, count type, 필터 없는 status scope로 좁다.
- 권한/개인정보/CSRF가 섞인 HTML을 캐시하지 않는다.

Counter 후보 조건:

- TTL cache로도 반복 비용이 크고, write path가 단순 상태 전이에 집중되어 있다.
- 상태 전이 action이 모두 명확하고 누락 없이 같은 transaction에서 갱신할 수 있다.

보류 사유:

- global/status count는 drift가 생기면 운영자 summary 신뢰도를 떨어뜨린다.
- 대시보드 표시는 편의 기능이므로 write path 복잡도를 키우기 전에 측정이 필요하다.

## 계열 4: Message Unread Counter

대상:

- `recipient_account_id`
- `read_at IS NULL`
- `recipient_deleted_at IS NULL`

현재 결정:

- 게시글/댓글 activity와 별도 계열로 유지한다.
- #361 unread count 측정 전에는 counter를 채택하지 않는다.
- 읽음 처리와 수신자 삭제 전이가 포함되므로 TTL cache보다 인덱스 또는 명시적 unread counter 중 하나로 판단한다.

채택 조건:

- 인덱스만으로 unread count가 부족하다는 fixture 결과가 있다.
- message send, read, recipient delete, cleanup이 모두 counter 갱신 지점을 제공한다.
- 읽음/삭제 처리 실패 시 counter drift를 복구할 recalculation 경로가 있다.

## #359와의 계약

#359가 전역 top-N 홈 피드를 선택하면 이 계약과 독립적으로 진행할 수 있다.

#359가 게시판 균형 샘플을 유지하고 board별 summary/cache가 필요하면 다음 기준을 따른다.

- 영속 summary/counter는 이 계약에서 소유와 최신성 정책을 확정한 뒤 구현한다.
- 단기 요청 단위 cache 또는 짧은 TTL cache는 public 권한, secret post, 유료 읽기 제한을 침범하지 않는 범위에서만 검토한다.
- board별 summary가 게시글/댓글 write path를 건드리면 계열 2 또는 계열 3과 같은 reconciliation 기준을 적용한다.

## #362와의 계약

#362의 view_count merge는 위 네 계열과 형태가 다르다. 하지만 누적 상태 저장 위치, 병합 trigger, drift, cron 없는 공유호스팅 재조정 원칙은 이 계약과 충돌하지 않아야 한다.

- view_count 영속 accumulator 확정은 이 계약의 누적/병합/reconciliation 원칙을 따른다.
- deferred merge를 채택하면 #359 인기 정렬과 #361 조회순 측정의 staleness 허용 범위를 함께 기록한다.
- board copy, board delete, privacy cleanup은 이 계약에 막히지 않지만, 대량 recalculation이나 merge가 필요해지면 `docs/core-decisions.md`의 작업 테이블형 기준을 따른다.

## 문서와 스키마 영향

현재 기록은 설계 계약이므로 스키마 변경을 포함하지 않는다.

후속에서 counter 컬럼이나 cache table을 채택하면 같은 작업에서 다음을 갱신한다.

- 설치 SQL과 update SQL
- DB specification 또는 구현 snapshot 문서
- 관리자 화면 field guide 또는 운영 문서
- 성능 기준 문서
- reconciliation 또는 수동 재조정 절차
- `php .tools/bin/check.php`가 확인할 수 있는 정적 marker 또는 runtime fixture

## 검증

이 작업은 설계 기록만 추가하므로 `git diff --check`를 최소 검증으로 삼는다. 스키마, helper, 관리자 화면, 작업 테이블을 추가하는 후속 작업에서는 `php .tools/bin/check.php`와 가능한 HTTP smoke를 실행한다.
