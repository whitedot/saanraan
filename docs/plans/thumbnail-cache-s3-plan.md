# 썸네일 캐시 S3 확장 계획

이 문서는 이슈 #325의 썸네일 생성 helper와 캐시 운영 정책을 S3 원본 환경까지 확장하기 위한 계획이다.

문서 수명:

- S3 원본 썸네일 생성과 캐시 정책 보강을 구현하기 전까지 계획 문서로 보관한다.
- 구현이 완료되면 현재 기준은 `docs/performance-policy.md`, `docs/deployment-protection.md`, `docs/module-guide.md`, 관련 모듈 README 또는 Wiki로 옮긴다.
- S3 캐시 저장소 자체 지원은 1차 구현과 분리한 후속 계획으로 남긴다.

## 기본 판단

S3 사용 여부에 따라 다르게 적용해야 하는 부분은 원본 읽기와 source version 산출이다. 하지만 S3를 사용한다고 해서 썸네일 캐시 저장소까지 자동으로 S3로 바꾸지는 않는다.

1차 목표:

```text
local 원본 + local 썸네일 캐시
S3 원본 + local 썸네일 캐시
```

후속 목표:

```text
S3 원본 + S3 썸네일 캐시
local/S3 캐시 저장소 선택 설정
```

이렇게 나누면 공유호스팅 친화성을 유지하면서 S3 원본 대응을 시작할 수 있고, `/admin/storage-cache`의 파일 스캔형 조회와 기간별 정리 기능도 1차 범위에서 계속 유효하다.

## 설정 방향

썸네일 캐시 저장소는 기본값을 local로 둔다.

예상 설정:

```php
'thumbnail_cache' => [
    'driver' => 'local',
]
```

`storage.default = s3`인 환경에서도 `thumbnail_cache.driver`의 기본값은 `local`이다.

이유:

- S3 원본을 쓰는 단일 서버/공유호스팅 환경에서는 local cache가 단순하고 비용이 낮다.
- 현재 Apache/dev-router 보호 규칙과 관리자 스토리지 캐시 화면을 그대로 사용할 수 있다.
- S3 캐시 저장소는 object listing, batch delete, CDN/public URL 정책까지 필요하므로 별도 adapter 설계가 필요하다.

## 원본 driver별 적용

### local 원본

```text
local source
-> local path 확인
-> MIME/getimagesize 재검증
-> source_version 산출
-> storage/cache/thumbnails local cache 생성
```

source version 우선순위:

```text
모듈 제공 checksum_sha256 또는 source_version
-> filemtime + filesize
-> filemtime
```

### S3 원본

```text
S3 source
-> HeadObject로 metadata 확인
-> GetObject를 임시 파일로 저장
-> MIME/getimagesize 재검증
-> source_version 산출
-> storage/cache/thumbnails local cache 생성
-> 임시 파일 정리
```

source version 우선순위:

```text
모듈 제공 checksum_sha256 또는 source_version
-> S3 VersionId
-> S3 ETag
-> LastModified + ContentLength
```

S3 `ETag`는 multipart upload에서 MD5 checksum이 아닐 수 있으므로 checksum이 아니라 version marker로만 사용한다.

## 필요한 storage primitive

S3 원본을 썸네일화하려면 storage helper가 원본을 임시 파일로 넘길 수 있어야 한다.

후보 API:

```php
sr_storage_copy_to_temp_file(string $driver, string $key, array $options = []): ?array
```

반환값:

```php
[
    'path' => '/tmp/...',
    'content_type' => 'image/jpeg',
    'content_length' => 12345,
    'version_marker' => '...',
    'cleanup' => true,
]
```

필수 제한:

- `storage_key` 안전성 검증
- 최대 원본 bytes 제한
- 최대 pixel 제한
- S3 metadata만 믿지 않고 임시 파일 기준 MIME과 이미지 크기 재검증
- 실패 시 기존 `public_url` fallback
- 성공/실패와 관계없이 임시 파일 삭제

## 캐시 key

기존 `source_mtime` 단독 방식은 source version으로 바꾼다.

권장 파일명:

```text
{source_hash}_{variant_key}_{source_version}.{ext}
```

`source_hash`:

```text
sha256(storage_driver . ':' . storage_key)
```

`source_version`은 파일명에 넣을 수 있도록 안전한 짧은 문자열로 정규화한다. 예를 들어 원본 version 후보 문자열 전체를 그대로 넣지 않고 `sha256(version input)`의 앞부분을 사용하는 방식이 안전하다.

