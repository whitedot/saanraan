# 저장소 문서 기준

이 디렉터리는 산란 구현과 함께 버전이 맞아야 하는 기준 문서를 둔다. 사람 개발자가 현재 구현을 이해하기 위한 설명서와 화면/DB 명세는 GitHub Wiki를 우선한다.

## 문서 배치

`docs/` 루트에는 자주 참조하는 현재 기준 문서를 둔다. 구현 기능과 특장점 요약은 [산란 특장점 소개](operator-feature-list.md)를 기준으로 한다. 구현 전 계획은 `docs/plans/`, 일회성 점검 기록은 `docs/records/`에 둔다.

[산란 특장점 소개](operator-feature-list.md)는 회원·콘텐츠·커뮤니티·퀴즈·설문·리액션·자산·쿠폰·알림·개인정보·사이트 운영 기능이 제공하는 기능과 편의를 정리한다. DB 컬럼, 요청 매핑, 관리자 화면별 상세 필드는 Wiki 또는 [1.0 전 구현 스냅샷](implementation-snapshot.md)을 우선한다.

계획 문서가 실제 구현으로 바뀌면 완료된 기준은 관련 유지 문서나 모듈 README로 옮기고, 계획 문서는 삭제하거나 아직 남은 계획만 남긴다. 점검 기록은 같은 기준으로 반복 확인할 필요가 있는 경우에만 유지한다.

## 저장소에 남기는 문서

다음 성격의 문서는 `docs/`에 남긴다.

- 구현 판단이 흔들릴 때 우선하는 설계 결정
- 보안, 개인정보, DB 접근, 배포 보호처럼 코드 변경과 함께 검토해야 하는 정책
- 모듈 계약, 모듈 업데이트, 릴리스, 스모크 테스트처럼 PR과 배포 과정에서 확인해야 하는 기준
- 아직 구현 전인 기능 계획 문서
- 1.0 배포 전 Wiki 정리보다 먼저 맞춰야 하는 임시 구현 스냅샷

현재 유지 문서:

| 문서 | 성격 |
| --- | --- |
| [1.0 범위 잠금 기준](1.0-scope.md) | 1.0 전 안정화 범위와 제외 범위 |
| [1.0 전 구현 스냅샷](implementation-snapshot.md) | Wiki 구현 명세 정리 전 임시 핵심 표면 |
| [산란 특장점 소개](operator-feature-list.md) | 현재 구현 기능과 특장점 요약 |
| [산란 포지셔닝 기준](positioning.md) | 경쟁 범위와 사용 판단 기준 |
| [검증 상태와 증거 기준](verification-status.md) | 기능 목록을 운영 신뢰로 연결하기 위한 증거 기준 |
| [모듈 상태 등급](module-status.md) | 번들 모듈별 운영 신뢰 등급과 1.0 전 보강 기준 |
| [프로젝트 리스크 레지스터](risk-register.md) | 주요 공개 리스크와 완화 증거 |
| [릴리스 검증 기록 템플릿](release-verification-template.md) | 날짜별 검증 기록 작성 양식 |
| [핵심 설계 결정](core-decisions.md) | 최상위 설계 결정 |
| [모듈 작성 가이드](module-guide.md) | 모듈 계약과 작성 기준 |
| [모듈 배치와 업데이트 기준](module-update-policy.md) | 모듈 설치/업데이트 기준 |
| [커스터마이징과 업데이트 충돌 가이드](customization-guide.md) | 배포판 모듈 커스터마이징과 업데이트 병합 비용 |
| [DB 접근 정책](database-access-policy.md) | SQL 작성과 DB 접근 정책 |
| [외부 의존성 배치 기준](dependency-policy.md) | vendored asset과 선택 라이브러리 배치 기준 |
| [성능과 캐시 기준](performance-policy.md) | 공유호스팅 성능, 캐시 허용/금지 기준 |
| [성능 베이스라인 증거표](performance-baseline-evidence.md) | 관리자 목록, 캐시 경로, sitemap/export 상한 정적 기준 |
| [기여자 작업 기준](contribution-guide.md) | 변경 범위, 고위험 작업, 검증 증거 기준 |
| [산란 보안 모델](security-model.md) | 보안 책임 경계 |
| [보안 체크리스트](security-checklist.md) | 변경 검토 체크리스트 |
| [보안 제보와 처리 기준](security-response-policy.md) | 취약점 제보, triage, 수정 증거 기준 |
| [보안 베이스라인 증거표](security-baseline-evidence.md) | 자작 보안 컴포넌트 구현 위치와 자동 점검 연결 |
| [개인정보 계약 매트릭스](privacy-contract-matrix.md) | 번들 모듈별 개인정보 export/cleanup 책임과 보존 판단 |
| [Rich Text Sanitizer 정책](rich-text-sanitizer-policy.md) | HTML 본문 허용 태그와 속성 기준 |
| [배포 보호 기준](deployment-protection.md) | 운영 서버 직접 접근 차단 기준 |
| [nginx 샘플 설정](deployment/nginx-saanraan.conf) | PHP-FPM 기반 nginx 배포 예시 |
| [릴리스 절차](release-process.md) | 릴리스 준비와 배포 절차 |
| [스모크 테스트 기준](smoke-test.md) | 배포 전후 최소 검증 |
| [운영 상태 점검 기준](operational-status.md) | queue/cron/배치 지연 신호 확인 기준 |
| [사이트 초기화와 더미 데이터 기준](site-reset-and-fixtures.md) | QA 환경 초기화와 실제 등록 경로 기반 더미 데이터 기준 |
| [관리자 UI 작성 기준](admin-ui-guide.md) | 관리자 화면 공통 UI 작성 기준 |
| [관리자 목록 컬럼 기준](admin-list-columns.md) | 관리자 목록별 노출 컬럼과 좁은 화면 기준 |

