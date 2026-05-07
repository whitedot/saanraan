# 현재 구현 상태

이 문서는 현재 코드 기준의 구현 범위를 정리한다.

## 구현 완료

### 설치와 실행 기반

- 설치 상태 분기
- 웹 설치 화면
- 설치 실패 시 `storage/install-failed.json` 복구 marker 기록
- 설치 실패 복구 marker 메시지 정규화
- 이전 설치 실패 단계와 오류 요약 표시
- debug 오류/설치 실패 상세 메시지의 민감한 key/value 및 Bearer 값 표시 전 마스킹
- 운영 설치 시 현재 접속 host 기준 내부 파일 직접 접근 노출 점검
- 내부 파일 노출 점검용 HTTP 요청은 public URL만 허용하고 리다이렉트를 따르지 않음
- 운영 설치 시 HTTPS URL 확인
- 설치 최초 관리자 비밀번호 원문 길이 초과 입력 차단
- 외부 HTTP 호출 대상은 private/reserved/CGNAT/documentation/multicast IP 범위를 public으로 취급하지 않음
- `config/config.php` 생성, 설정 임시 파일은 PHP 확장자로 작성
- `storage/installed.lock` 생성
- core/member/admin 설치 SQL 실행
- 배포본에 포함된 경우 seo/popup_layer/point/deposit/reward/site_menu/banner/notification 설치 여부 선택
- 선택한 선택 모듈의 설치 SQL 실행
- 스키마 버전 기록
- 선택 모듈 코드가 없는 배포본에서는 설치 화면에서 해당 선택 모듈 제외
- minimal/standard/ops 배포 디렉터리와 zip 생성을 위한 패키징 스크립트 추가

### 모듈 리포지토리 분리

- `banner`, `popup_layer`, `site_menu`, `notification`, `seo`, `point`, `deposit`, `reward` 8개 모듈의 별도 Git 리포지토리 생성
- 각 모듈 리포지토리에 `module/` 하위 런타임 구조 적용
- 각 모듈 리포지토리에 `README.md`와 `CHANGELOG.md` 추가
- 각 모듈 리포지토리의 `module.php`에 Toycore 호환 메타데이터 추가
- 각 모듈 리포지토리에 설치용 zip 생성 스크립트 추가
- 모듈 설치/관리 화면에 설치 버전, 코드 버전, Toycore 호환 버전 표기
- 모듈 설치/관리 화면에서 owner 전용 zip 업로드와 공식 registry release zip 다운로드 지원
- 운영 환경의 모듈 소스 반영은 `admin.module_sources_enabled` bool 설정으로 명시 활성화해야 사용 가능
- 모듈 소스 반영과 파일 버전 동기화는 owner 비밀번호 재인증 필요
- 모듈 registry/repository zip 다운로드는 URL 검증 후 HTTP redirect를 따르지 않음
- 모듈 registry/repository zip 다운로드는 HTTP 2xx 응답만 저장 대상으로 처리
- 모듈 zip은 checksum, 항목 수, symlink, 경로 제어 문자/콜론/모호한 segment, 압축 해제 후 실제 파일 트리 경계, 압축 해제 크기, module key, version, downgrade, 교체 확인을 검증
- 모듈 소스 교체 실패 시 기존 백업 복구 실패를 감지하고 실패로 처리
- 모듈 소스 업로드/다운로드 성공과 실패를 감사 로그에 기록
- 공식 모듈 registry는 `docs/module-index.json`에 두고 HTTPS release zip URL과 checksum이 등록된 항목만 다운로드 가능
- `.tools/bin/check-module-index.php`로 공식 모듈 registry 구조와 release zip/checksum 쌍 검증 가능
- registry에 등록된 공식 GitHub repository는 owner가 고급 UI에서 ref를 지정해 archive zip으로 다운로드 가능
- repository archive 반영은 운영 환경에서 `repository_refs`에 등록된 40자 commit SHA와 sha256 checksum 쌍만 허용하며, branch/tag ref는 개발/스테이징용으로 제한
- checksum 미등록 repository archive 허용 설정은 개발/스테이징에서만 동작하며 bool 타입만 허용
- `.tools/bin/update-module-index`로 release zip checksum을 계산해 공식 모듈 registry를 갱신 가능
- `.tools/bin/update-module-index --repository-refs`로 운영용 repository archive commit SHA/checksum 등록 가능
- `.tools/bin/publish-module-release`로 공식 모듈 zip 수집, registry 갱신, GitHub Release 업로드 보조 가능
- `.tools/bin/clone-official-modules.php`로 공식 모듈 리포지토리 checkout 가능
- Toycore 본체에서 선택 모듈 복사본 제거
- Toycore 본체 배포 패키지는 minimal/standard/ops로 분리 가능
- standard/ops 배포 패키지는 같은 상위 디렉터리의 외부 모듈 리포지토리에서 선택 모듈을 조립
- GitHub Actions `Release packages` workflow로 공식 모듈 checkout, 전체 점검, 배포 패키지 조립, artifact 업로드 가능
- `.tools/bin/check-distributions.php`로 배포 패키지 manifest, 포함 모듈 버전, Toycore 최소 버전, 모듈 계약 버전, 설치 화면 선택 모듈 구성 검증 가능