## 캐시 경로

운영 가시성을 위해 후속 변경 시 module key를 경로에 포함한다.

권장 경로:

```text
storage/cache/thumbnails/{module_key}/{hash-prefix}/{source_hash}_{variant_key}_{source_version}.{ext}
```

`module_key` 규칙:

```text
[a-z][a-z0-9_]{1,39}
```

모듈이 명시하지 않은 경우 fallback은 `common`으로 둔다. 다만 실제 호출부는 가능한 한 모듈 key를 명시한다.

## 생성 흐름

권장 흐름:

```text
sr_thumbnail_public_url($source, $options)
  -> 공개 가능한 원본인지 확인
  -> module_key, driver, key, options 검증
  -> source metadata/head 확인
  -> driver별 원본 파일 확보
  -> 실제 MIME/크기 재검증
  -> source_version 산출
  -> cache key 계산
  -> cache exists면 cache URL 반환
  -> temp target에 썸네일 생성
  -> 생성 파일 검증
  -> atomic rename으로 최종 cache path 반영
  -> cache URL 반환
  -> 실패 시 public_url fallback
```

캐시 생성은 임시 파일 후 atomic rename을 사용한다.

```text
{cachePath}.tmp.{random}
-> GD/Imagick 저장
-> is_file/getimagesize 확인
-> rename(tmp, cachePath)
```

## 삭제와 정리 계약

원본 삭제 또는 교체가 일어나는 모듈 action은 old source 기준으로 썸네일 variant 삭제 helper를 호출한다.

```php
sr_thumbnail_delete_variants([
    'module_key' => 'community',
    'storage_driver' => $oldDriver,
    'storage_key' => $oldKey,
]);
```

적용 지점:

- 첨부 삭제
- 게시글 삭제 또는 비식별화로 첨부가 삭제되는 경우
- 게시판 삭제로 첨부가 삭제되는 경우
- 같은 storage key에 overwrite하는 경우
- 새 storage key로 교체하는 경우

source version이 바뀌면 새 썸네일 URL이 생성되지만, 과거 version 캐시 파일 정리는 삭제/교체 계약이 맡는다. 관리자 화면의 기간별 정리는 운영 보조 수단이다.

## 관리자 화면

1차 범위에서는 `/admin/storage-cache`가 local cache를 파일 스캔 방식으로 조회하고 정리한다.

유지할 기준:

- `storage/cache/thumbnails` 아래 생성 패턴 파일만 대상으로 한다.
- 기간 필터는 캐시 파일 수정 시각 기준이다.
- 삭제는 현재 조회 조건, delete 권한, CSRF, 확인 문구, 감사 로그를 요구한다.
- 원본 파일과 모듈 데이터는 변경하지 않는다.

module key 경로가 도입되면 `module_key` 필터를 추가한다.

## S3 캐시 저장소 후속 단계

썸네일 캐시 자체를 S3에 저장하려면 현재 파일 스캔형 helper를 그대로 확장하지 않고 adapter 계약을 만든다.

후보 API:

```php
sr_thumbnail_cache_list(array $filters): array
sr_thumbnail_cache_delete(array $filters): array
```

adapter별 구현:

```text
local: RecursiveDirectoryIterator 기반 파일 스캔/delete
s3: ListObjectsV2 기반 object listing + DeleteObjects batch
```

후속 고려 사항:

- S3 listing pagination
- `DeleteObjects` batch 제한
- CDN/public_base_url 또는 signed URL 정책
- cache driver별 감사 로그
- 다중 서버 캐시 일관성
- 관리자 화면의 `cache_driver` 필터

## 최종 구현 순서

1. `source_version` helper를 추가한다.
2. local 원본 version을 checksum 또는 `filemtime + filesize`로 산출한다.
3. 캐시 생성 저장을 temp file + atomic rename으로 바꾼다.
4. `module_key` 옵션과 캐시 경로를 도입한다.
5. 관리자 캐시 화면이 module key 경로를 파싱하고 필터링하게 한다.
6. `sr_storage_copy_to_temp_file()`을 추가한다.
7. S3 원본을 임시 파일로 받아 local cache 썸네일을 생성한다.
8. 원본 삭제/교체 모듈 action의 `sr_thumbnail_delete_variants(old source)` 호출을 점검하고 보완한다.
9. S3 캐시 저장소는 별도 후속 이슈로 adapter 기반 list/delete와 함께 구현한다.