## 루트 보조 문서

루트에는 저장소 전체를 처음 보는 사람이 바로 확인해야 하거나, 작업 중 자주 여는 보조 문서를 둔다.

현재 루트 보조 문서:

| 문서 | 성격 |
| --- | --- |
| [수동 화면 점검 체크리스트](../manual-check.md) | 브라우저 수동 확인용 살아있는 체크리스트 |

`manual-check.md`는 특정 날짜의 결과 기록이 아니라 다음 점검 때 다시 사용하는 작업 목록이므로 `docs/records/`로 옮기지 않는다. 수동 점검을 완료해 날짜별 결과를 남겨야 할 때는 별도 기록 문서를 `docs/records/`에 추가한다.

## 모듈/예제 문서

특정 모듈이나 예제에만 적용되는 문서는 해당 폴더 안에 둔다.

현재 모듈/예제 문서:

- [sample_module README](../examples/sample_module/README.md)
- [content README](../modules/content/README.md)
- [ckeditor README](../modules/ckeditor/README.md)

## 계획/정합성 문서

구현 전 계획, 구현 후에도 유지할 정합성 기준, 후속 범위를 분리해 둔 문서는 `docs/plans/`에 보관한다. 구현이 완료되어 현재 기준으로 굳어진 내용은 관련 유지 문서나 모듈 README로 옮기고, 계획 문서에는 남은 판단 기준이나 후속 범위만 남긴다.

현재 계획/정합성 문서:

- [본인확인 플러그인 계획](plans/identity-verification-plugin-plan.md)
- [회원 마이그레이션 계획](plans/member-migration-plan.md)
- [결제 플러그인 계획](plans/payment-plugin-plan.md)
- [마일스톤 2 퀴즈 보상 모듈 정합성 평가](plans/quiz-reward-module-plan.md)
- [마일스톤 13 읽기 참조 계약 계획](plans/read-reference-contract-plan.md)
- [보상 부정 수급 공통 기준 초안](plans/reward-abuse-common-standards.md)
- [설문 모듈 MVP/의존성 지도](plans/survey-module-mvp-map.md): 구현 완료 후에도 설문 보상/통계/개인정보 정합성 기준을 빠르게 확인하기 위해 유지

## 점검 기록

일회성 브라우저 확인, 화면 감사, 릴리스 후보 점검처럼 특정 시점의 기록은 `docs/records/`에 둔다.

현재 점검 기록:

