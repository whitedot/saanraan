# 모듈 자동 점검 빠른 시작

CI는 GitHub가 대신 실행해 주는 자동 점검이다. 배포가 아니며, 처음부터 꼭 알아야 하는 기능도 아니다.

외부 모듈 제작을 처음 시작한다면 [외부 모듈 제작 빠른 시작](external-module-quickstart.md)의 로컬 점검부터 실행한다. 로컬 점검에 익숙해진 뒤 이 문서를 본다.

## CI가 대신 하는 일

로컬에서 하던 다음 명령을 GitHub가 push나 pull request 때 실행한다.

```sh
php toycore/.tools/bin/check-external-module.php module-repo/module banner
```

여기서 `toycore`와 `module-repo`는 GitHub Actions workflow가 checkout한 작업 디렉터리 이름이다. 로컬 디렉터리 배치가 Toycore 저장소와 모듈 저장소를 같은 상위 폴더에 두어야 한다는 뜻이 아니다.

성공하면 최소 모듈 구조, 메타데이터, Toycore 계약 버전, PHP 문법이 맞는지 확인한 것이다.

## 설정 순서

1. Toycore의 `docs/module-ci-template.yml`을 모듈 저장소의 `.github/workflows/check.yml`로 복사한다.
2. `TOYCORE_MODULE_KEY`를 내 모듈 키로 바꾼다.
3. `TOYCORE_REF`를 지원할 Toycore 릴리스 태그로 바꾼다.

예:

```yaml
env:
  TOYCORE_MODULE_KEY: banner
  TOYCORE_REF: v0.1.1
```

## 실패했을 때

GitHub Actions가 실패하면 로그에서 `toycore external module checks failed` 아래 메시지를 본다.

자주 나오는 원인:

- `module.php`가 없다.
- `install.sql`이 없다.
- `module.php`의 `version` 형식이 다르다.
- `toycore.module_contract`가 현재 Toycore 계약 버전과 다르다.
- PHP 문법 오류가 있다.
- `paths.php`의 action 파일이 없거나 경로 형식이 다르다.
- `admin-menu.php`의 관리자 path가 `paths.php`의 `GET` route와 맞지 않는다.
- 계약 파일 반환 구조가 최소 형식과 다르다.

## 기억할 점

- CI는 선택 기능이다.
- CI는 배포가 아니라 점검이다.
- 로컬 점검이 먼저이고, CI는 그 점검을 자동화하는 다음 단계다.
