# 파일 저장 S3 연동 기준

이 문서는 로컬 `storage/` 기반 파일 저장을 유지하면서 S3-compatible object storage를 선택적으로 사용하는 기준을 정리한다.

## 구현 상태

| 항목 | 상태 |
| --- | --- |
| 공통 storage helper | 구현 |
| 로컬 저장 adapter | 구현 |
| S3 PUT, DELETE, HEAD | 구현 |
| S3 presigned GET | 구현 |
| 배너 이미지 | 적용 |
| 커뮤니티 첨부 | 적용 |
| 관리자 진단 | 설정 준비 상태 표시 |
| 운영 HTTPS 검증 | 진단과 런타임 차단 적용 |
| 회귀 점검 | `.tools/bin/check-storage-helpers.php` |
| 기존 로컬 파일 | 호환 유지 |

## 방향

| 항목 | 기준 |
| --- | --- |
| 기본값 | 기존 로컬 `storage/` 저장 유지 |
| 선택지 | S3 또는 S3-compatible object storage |
| 코어 책임 | 저장, 읽기 URL, 삭제 같은 저장소 primitive |
| 모듈 책임 | 파일 의미, 공개 범위, 다운로드 권한, 보존 정책 |
| 우선 적용 | 배너 이미지, 커뮤니티 첨부 |
| 의존성 | AWS SDK 없이 최소 Signature V4 helper 우선 |

## 설계 기준

| 구분 | 결정 |
| --- | --- |
| 공개 파일 | 배너처럼 공개 가능한 파일은 `public_base_url` 사용 가능 |
| 비공개 파일 | 커뮤니티 첨부는 모듈 권한 확인 후 PHP proxy 또는 짧은 presigned URL 사용 |
| 인증 정보 | DB보다 `config/config.php` 또는 env 우선 |
| 운영 환경 | `endpoint`, `public_base_url`은 HTTPS만 허용 |
| 개발 환경 | S3-compatible 로컬 endpoint 검증을 위해 HTTP 허용 가능 |
| 기존 파일 | 기존 로컬 파일은 유지하고 신규 업로드부터 S3 적용 |
| 일괄 이전 | 별도 마이그레이션 도구로 분리 |
| 저가형 호스팅 | cURL 또는 stream context로 동작 가능한 구현 우선 |

## 구현 완료 범위

| 항목 | 내용 |
| --- | --- |
| 설정 | `storage.default`, `storage.s3.*` 구조 |
| 저장 helper | local adapter, S3 `PUT`, `DELETE`, `HEAD`, presigned `GET` |
| 적용 모듈 | 배너 이미지, 커뮤니티 첨부 |
| DB update | 첨부 테이블 `storage_driver`, `storage_key` |
| 관리자 진단 | S3 설정 준비 상태와 설정 오류 표시 |
| 배포 문서 | S3 설정 예시와 권한 기준 |

## 설정 예시

```php
'storage' => [
    'default' => 'local',
    's3' => [
        'bucket' => '',
        'region' => '',
        'endpoint' => '',
        'public_base_url' => '',
        'path_style' => false,
        'access_key_env' => 'TOY_S3_ACCESS_KEY',
        'secret_key_env' => 'TOY_S3_SECRET_KEY',
    ],
],
```

## DB 기준

| 컬럼 | 용도 |
| --- | --- |
| `storage_driver` | `local`, `s3` 구분 |
| `storage_key` | 저장소 내부 object key |
| `storage_path` | 기존 로컬 파일 호환용 |
| `mime_type` | 응답 `Content-Type` 검증 |
| `size_bytes` | 저장 후 크기 검증 |
| `checksum_sha256` | 저장 무결성 검증 |

## 검증

| 항목 | 확인 |
| --- | --- |
| 기본 호환 | S3 설정이 없어도 기존 로컬 업로드/다운로드 동작 |
| 업로드 | 이미지 재인코딩 후 S3 저장, 크기와 checksum 기록 |
| 다운로드 | 비공개 첨부가 권한 없이 열리지 않음 |
| 실패 처리 | 인증 실패, 버킷 없음, 네트워크 실패 메시지 확인 |
| 보안 | 실행 확장자 차단, MIME 검증, `nosniff`, signed URL TTL 확인 |
| 운영 HTTPS | `production`에서 HTTP endpoint/public URL 차단 |
| 호환성 | AWS S3와 path-style S3-compatible endpoint 모두 확인 |
| 자동 점검 | `./.tools/bin/check-storage-helpers.php`, `./.tools/bin/check` |

## 제외

| 항목 | 이유 |
| --- | --- |
| 전체 파일 관리자 | 코어가 파일 의미를 소유하지 않기 때문 |
| 자동 로컬 파일 이전 | 운영 위험이 커 별도 도구로 분리 |
| 원격 스토리지 강제 | 공유호스팅 기본 운영성을 유지해야 함 |
| 모듈별 보존 정책 통합 | 삭제와 보존 판단은 도메인 모듈 책임 |