### 코어

- 요청 method/path 해석
- 설치 전/설치 후 분기
- core helper 진입점과 큰 책임 단위 helper 파일 분리
- 현재 locale 결정
- 지원 locale 목록 설정
- 기본 번역 helper
- 기본 locale 번역 fallback
- 사이트/모듈 설정 조회 helper
- 단일 사이트 기본값을 `toy_site_settings` 필수 키로 저장
- 요청 단위 설정 조회 메모리 캐시
- 활성 모듈 `paths.php` 기반 action include
- 활성 모듈 간 method/path 충돌 감지와 오류 로그 기록
- action 상대 경로 검증
- 모듈 `module.php` 메타데이터 조회 helper
- 모듈 `toycore.module_contract`와 현재 코어 계약 버전 검증 helper
- 모듈 `requires` 메타데이터 기반 활성화 의존성 검증
- 모듈 type 조회 helper
- 활성 모듈 계약 파일 조회 helper
- 활성 모듈 `output-slots.php` 기반 출력 슬롯 helper
- 잔액 row 잠금, 거래 기록, 음수 잔액 방지를 처리하는 공통 원장 helper
- 기본 home/error view
- 공통 CSS 파일
- CSRF helper
- HTML escape helper
- redirect helper
- 리다이렉트/URL 제어 문자 차단
- 사이트 Base URL http/https scheme 검증
- 사이트 Base URL query/fragment/userinfo 제외 검증
- 현재 요청 Host 형식 검증
- canonical URL helper
- title, description, canonical, robots, Open Graph 출력 슬롯
- 활성 모듈 `extension-points.php` 기반 확장 지점 조회
- 다운로드 응답 헤더 helper
- 기본 mail helper
- SMTP/HTTP API mail transport 설정 지원
- 메일 subject/from name과 SMTP EHLO 값 제어 문자 방어
- HTTP API mail transport 발신자 payload 포함
- HTTP API 인증 메일 공개 HTTPS endpoint 검증
- 공개 HTTP URL DNS A/AAAA 사설망 주소 차단
- HMAC hash helper
- 환경변수 기반 app key 주입 지원
- 기본 보안 응답 헤더
- trusted proxy 기반 HTTPS/클라이언트 IP 해석
- trusted proxy IP/CIDR 설정 검증
- DB 기반 PHP 세션 저장소
- PHP 런타임 session ID 원문 대신 hash 저장
- DB 세션 잠금 획득 실패 시 세션 읽기/쓰기 실패 처리
- 독립 rate limit 카운터 저장소
- rate limit subject HMAC hash 저장
- 토큰 URL 보호를 위한 referrer 전송 차단
- 동적 PHP 응답 캐시 방지 헤더
- 감사 로그 helper
- 감사 로그 metadata 저장 전 민감 키 마스킹
- 인증/감사 로그 클라이언트 IP와 User-Agent 길이/제어 문자 정규화
- 클라이언트 IP 실제 IP 형식 검증
- 예외 요약 파일 로그
- 예외 요약 파일 로그 민감 key/value 마스킹
- 운영 복구 marker 기록/삭제 helper
- 운영 복구 marker 저장 전 민감 키 마스킹

### 회원

