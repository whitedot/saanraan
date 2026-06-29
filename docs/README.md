# 저장소 문서 안내

이 디렉터리는 산란을 소개하고, 설치·배포·기여·모듈 개발에 필요한 문서와 릴리스 판단에 필요한 검증 기준 문서를 둔다. 현재 구현과 직접 연결되는 상태표, 검증 기준, 리스크 문서는 저장소에 두고, 새 기능 제안과 후속 작업은 GitHub 이슈와 마일스톤에서 관리한다.

## 읽는 순서

| 목적 | 문서 |
| --- | --- |
| 프로젝트 소개 | [산란 특장점 소개](operator-feature-list.md), [산란 포지셔닝 기준](positioning.md) |
| 설계 방향 | [핵심 설계 결정](core-decisions.md) |
| 설치와 배포 | [배포 보호 기준](deployment-protection.md), [nginx 샘플 설정](deployment/nginx-saanraan.conf), [nginx 하위 경로 샘플 설정](deployment/nginx-saanraan-subdirectory.conf) |
| 기여와 개발 | [기여자 작업 기준](contribution-guide.md), [모듈 작성 가이드](module-guide.md), [스킨·테마 패키지 기준](skin-theme-packages.md), [모듈 배치와 업데이트 기준](module-update-policy.md), [커스터마이징과 업데이트 충돌 가이드](customization-guide.md) |
| 관리자 화면 작성 | [관리자 UI 작성 기준](admin-ui-guide.md), [관리자 목록 컬럼 기준](admin-list-columns.md), [모듈을 가로지르는 운영 흐름 가이드](cross-module-operations-guide.md) |
| 보안과 정책 | [산란 보안 모델](security-model.md), [보안 체크리스트](security-checklist.md), [보안 제보와 처리 기준](security-response-policy.md), [DB 접근 정책](database-access-policy.md), [외부 의존성 배치 기준](dependency-policy.md) |
| 운영 정책 | [성능과 캐시 기준](performance-policy.md), [개인정보 처리활동 기록 기준](privacy-processing-records.md), [Rich Text Sanitizer 정책](rich-text-sanitizer-policy.md) |
| 검증과 리스크 | [모듈 상태표](module-status.md), [검증 상태와 증거 기준](verification-status.md), [프로젝트 리스크 레지스터](risk-register.md), [릴리스 검증 기록 템플릿](release-verification-template.md), [릴리스 절차](release-process.md), [운영 상태 점검 기준](operational-status.md), [Smoke 테스트 기준](smoke-test.md) |
| 정책 기록 | [마일스톤 28 통화·정산 정책 기록 - 2026-06-11](records/milestone-28-currency-settlement-policy-2026-06-11.md) |

## 문서 운영 기준

- 프로젝트의 방향, 사용 방법, 기여 방법, 배포와 보안 기준을 이해하는 데 필요한 문서를 저장소에 둔다.
- 기능 계획, 후속 작업, 마일스톤별 진행 상황은 GitHub 이슈와 마일스톤에서 관리한다.
- 릴리스 판단에 필요한 상태 등급, 검증 증거 수준, 리스크, smoke 기준은 저장소 문서와 `docs/records/`의 검증 기록으로 남긴다.
- 일회성 점검 기록은 검증 기준에 연결되는 경우에만 `docs/records/`에 남기고, 구현 상태 설명은 현재 코드와 저장소 `docs/`를 우선한다.
- DB 명세, 관리자 화면별 항목 설명, 운영 중 참고 문서는 현재 저장소 `docs/`를 기준으로 관리한다. 1.0 배포 전에는 GitHub Wiki를 운영 문서의 정본으로 사용하지 않는다.
