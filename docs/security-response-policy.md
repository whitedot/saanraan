# 보안 제보와 처리 기준

이 문서는 saanraan의 보안 제보를 triage하고 수정할 때 남겨야 하는 최소 기준을 정리한다. 방어 설계 기준은 [산란 보안 모델](security-model.md)을, 변경 전 점검 항목은 [보안 체크리스트](security-checklist.md)를 우선한다.

## 제보 채널

보안 취약점 또는 민감한 운영 위험은 공개 이슈보다 먼저 이메일로 제보받는다.

```text
kimminsup@gmail.com
```

제보에는 가능한 경우 다음 정보를 포함한다.

- 영향받은 commit, branch, 배포판 또는 설치 상태
- 재현 절차와 요청 path, method, 입력값
- 기대 영향: 권한 우회, XSS, CSRF, 파일 노출, 개인정보 잔존, 중복 지급/차감 등
- 로컬 또는 스테이징 재현 여부
- 사용한 브라우저, PHP, DB 버전

운영 개인정보, 비밀번호, token, API key, 쿠키 원문은 제보 본문에 넣지 않는다. 필요한 경우 마스킹한 값과 재현 가능한 더미 데이터를 사용한다.

## 우선순위 분류

| 등급 | 기준 |
| --- | --- |
| Critical | 비로그인 또는 일반 회원이 관리자 권한, 임의 코드 실행, 대량 개인정보, 금액성 자산 생성/차감 우회를 얻는 경우 |
| High | 인증 회원이 다른 회원의 개인정보, 비공개 콘텐츠, 자산 권리, 관리자 기능에 접근하거나 저장형 XSS를 만들 수 있는 경우 |
| Medium | 제한된 조건의 권한 우회, 반사형 XSS, CSRF, 파일 노출, 개인정보 보존/삭제 불일치, 중복 처리 가능성이 있는 경우 |
| Low | 보안 hardening 누락, 오류 메시지 정보 노출, 영향이 제한적인 정책 불일치 |

금액성 자산, 개인정보, 업로드/다운로드, sanitizer, 설치/업데이트 경로는 재현 조건이 좁아도 기본적으로 한 단계 높게 검토한다.

## 수정 기준

보안 수정은 다음 순서를 따른다.

1. 재현 가능한 최소 요청 또는 fixture를 만든다.
2. 영향 범위를 action, helper, module contract, DB table 기준으로 좁힌다.
3. 가장 가까운 소유 모듈에서 수정한다. 코어 변경은 일반 실행 기반일 때만 선택한다.
4. 자동 check, fixture, smoke, 수동 검증 중 적어도 하나의 회귀 증거를 남긴다.
5. 요청 흐름, 권한, 개인정보, 배포 가정이 바뀌면 관련 문서를 갱신한다.
6. 유사 경로가 있는 모듈을 함께 검색한다.

수정 중 임시 우회나 기능 비활성화를 선택한 경우에는 복구 조건과 남은 위험을 GitHub 이슈나 마일스톤 체크리스트에 남긴다.

## 필수 검증

보안 수정 후 기본 검증:

```bash
php .tools/bin/check.php
```

HTTP 경로, redirect, 설치 화면, 내부 파일 노출, 공개/관리자 route에 영향이 있으면 HTTP smoke를 실행한다.

```bash
php -S 127.0.0.1:8097 -t .tools/public .tools/bin/dev-router.php
SR_SMOKE_BASE_URL=http://127.0.0.1:8097 php .tools/bin/smoke-http.php
```

HTML sanitizer, CKEditor, URL 임베드 변경은 rich text sanitizer fixture와 임베드 매니저 계약 fixture를 직접 실행한다.

```bash
php .tools/bin/check-rich-text-sanitizer.php
php .tools/bin/check-embed-manager-contracts.php
```

포인트, 적립금, 예치금, 쿠폰, 환전, 유료 열람, 환불 변경은 설치된 로컬 또는 스테이징 DB에서 read-only reconciliation 결과를 확인한다.

```bash
php .tools/bin/reconcile-assets.php
```

queue, cron, 예약, 저장소 정리, 보상 지급 변경은 운영 상태 점검 결과를 확인한다.

```bash
php .tools/bin/ops-status.php
```

인증 smoke처럼 데이터를 생성하거나 수정하는 검증은 운영 DB에서 실행하지 않는다.

## 공개와 기록

1. 수정 전에는 공개 이슈에 재현 가능한 공격 절차를 자세히 남기지 않는다.
2. 수정 후 공개 기록에는 영향 범위, 수정 요약, 검증 명령, 남은 제한을 남긴다.
3. 운영자가 조치해야 하는 설정, vendor 배치, DB update, cache 삭제가 있으면 릴리스 노트 또는 배포 문서에 명시한다.
4. 1.0 전에는 Wiki보다 저장소 `docs/` 기준 문서를 먼저 갱신하고, Wiki 정리 필요성은 작업 기록에 남긴다.

## 비밀과 로그

보안 제보와 수정 과정에서 다음 값은 저장소, 이슈, 로그, 검증 기록에 원문으로 남기지 않는다.

- 비밀번호, reset token, session token, remember token
- API key, SMTP 비밀번호, S3 secret, webhook secret
- 주민번호, 계좌번호, 전화번호, 주소 같은 운영 개인정보
- 실사용 쿠키, Authorization header, 외부 서비스 credential

재현에는 더미 계정과 로컬 또는 스테이징 데이터를 사용한다.