- 회원가입
- 회원가입 허용 설정
- 회원가입 요청 빈도 제한
- 회원가입 이메일 원문 길이 초과 입력 차단
- 회원가입 로그인 아이디 원문 길이 초과 입력 차단
- 회원가입 계정/인증 token/필수 동의 기록 원자 처리
- 로그인 식별자 email/login_id 설정 저장
- login_id 모드 공개 회원가입 아이디 입력/검증
- 로그인/로그아웃
- 로그인 식별자 원문 길이 초과 입력 조회 차단
- 로그아웃 시 현재 DB 세션 폐기 실패와 성공 로그 구분
- 로그인 실패 시 더미 비밀번호 검증으로 계정 존재 타이밍 노출 완화
- 로그인 성공 시 오래된 비밀번호 hash 재해시
- 이메일 인증 사용 시 미인증 계정 로그인 차단
- 미인증 계정 로그인 차단 시 인증 메일 재발송
- 이메일 인증 사용 시 회원가입 직후 자동 로그인 차단
- 미인증 로그인 차단 이벤트를 로그인 시도 제한에 포함
- 로그인 세션 생성 실패 이벤트를 로그인 시도 제한에 포함
- 회원가입 이메일 인증 메일 발송 요청
- 인증/재설정 메일 발송 결과 감사 로그 metadata 기록
- 인증/재설정 메일 발송 실패 인증 로그 기록
- 이메일 인증 사용 여부 설정
- 이메일 인증 메일 재발송 빈도 제한
- 내 계정 화면
- 내 계정 수정 intent 허용 목록 검증
- 표시 이름/선호 locale 수정
- 공개 계정 요약 helper
- 비밀번호 변경
- 새 비밀번호 원문 길이 초과 입력 차단
- 비밀번호 변경 후 현재 PHP/회원 세션 token 회전
- 비밀번호 변경 후 현재 세션 회전 실패 시 성공 로그인 상태 정리
- 비밀번호 변경 시 다른 로그인 세션 폐기
- 비밀번호 변경/재설정/탈퇴 시 세션 폐기 실패와 0건 폐기 구분
- 비밀번호 변경/탈퇴 현재 비밀번호 재확인 실패 제한
- 비밀번호 재설정 token 생성/검증/사용 처리
- 비밀번호 재설정 token 원자적 사용 처리
- 비밀번호 재설정 완료 시 전체 로그인 세션 폐기
- 비밀번호 재설정 완료 시 현재 PHP 세션 즉시 정리
- 비밀번호 재설정 완료 감사 로그에 현재 세션 정리 필요/성공 여부 분리 기록
- 비밀번호 재설정 완료 후 로그인 화면으로 이동
- 새 비밀번호 재설정 token 발급 시 기존 미사용 token 무효화
- 비밀번호 재설정 confirm URL과 HTML에서 token 원문 제거
- 비밀번호 재설정 confirm 세션에는 token hash와 짧은 보관 시각만 저장
- 비밀번호 재설정/이메일 인증 token 원문 길이 초과 입력 차단
- 비밀번호 재설정 메일 발송 요청
- 비밀번호 재설정 요청 이메일 원문 길이 초과 입력 차단
- 비밀번호 재설정 debug 링크는 localhost 요청에서만 표시
- 비밀번호 재설정 요청 빈도 제한
- 이메일 인증 token 생성/검증/사용 처리
- 이메일 인증 token 원자적 사용 처리
- 새 이메일 인증 token 발급 시 기존 미사용 token 무효화
- 이메일 인증 token의 발급 이메일과 현재 계정 이메일 일치 검증
- 이메일 인증 성공 후 token 없는 완료 URL로 이동
- 이메일 인증 debug 링크는 localhost 요청에서만 표시
- 회원 관리자 throttle 숫자 설정 원문 범위 검증
- DB 기반 로그인 세션 기록
- DB 로그인 세션 생성 실패 시 기존 session token hash 정리
- DB 세션 테이블이 있는 환경에서 로그인 세션 생성 실패 시 로그인 성공 처리 차단
- PHP 세션 strict/cookie-only 모드 적용
- 로그인 상태를 다중 인스턴스에서 공유 가능한 DB 세션으로 저장
- 계정 ID 없이 남은 회원 session token hash 정리
- 현재 세션의 계정 ID가 비정상이거나 계정이 없으면 PHP 세션 정리
- 비활성 계정의 기존 로그인 세션 차단
- 독립 rate limit 카운터 기반 시도 제한
- rate limit 테이블이 없는 기존 설치의 인증 로그 기반 제한 fallback
- 로그인 실패 제한 기준 모듈 설정화
- 회원 모듈 전용 관리자 설정 화면
- 회원가입/이메일 인증/시도 제한 설정
- 로그인 세션 최종 활동 시간 갱신 주기 완화
- 선택 프로필 저장/조회
- 선택 프로필 항목별 사용 설정
- 선택 프로필 아바타 경로 입력과 공개 URL 형식 검증
- 회원 탈퇴 시 계정 식별 정보 익명화
- 탈퇴 시 선택 프로필 삭제와 로그인 세션 폐기
- 탈퇴 시 최신 동의 철회 이력 기록
- 약관/개인정보 동의 기록
- 회원가입 마케팅 수신 선택 동의 이력 기록
- 개인정보 요청 접수/목록
- 개인정보 요청 입력값 보존
- 개인정보 요청 내용 원문 길이 초과 입력 차단
- 동일 유형 진행 중 개인정보 요청 중복 접수 차단
- 회원 개인정보 요청 목록의 관리자 메모 노출 축소
- 개인정보 JSON 내보내기
- 개인정보 JSON 내보내기 현재 비밀번호 재인증
- 개인정보 JSON 내보내기 POST/CSRF 보호
- 개인정보 JSON 내보내기 프로필 포함
- 개인정보 JSON 내보내기 세션 이력 포함
- 모듈별 개인정보 JSON 내보내기 확장
- 모듈별 개인정보 JSON 내보내기 실패 격리
- 모듈별 개인정보 JSON 내보내기 배열 반환 계약 검증
- 모듈별 개인정보 JSON 내보내기 내부 hash/token 필드 제거
- 모듈별 개인정보 JSON 내보내기 내부 secret-like 필드 제거
- 인증 로그 기록
- member helper 진입점과 계정/세션/token/privacy/throttle 단위 helper 파일 분리