- [관리자 화면 레이아웃 점검 기록 - 2026-05-18](records/admin-layout-audit-2026-05-18.md)
- [자산 시스템 운영/보안/정합성 점검 기록 - 2026-05-28](records/asset-system-risk-review-2026-05-28.md)
- [이슈 #42 전체 정합성 점검 기록 - 2026-05-28](records/issue-42-full-review-2026-05-28.md)
- [릴리스 게이트 점검 기록 - 2026-05-31](records/release-gate-check-2026-05-31.md)
- [마일스톤 4 링크 카드 구현 기록 - 2026-06-01](records/milestone-4-link-card-2026-06-01.md)
- [마일스톤 8 콘텐츠·커뮤니티 운영 정합성 구현 기록 - 2026-06-01](records/milestone-8-community-category-2026-06-01.md)
- [마일스톤 8 리액션 범위 결정 - 2026-06-01](records/milestone-8-reaction-decision-2026-06-01.md)
- [마일스톤 10 기능 테스트 결과 기록 - 2026-06-02](records/milestone-10-test-results-2026-06-02.md)
- [마일스톤 11 정합성 테스트 결과 기록 - 2026-06-02](records/milestone-11-consistency-test-results-2026-06-02.md)
- [마일스톤 15 QA 실행 기록 - 2026-06-03](records/milestone-15-qa-results-2026-06-03.md)
- [마일스톤 16 운영 항목 복사 구현 기록 - 2026-06-04](records/milestone-16-operational-copy-2026-06-04.md)
- [이슈 #209 게시판·콘텐츠 운영 단위 삭제 기록 - 2026-06-04](records/issue-209-domain-delete-2026-06-04.md)
- [이슈 #210 CKEditor 업로드 파일 관리 정책 점검 기록 - 2026-06-05](records/issue-210-ckeditor-upload-policy-2026-06-05.md)
- [이슈 #214 콘텐츠 커버 이미지 구현 기록 - 2026-06-06](records/issue-214-content-cover-image-2026-06-06.md)
- [이슈 #221 알림 이벤트 검토 기록 - 2026-06-06](records/issue-221-notification-events-2026-06-06.md)
- [마일스톤 18-20 보상·퀴즈·설문 구현 기록 - 2026-06-07](records/milestone-18-20-reward-quiz-survey-2026-06-07.md)
- [이슈 #260 관리자 독립 form 조사 및 안내 계획 - 2026-06-08](records/issue-260-admin-independent-forms-2026-06-08.md)
- [이슈 #261 긴 관리자 폼 섹션 내비게이션 계획 - 2026-06-08](records/issue-261-long-admin-forms-2026-06-08.md)
- [이슈 #303 삭제 글 댓글 보존 정책 점검 - 2026-06-10](records/issue-303-delete-retention-policy-2026-06-10.md)
- [이슈 #115 settlement 기반 복합 차감 계약 결정 - 2026-06-11](records/issue-115-settlement-contract-2026-06-11.md)
- [이슈 #326 공통 리액션 정책 결정 - 2026-06-11](records/issue-326-reaction-policy-2026-06-11.md)
- [마일스톤 28 통화·정산 정책 기록 - 2026-06-11](records/milestone-28-currency-settlement-policy-2026-06-11.md)
- [개선 보강 검증 기록 - 2026-06-11](records/improvement-hardening-verification-2026-06-11.md)
- [HTML Purifier 배포 방식 결정 - 2026-06-11](records/htmlpurifier-packaging-decision-2026-06-11.md)

## Wiki로 충분한 문서

다음 성격의 문서는 GitHub Wiki를 우선한다.

- 개발자 온보딩과 설명형 가이드
- 현재 DB 스키마 명세
- 관리자 화면별 항목 설명
- 요청 흐름, 관리자 화면, DB, 보안/개인정보, 배포/운영을 설명하는 개발자 참조
- 문제 해결과 운영 중 참고 문서

Wiki 문서는 현재 구현 상태를 설명한다. 다만 1.0 배포 전 개발 흐름에서는 저장소 `docs/`의 기준 문서를 먼저 맞추고, DB 명세와 관리자 화면별 설명 같은 Wiki 구현 명세는 1.0 배포 정리 이슈에서 한 번에 맞춘다. 그 전까지는 [1.0 전 구현 스냅샷](implementation-snapshot.md)을 임시 참조로 유지한다.

현재 Wiki 정리 추적 이슈:

- [#34 docs: 1.0 배포 시점 Wiki 구현 명세 정리](https://github.com/whitedot/saanraan/issues/34)

1.0 전까지 Wiki는 일부 구현보다 늦을 수 있다. 코드 변경으로 보안/개인정보/요청 흐름/모듈 계약 같은 기준이 달라지는 경우에는 저장소 `docs/`를 먼저 갱신하고, Wiki 갱신 필요성은 해당 이슈나 작업 기록에 남긴다.
