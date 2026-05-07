# 릴리스 절차

이 문서는 Toycore 릴리스 전후의 최소 절차를 정리한다. Toycore 릴리스는 현재 저장소의 파일을 기준으로 만들며, 외부 모듈 저장소 checkout을 전제로 하지 않는다.

## 1. 준비

- `main` 브랜치가 배포할 커밋을 가리키는지 확인한다.
- 배포할 모듈이 있다면 toycore.git 안의 `modules/{module_key}` 폴더에 포함되어 있는지 확인한다.
- 각 모듈의 `module.php` version, `toycore.min_version`, `toycore.module_contract`를 확인한다.
- 기본 점검을 통과시키고, 필요한 경우 로컬/스테이징 HTTP 스모크 점검을 실행한다.

```sh
./.tools/bin/check
```

## 2. 릴리스 산출물

Git을 사용할 수 있는 환경은 릴리스 태그나 검증된 commit SHA를 기준으로 배포한다. Git을 사용할 수 없는 공유호스팅에는 GitHub 릴리스의 source zip 또는 maintainer가 현재 저장소 파일로 만든 단일 zip을 업로드한다.

릴리스 zip은 현재 저장소의 파일 구조를 보존해야 한다. `config/config.php`, `storage/installed.lock`, 로그, 백업 파일, 로컬 임시 파일은 포함하지 않는다.

## 3. 모듈 zip 확인

Toycore 릴리스는 모듈 소스의 출처를 관리하지 않는다. 별도 배포가 필요한 모듈은 제작자가 자기 환경에서 zip을 만들고, 운영자는 `/admin/modules`에서 업로드하거나 FTP/SFTP로 `modules/{module_key}`에 배치한다.

확인 기준:

- zip 압축 해제 시 `{module_key}/module.php` 구조가 나오는가
- `module.php` version이 배포하려는 버전과 맞는가
- `install.sql`과 필요한 `updates/` 파일이 포함되어 있는가
- 같은 버전의 update SQL을 이미 배포한 적이 있다면 내용이 바뀌지 않았는가

## 4. 릴리스 노트

릴리스 노트에는 다음을 포함한다.

- 본체 버전
- 포함된 모듈 목록과 각 모듈 버전
- 포함 모듈의 Toycore 최소 버전과 모듈 계약 버전이 바뀐 경우의 안내
- DB update SQL이 있는 모듈 목록
- 수동 백업과 `/admin/updates` 실행 안내

## 5. 배포 후 확인

- 릴리스 zip으로 신규 설치가 가능한지 확인한다.
- `/admin/modules`에서 설치 버전과 코드 버전이 일치하는지 확인한다.
- `/admin/updates`에 미적용 SQL이 남아 있지 않은지 확인한다.