### 팝업레이어

- 팝업레이어 목록/등록/수정/삭제
- 팝업레이어 활성/비활성 상태 관리
- 노출 시작/종료 시간 설정
- 닫기 유지 일수 설정
- 활성 모듈 `extension-points.php` 기반 노출 대상 선택
- `module -> point -> slot -> subject` 대상 규칙 저장
- 배너와 같은 content slot 기준 팝업 출력
- 사용자 요청 시 저장된 대상 규칙 테이블 조회
- 회원 로그인/회원가입 화면 content slot 출력 지점 연결

### 사이트 메뉴, 배너, 알림

- 사이트 메뉴 모듈 추가
- 메뉴 단위와 메뉴 항목 테이블 추가
- 설치 시 기본 header 메뉴와 홈/로그인/회원가입 항목 생성
- 사이트 메뉴 기본 항목 업데이트 전 중복 URL 정리
- 관리자 사이트 메뉴 화면 추가
- 메뉴 단위 수정과 key 변경 중복 검증
- 메뉴 삭제와 하위 항목 동시 삭제
- 기본 홈 화면의 `site.header` 출력 슬롯에 `header` 메뉴 출력
- 활성 모듈 `menu-links.php` 기반 메뉴 후보 조회
- 배너 모듈 추가
- 배너와 배너 대상 규칙 테이블 추가
- 관리자 배너 화면 추가
- 배너 상태 필터 조회 추가
- 배너 이미지 URL 내부 경로 제한
- 기본 홈 화면 본문 위/아래 content 출력 슬롯 연결
- 알림 모듈 추가
- 사이트 내 알림, 채널별 발송 대기열, 회원별 읽음 테이블 추가
- ERD 문서에 운영 모듈 테이블 반영
- 관리자 알림 등록/운영 현황/대기열 화면 추가
- 관리자 알림 대상/발송 채널/발송 상태 필터 조회 추가
- 관리자 알림 목록의 제목/수신자/provider/생성자 세부 노출 축소
- 알림 링크 URL 검증과 회원 화면 외부 링크 보안 속성 적용
- 관리자 알림 삭제 추가
- 알림 발송 대기열 상태 수동 변경
- 회원 알림 목록과 읽음 처리 화면 추가
- 회원 알림 읽음 상태 필터와 미읽음 요약 추가
- 회원 알림 전체 읽음 처리 추가
- 개인정보 JSON 내보내기에 알림 데이터 포함
- 이메일/SMS/알림톡은 실제 provider 호출 전 발송 대기열까지 구현

### 포인트 모듈, 예치금 모듈, 적립금 모듈

