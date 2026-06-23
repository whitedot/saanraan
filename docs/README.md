# 저장소 문서 안내

이 디렉터리는 산란을 소개하고, 설치·배포·기여·모듈 개발에 꼭 필요한 문서만 둔다. 현재 상태 점검, 검증 기록, 구현 전 계획은 저장소 문서로 누적하지 않고 GitHub 이슈와 마일스톤에서 관리한다.

## 읽는 순서

| 목적 | 문서 |
| --- | --- |
| 프로젝트 소개 | [산란 특장점 소개](operator-feature-list.md), [산란 포지셔닝 기준](positioning.md) |
| 설계 방향 | [핵심 설계 결정](core-decisions.md) |
| 설치와 배포 | [배포 보호 기준](deployment-protection.md), [nginx 샘플 설정](deployment/nginx-saanraan.conf), [nginx 하위 경로 샘플 설정](deployment/nginx-saanraan-subdirectory.conf) |
| 기여와 개발 | [기여자 작업 기준](contribution-guide.md), [모듈 작성 가이드](module-guide.md), [모듈 배치와 업데이트 기준](module-update-policy.md), [커스터마이징과 업데이트 충돌 가이드](customization-guide.md) |
| 관리자 화면 작성 | [관리자 UI 작성 기준](admin-ui-guide.md), [관리자 목록 컬럼 기준](admin-list-columns.md) |
| 보안과 정책 | [산란 보안 모델](security-model.md), [보안 체크리스트](security-checklist.md), [보안 제보와 처리 기준](security-response-policy.md), [DB 접근 정책](database-access-policy.md), [외부 의존성 배치 기준](dependency-policy.md) |
| 운영 정책 | [성능과 캐시 기준](performance-policy.md), [개인정보 처리활동 기록 기준](privacy-processing-records.md), [Rich Text Sanitizer 정책](rich-text-sanitizer-policy.md) |

## 문서 운영 기준

- 프로젝트의 방향, 사용 방법, 기여 방법, 배포와 보안 기준을 이해하는 데 필요한 문서만 저장소에 둔다.
- 기능 계획, 후속 작업, 마일스톤별 진행 상황은 GitHub 이슈와 마일스톤에서 관리한다.
- 일회성 점검 기록이나 현재 상태 스냅샷은 저장소에 새 문서로 쌓지 않는다.
- DB 명세, 관리자 화면별 항목 설명, 운영 중 참고 문서는 GitHub Wiki를 우선한다.
