# 마일스톤 32 커뮤니티 대용량 운영 성능 분해 완료 점검

## 후속 이슈 처리 상태

| 이슈 | 처리 | 증거 |
| --- | --- | --- |
| #357 관리자 count join 최소화 | 구현 완료 | `11c91214 fix: 커뮤니티 관리자 count join 최소화 #357` |
| #358 목록 대표 이미지 조회 통합 | 구현 완료 | `1a920a59 fix: 커뮤니티 목록 대표 이미지 조회 통합 #358` |
| #359 홈 피드 의미 결정 | 구현 완료 | `0e7c6806 feat: 커뮤니티 홈 피드를 전역 top-N으로 정리 #359`, `docs/records/milestone-32-community-home-feed-decision-2026-06-24.md` |
| #360 counter/summary 계약 | 설계 기록 완료 | `aaf00710 docs: 커뮤니티 카운터 summary 계약 정리 #360`, `docs/records/milestone-32-community-counter-summary-contract-2026-06-24.md` |
| #361 EXPLAIN/fixture 측정 계획 | 측정 계획 완료 | `ffed2163 docs: 커뮤니티 대용량 쿼리 측정 계획 정리 #361`, `docs/records/milestone-32-community-query-measurement-plan-2026-06-24.md` |
| #362 고부하 작업 경로 | 구현/기록 완료 | `bd88f959 fix: 커뮤니티 고부하 복사 작업 경로 정리 #362`, `docs/records/milestone-32-community-high-load-paths-2026-06-24.md` |
| #363 sitemap/privacy export | 구현/기록 완료 | `885d6d16 fix: 커뮤니티 sitemap과 privacy export 정확성 보강 #363`, `docs/records/milestone-32-community-sitemap-privacy-export-2026-06-24.md` |
| #364 마일스톤 점검 | 이 문서 | `docs/records/milestone-32-community-completion-check-2026-06-24.md` |

GitHub issue 상태는 별도 명시 없이 변경하지 않는 저장소 운영 규칙에 따라 닫지 않았다. 이 기록은 로컬 구현, 설계 판단, 검증 증거 기준의 완료 점검이다.

## 게이트 확인

- #361 -> #360: #361은 hosting capability probe, ngram/LIKE 대안, 상태 술어 인덱스 적합성, entity counter 후보 측정을 포함한다. #360은 측정 의존 계열을 채택 확정하지 않고 hold로 남겼다.
- #360 -> #359 옵션 2: #359는 전역 top-N을 선택해 board별 summary/cache substrate가 필요하지 않다.
- #357 <-> #360: #357은 pagination count의 join 최소화에 집중했고, status-only/global counter 판단은 #360 기록으로 분리했다.
- #362 view_count -> #360: view_count deferred merge는 이번 마일스톤에서 채택하지 않았다. 영속 accumulator는 #360 원칙 확장 없이는 만들지 않는다.

## 정확성 변경 기록

- #357: join 제거는 필터에 필요한 alias만 남기는 방식이다. 1:N attachment join을 count에서 제거한 경우는 pagination count inflation을 피하기 위한 정정이며, list/count 정합성은 같은 helper의 query part 기준으로 확인했다.
- #358: 대표 이미지는 활성 이미지 MIME으로 필터링한 뒤 `MIN(id)`를 선택한다. 파생 테이블과 attachment join은 모두 `LEFT JOIN`이라 이미지 없는 글이 사라지지 않는다.
- #363: sitemap SQL prefilter는 익명 public 권한 프로필 기준의 `status = 'enabled'`와 `read_policy = 'public'`만 `LIMIT 1000` 이전에 적용한다. PHP `sr_community_account_can_read_board(..., null)` 최종 게이트는 유지한다.
- #363: privacy export는 섹션별 1000건 상한을 유지하되 1001번째 row를 감지해 `_limits.{section}.has_more`로 후속 대응 필요성을 표시한다.
- #362/#363/#8: privacy cleanup과 privacy export는 #8 개인정보 계약 매트릭스의 community `export_cleanup` 정의를 따른다. 별도 개인정보 컬럼 정의는 만들지 않았다.

## 후속 판단

- #360 entity/global/message counter 계열은 실제 fixture/EXPLAIN 측정 전에는 schema 또는 영속 summary를 추가하지 않는다.
- #362 게시판 삭제의 `deleting` 상태 + batch cleanup 전환, 개인정보 cleanup의 작업 테이블형 전환, view_count deferred merge는 이번 마일스톤에서 채택하지 않았다. 필요하면 별도 이슈로 분리한다.
- #361은 측정 계획 수립 이슈이므로 실제 설치 DB EXPLAIN 결과 수집은 후속 실행 작업이다.

## 검증

각 구현 작업 단위에서 다음 검사를 실행했다.

- `php .tools/bin/check.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:8087 php .tools/bin/smoke-http.php`
- #363 타깃 검사: `php .tools/bin/check-privacy-export-runtime.php`
- #362 타깃 검사: `php .tools/bin/check-community-board-copy-limits.php`, `php .tools/bin/check-community-board-copy-job-lock.php`

마지막 전체 점검에서도 `php .tools/bin/check.php`와 HTTP smoke가 통과했다.

## 리뷰 후속

정밀 리뷰에서 게시판 full copy job 경로의 선택 스키마 호환성과 verify 단계 처리 상한 누락을 발견했다. 후속 커밋에서 category table guard와 verify batch marker를 추가했다.

추가 리뷰에서 cleanup 실패 map이 storage 참조를 잃으면 정리 재시도가 같은 파일을 다시 찾을 수 없는 문제를 발견했다. 후속 커밋에서 실패 상태의 storage driver/key 보존과 회귀 marker를 추가했다.