- 포인트 모듈, 예치금 모듈, 적립금 모듈을 각각 독립 모듈로 분리
- 각 독립 모듈의 회원별 잔액 테이블
- 각 독립 모듈의 거래 원장 테이블
- 관리자 수동 지급/차감 화면
- 각 모듈의 `admin-menu.php` 기반 관리자 메뉴 노출
- 회원별 잔액 조회
- 회원별 거래 이력 조회
- 거래 후 잔액 기록
- 잔액 음수 방지
- 거래 사유와 참조 유형/ID 기록
- 거래 생성 감사 로그 기록

### 관리자

- 관리자 대시보드
- 관리자 대시보드 설치 보호 상태 요약
- 관리자 대시보드 고위험 사이트 설정 상태와 수정 시각 요약
- 관리자 대시보드 인증 런타임 설정 요약
- 관리자 대시보드 클라이언트 IP 판정과 인증 제한 설정 요약
- 관리자 대시보드 운영 모듈 요약
- 관리자 대시보드 복구 marker와 모듈 백업 요약
- 사이트 설정 조회/저장
- 사이트 설정 intent 허용 목록 검증
- 사이트 설정 항목 조회/저장/삭제
- 민감한 이름의 사이트 설정 값 목록 표시 마스킹
- 보안 경계를 바꾸는 고위험 사이트 설정 저장/삭제 owner 재인증
- 고위험 사이트 설정 bool 타입 강제
- 고급 사이트/모듈 설정 int/bool/json 값 저장 전 형식 검증과 bool/int 정규화
- 모듈 목록/상태 관리
- 민감한 이름의 모듈 설정 값 목록 표시 마스킹
- 코드에 있지만 DB에 등록되지 않은 모듈 설치
- 모듈 설치 중 실패 상태 표시와 재설치
- 모듈 설치 후 활성/비활성 상태 선택
- 모듈 활성화 시 의존 모듈과 계약 파일 확인
- 모듈 key는 영문 소문자 시작, 영문/숫자/underscore 2~40자 범위로 검증
- 번역/개인정보 내보내기 모듈 확장점도 공용 모듈 key 기준으로 검증
- 모듈 코드 버전과 설명 표시
- 모듈 소스 반영 실패 메시지 표시 전 민감한 key/value 및 Bearer 값 마스킹
- 모듈 설정 항목 조회/저장/삭제
- 민감한 이름의 모듈 설정 저장/삭제 owner 재인증
- 기본 모듈 비활성화 차단
- 회원 목록/상태 관리
- 회원 상태 관리 intent 허용 목록 검증
- 회원 비활성 상태 변경 시 세션 자동 폐기
- 회원 활성 세션 수 조회
- 회원 세션 강제 폐기
- 관리자 역할 부여/회수
- 마지막 owner 회수 차단
- 마지막 active owner 역할 회수 차단
- owner 계정 상태/세션 변경 owner 권한 보호
- 마지막 active owner 비활성화 차단
- 관리자 회원/역할 목록의 이메일과 표시명 노출 축소
- 관리자 POST 대상 ID 양의 정수 문자열 검증
- 관리자 작업 로그 조회
- 관리자 작업 로그 처리자/결과/기간 필터
- 관리자 작업 로그 이벤트/대상/결과 필터 허용값 검증
- 관리자 작업 로그 날짜 필터 실제 날짜 검증
- 관리자 작업 로그 metadata 표시 민감 키 마스킹
- 관리자 작업 로그 metadata 저장 전 민감 키 마스킹
- 관리자 작업 로그 message와 metadata 문자열의 민감한 key/value 및 Bearer 값 저장 전 마스킹
- 기존 관리자 작업 로그 message와 metadata 문자열 표시 전 민감한 key/value 및 Bearer 값 마스킹
- 설치/업데이트 실패 marker message 표시 전 민감한 key/value 및 Bearer 값 마스킹
- 개인정보 요청 처리
- 종결된 개인정보 요청 상태 재변경 차단
- 관리자 개인정보 요청 메모 원문 길이 초과 입력 차단
- 관리자 개인정보 요청 목록의 기존 메모 원문 미리 채움 차단
- 개인정보 요청 JSON 내보내기
- 관리자 개인정보 요청 JSON 내보내기 POST/CSRF 보호
- 관리자 개인정보 요청 JSON 내보내기 관리자 비밀번호 재인증
- 관리자 개인정보 요청 JSON 내보내기 연결 회원 데이터 실패 격리
- 개인정보 JSON 내보내기 UTF-8 치환 인코딩과 헤더 전 인코딩 실패 차단
- 관리자 개인정보 요청 목록의 요청자/요청 내용 노출 축소
- 관리자 개인정보 요청 목록 조회 감사 로그
- 보관 기간 수동 정리
- 보관 기간 정리 일수 원문 범위 검증
- 보관 기간 정리 실행 전 확인 문구 검증
- 오래된 모듈 파일 백업 보관 기간 정리
- owner 전용 업데이트 확인/실행
- 업데이트 전 백업 확인
- 업데이트 적용 전 SQL statement 수 표시
- 업데이트 실패 marker 관리자 화면 표시
- 적용된 스키마 버전 목록 표시
- 업데이트 파일 checksum 표시와 감사 로그 기록
- 활성 모듈의 `admin-menu.php` 기반 관리자 메뉴 조정

