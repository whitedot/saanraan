# MODULE_NAME

이 저장소는 Toycore 외부 모듈 `MODULE_KEY`를 관리한다.

## 지원 버전

```text
Toycore 최소 버전: TOYCORE_VERSION
Toycore 검증 버전: TOYCORE_VERSION
모듈 계약 버전: MODULE_CONTRACT_VERSION
```

## 구조

```text
module/
- module.php
- install.sql
.tools/bin/package-module
```

Toycore에 업로드되는 실제 모듈 파일은 `module/` 아래에 둔다.

## 로컬 점검

zip을 만들기 전에 Toycore가 이 모듈을 읽을 수 있는지 확인한다.

아래 명령은 Toycore 저장소와 이 모듈 저장소가 같은 상위 폴더에 나란히 있는 구조를 기준으로 한다.

```text
work/
- toycore/
- MODULE_REPOSITORY/
```

```sh
git clone https://github.com/whitedot/toycore.git toycore
cd toycore
git checkout TOYCORE_REF
php .tools/bin/check-external-module.php ../MODULE_REPOSITORY/module MODULE_KEY
```

이미 `toycore` 저장소가 있다면 마지막 명령만 실행하면 된다. 모듈 저장소 위치가 다르면 `../MODULE_REPOSITORY/module` 부분만 실제 `module/` 폴더 경로로 바꾼다.

## zip 구조

릴리스 zip은 압축을 풀었을 때 바로 모듈 키 디렉터리가 나오게 만든다.

```text
MODULE_KEY-2026.05.001.zip
-> MODULE_KEY/
   - module.php
   - install.sql
```

스캐폴딩 도구가 만든 저장소라면 다음 명령으로 zip을 만들 수 있다.

```sh
./.tools/bin/package-module 2026.05.001
```

Windows처럼 실행 권한 개념이 다른 환경에서는 다음처럼 실행해도 된다.

```sh
php .tools/bin/package-module 2026.05.001
```

## Toycore 관리자 업로드

```text
1. /admin/modules 이동
2. 모듈 zip 업로드
3. owner 비밀번호 입력
4. 설치 또는 파일 교체
5. /admin/updates에서 미적용 SQL 확인
```

## 자동 점검

GitHub Actions를 사용하면 로컬 점검 명령을 push할 때 자동으로 실행할 수 있다. 처음에는 몰라도 된다.

자세한 내용은 Toycore 본체 문서를 본다.

- `docs/external-module-quickstart.md`
- `docs/module-checklist.md`
- `docs/module-ci-quickstart.md`
- `docs/module-guide.md`
