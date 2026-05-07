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
```

Toycore에 업로드되는 실제 모듈 파일은 `module/` 아래에 둔다.

## 로컬 점검

zip을 만들기 전에 Toycore가 이 모듈을 읽을 수 있는지 확인한다.

```sh
git clone https://github.com/whitedot/toycore.git toycore
cd toycore
git checkout TOYCORE_REF
php .tools/bin/check-external-module.php ../MODULE_REPOSITORY/module MODULE_KEY
```

## zip 구조

릴리스 zip은 압축을 풀었을 때 바로 모듈 키 디렉터리가 나오게 만든다.

```text
MODULE_KEY-2026.05.001.zip
-> MODULE_KEY/
   - module.php
   - install.sql
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
