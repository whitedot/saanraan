# 저장소 문서 기준

이 디렉터리는 산란 구현과 함께 버전이 맞아야 하는 기준 문서를 둔다. 사람 개발자가 현재 구현을 이해하기 위한 설명서와 화면/DB 명세는 GitHub Wiki를 우선한다.

## 저장소에 남기는 문서

다음 성격의 문서는 `docs/`에 남긴다.

- 구현 판단이 흔들릴 때 우선하는 설계 결정
- 보안, 개인정보, DB 접근, 배포 보호처럼 코드 변경과 함께 검토해야 하는 정책
- 모듈 계약, 모듈 업데이트, 릴리스, 스모크 테스트처럼 PR과 배포 과정에서 확인해야 하는 기준
- 아직 구현 전인 기능 계획 문서

현재 유지 문서:

| 문서 | 성격 |
| --- | --- |
| [핵심 설계 결정](core-decisions.md) | 최상위 설계 결정 |
| [모듈 작성 가이드](module-guide.md) | 모듈 계약과 작성 기준 |
| [모듈 배치와 업데이트 기준](module-update-policy.md) | 모듈 설치/업데이트 기준 |
| [DB 접근 정책](database-access-policy.md) | SQL 작성과 DB 접근 정책 |
| [산란 보안 모델](security-model.md) | 보안 책임 경계 |
| [보안 체크리스트](security-checklist.md) | 변경 검토 체크리스트 |
| [배포 보호 기준](deployment-protection.md) | 운영 서버 직접 접근 차단 기준 |
| [릴리스 절차](release-process.md) | 릴리스 준비와 배포 절차 |
| [스모크 테스트 기준](smoke-test.md) | 배포 전후 최소 검증 |

## 임시 보관 계획 문서

아직 구현하지 않은 기능 계획은 구현 전까지 `docs/`에 보관한다. 실제 구현과 검증이 끝나면 계획 문서는 삭제하고, 계속 유지해야 할 기준만 관련 유지 문서나 모듈 README로 옮긴다.

현재 계획 문서:

- [관리자 토스트 안내 계획](admin-toast-notice-plan.md)
- [CKEditor 플러그인 계획](ckeditor-plugin-plan.md)
- [본인확인 플러그인 계획](identity-verification-plugin-plan.md)
- [회원 마이그레이션 계획](member-migration-plan.md)
- [페이지 모듈 계획](page-module-plan.md)
- [결제 플러그인 계획](payment-plugin-plan.md)

## Wiki로 충분한 문서

다음 성격의 문서는 GitHub Wiki를 우선한다.

- 개발자 온보딩과 설명형 가이드
- 현재 DB 스키마 명세
- 관리자 화면별 항목 설명
- 요청 흐름, 관리자 화면, DB, 보안/개인정보, 배포/운영을 설명하는 개발자 참조
- 문제 해결과 운영 중 참고 문서

Wiki 문서는 현재 구현 상태를 설명한다. 구현 기준이 바뀌면 관련 저장소 문서와 Wiki를 함께 갱신한다.