### 개인정보와 운영

- 감사 로그 테이블
- 개인정보 요청 테이블
- 개인정보 내보내기에서 내부 hash 제외
- 개인정보 다운로드 응답의 Content-Type과 파일명 헤더 값 정규화
- 사용 완료 token 정리
- 만료/폐기 세션 정리
- 오래된 인증 로그/감사 로그 정리
- 만료된 PHP 런타임 세션과 인증 제한 카운터 정리
- 오래된 알림/알림 발송 대기열/알림 읽음 기록 정리
- 오래된 모듈 파일 백업 정리
- 인증 로그 IP/시간 조회 인덱스
- 인증 로그 계정/이벤트/시간 조회 인덱스
- 인증 로그 IP/이벤트/시간 조회 인덱스
- 배포 보호 기준 문서화
- `storage/logs/error.log` 오류 기록, 로그 필드는 제어 문자를 제거해 단일 라인으로 정규화
- 모듈 업데이트 SQL 적용
- 모듈 업데이트 SQL statement 분리 실행
- 업데이트 파일 경로와 checksum 적용 전 검증
- 업데이트 동시 실행 방지 lock
- 업데이트 실패 시 `storage/update-failed.json` 복구 marker 기록
- 업데이트 실패 감사 로그와 복구 marker 메시지 정규화
- 비활성 설치 모듈의 업데이트 파일 확인
- 팝업레이어 대상 조회 인덱스

### 개발 및 검증 도구

- Docker 기반 로컬 PHP 실행 래퍼
- 기본 점검 스크립트
- 로컬 PHP 기반 크로스플랫폼 기본 점검 스크립트
- 공식 모듈 registry 구조 검사
- 클라우드 인증 런타임 정적 검사
- trusted proxy와 app key 런타임 helper 동작 검사
- Docker 없이 실행 가능한 공백, SQL 파일, 모듈 기본 구조 검사 선행
- 관리자 메뉴 path와 모듈 `paths.php` GET route 일치 검사
- 로컬 PHP 또는 Docker 실행 시 전체 PHP 문법 검사
- 기본 점검 스크립트의 인증 런타임/회원 인증 정책 검사 실행
- 로컬/스테이징 URL 대상 최소 HTTP 스모크 점검 도구

## 남은 작업

- 현재 문서화된 1차 구현 목표는 모두 코드 또는 문서로 반영됨

## 검증 기준

현재 구현은 다음 검증 기준을 사용한다.

```text
전체 PHP 파일 php -l
git diff --check
SQL 파일 비어 있음 여부 검증
모듈 기본 계약 파일 구성 검증
관리자 메뉴 path와 모듈 GET route 일치 검증
설치/로그인/관리자 주요 화면 수동 HTTP 검증
회원가입/비밀번호 변경/재설정/이메일 인증/탈퇴 검증
개인정보 요청/내보내기/보관 정리 검증
관리자 업데이트 화면 검증
적용된 스키마 버전 목록 표시 검증
robots.txt 출력 검증
sitemap XML 출력 검증
SEO 관리자 설정 저장 검증
DB 세션 기록/폐기 검증
공통 CSS 정적 파일 응답 검증
SEO meta tag 출력 검증
번역 fallback 검증
내부 SQL/PHP/문서/도구/저장소 파일 직접 접근 차단 HTTP 스모크 검증
```

최근 로컬 실행에서는 `php .tools/bin/check.php` 기준으로 전체 PHP 파일 `php -l`, `git diff --check`, SQL 파일 비어 있음 여부 검증, 모듈 기본 계약 파일 구성 검증, 관리자 메뉴 path와 모듈 GET route 일치 검증, 인증 런타임/회원 인증 정책 검사까지 통과했다.
