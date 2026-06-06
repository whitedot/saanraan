# 커스터마이징 가이드

이 문서는 배포판에 포함된 코어와 기본 모듈을 운영 환경에서 커스터마이징할 때 지켜야 하는 기준이다. 목표는 사이트별 요구를 반영하면서도 이후 배포판 업데이트가 저장 흐름, DB 스키마, 삭제/복사/개인정보 처리와 충돌하지 않게 하는 것이다.

## 기본 원칙

배포판이 소유하는 테이블, 컬럼, helper, view는 배포판 업데이트의 기준선이다. 사이트별 커스터마이징은 가능하지만, 배포판 파일이나 공식 테이블을 직접 고치면 다음 업데이트에서 같은 이름의 컬럼, 저장 helper 변경, view 교체, update SQL과 충돌할 수 있다.

커스터마이징이 DB를 필요로 하면 먼저 별도 모듈 또는 별도 확장 테이블로 분리한다. 기존 공식 테이블에 컬럼을 더하는 방식은 마지막 수단으로만 검토하고, 그 경우에도 배포판 업데이트와 이름이 겹치지 않도록 명확한 site/module prefix를 붙인다.

## 하지 않는 것

- `sr_content_items`, `sr_community_posts`, `sr_member_accounts`처럼 배포판 모듈이 소유하는 테이블에 사이트 전용 컬럼을 바로 추가하지 않는다.
- 배포판 view 파일을 직접 수정해 사이트 전용 기능의 저장 진실원을 만들지 않는다.
- public layout이나 skin이 자기 표시를 위해 공식 모듈의 저장 helper 계약을 암묵적으로 바꾸게 하지 않는다.
- 배포판 update SQL 파일을 운영 사이트에 맞게 수정하지 않는다. 필요한 변경은 별도 모듈 update SQL 또는 운영 전용 마이그레이션으로 남긴다.
- 파일 경로를 DB에 직접 저장해 레이아웃 파일과 강하게 묶지 않는다. DB에는 안정적인 key나 소유 모듈의 식별자만 저장한다.

## 권장 구조

사이트 전용 기능은 다음 중 하나로 분리한다.

- 별도 모듈: 관리자 화면, public route, 권한, 설치/update SQL이 필요한 경우.
- 플러그인: 기존 모듈의 계약 파일, 출력 슬롯, 에디터, 결제 수단처럼 특정 확장점에 붙는 경우.
- 별도 확장 테이블: 기존 공식 레코드에 사이트 전용 표시값만 붙이고, 별도 화면이나 helper가 그 값을 읽는 경우.
- 별도 layout/theme/skin: 표시 구조만 바꾸고 공식 저장 정책을 그대로 쓰는 경우.

테이블 이름은 프로젝트 prefix와 소유 영역을 드러내야 한다.

```text
sr_site_content_main_sections
sr_custom_content_cards
sr_clientname_content_layout_settings
```

공식 레코드와 연결할 때는 안정 식별자를 사용한다.

```text
content_id
content_group_id
board_id
post_id
account_id
```

## 콘텐츠 메인 화면 예시

콘텐츠 메인 페이지에 추천 문구, 히어로 이미지, 카드 섹션, 운영자가 고른 콘텐츠 묶음을 표시하고 싶다면 공식 `sr_content_items`나 `sr_content_groups`에 컬럼을 추가하지 않는다.

권장 흐름:

1. 사이트 전용 테이블을 만든다.
2. 필요한 경우 사이트 전용 관리자 화면에서 값을 저장한다.
3. 배포판 `content.basic` 파일을 직접 수정하지 않고 별도 콘텐츠 layout/theme/skin 후보를 만든다.
4. 새 layout/theme/skin은 공식 콘텐츠 helper로 배포판 데이터를 읽고, 사이트 전용 helper로 확장 테이블 데이터를 함께 읽는다.
5. 콘텐츠 삭제, 그룹 삭제, 복사, sitemap, 개인정보 처리에 확장 데이터가 영향을 주는지 별도로 판단한다.

예시 테이블:

```sql
CREATE TABLE IF NOT EXISTS sr_site_content_main_sections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    section_key VARCHAR(60) NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    linked_content_id BIGINT UNSIGNED NULL,
    image_storage_key VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_site_content_main_sections_key (section_key),
    KEY idx_sr_site_content_main_sections_status_sort (status, sort_order, id),
    KEY idx_sr_site_content_main_sections_content (linked_content_id)
);
```

이 테이블은 배포판 콘텐츠 모듈이 아니라 사이트 전용 기능이 소유한다. 배포판 콘텐츠 업데이트는 이 테이블을 설치하거나 수정하거나 정리하지 않는다.

## 레이아웃과 DB 책임

레이아웃은 기본적으로 표시 책임만 가진다. `<head>`, 공통 header/footer, 메뉴 위치, 전체 폭 같은 문서 골격은 public layout이 맡고, 콘텐츠 목록/상세 같은 도메인 표시는 콘텐츠 모듈의 theme/skin 또는 별도 확장 모듈이 맡는다.

레이아웃 때문에 DB 테이블이나 컬럼이 필요해지는 순간, 그 기능은 단순 레이아웃이 아니라 저장 정책을 가진 확장 기능이다. 이때는 저장 helper, update SQL, 삭제/복사/권한/개인정보 정책을 함께 가진 별도 모듈 또는 명시적인 확장 테이블로 승격한다.

## 업데이트 영향

배포판 업데이트는 공식 테이블과 공식 파일을 기준으로 동작한다. 사이트 커스터마이징이 공식 테이블을 직접 변경하면 다음 문제가 생길 수 있다.

- 공식 update SQL이 같은 컬럼명을 추가하면서 충돌한다.
- 공식 저장 helper가 모르는 `NOT NULL` 컬럼 때문에 등록/수정이 실패한다.
- 콘텐츠 복사, 삭제, 리비전 생성, 개인정보 export/cleanup에서 사이트 전용 데이터가 누락된다.
- 배포판 view 교체 후 직접 수정한 화면 코드가 사라진다.
- 공식 helper의 반환 필드나 출력 순서가 바뀌어 커스텀 layout이 깨진다.

별도 확장 테이블을 쓰면 배포판 업데이트는 공식 스키마만 갱신하고, 사이트 전용 기능은 자기 update SQL과 검증 절차로 따로 관리할 수 있다.

## 삭제, 복사, 개인정보 처리

확장 데이터가 공식 레코드와 연결되면 다음 정책을 반드시 정한다.

- 공식 레코드가 삭제될 때 확장 데이터도 삭제할지, 보관할지.
- 공식 레코드를 복사할 때 확장 데이터도 복사할지, 새 기본값을 쓸지.
- 회원 계정과 연결되는 데이터가 개인정보 사본 제공에 포함되는지.
- 회원 탈퇴/익명화 시 account 연결을 제거해야 하는지.
- 저장소 파일을 쓰면 삭제 실패 기록과 재시도 흐름을 둘지.

이 정책이 없으면 화면은 동작해도 운영 중 고아 데이터나 개인정보 처리 누락이 생긴다.

## 구현 체크리스트

- [ ] 커스터마이징 데이터가 공식 모듈 테이블에 직접 들어가지 않는가.
- [ ] 별도 테이블 이름에 소유 영역과 site/module prefix가 드러나는가.
- [ ] 공식 레코드와 안정 식별자로 연결하는가.
- [ ] 별도 설치/update SQL이 있고, 반복 실행되어도 안전한가.
- [ ] 삭제, 복사, 개인정보 export/cleanup, 저장소 정리 정책을 정했는가.
- [ ] 배포판 view 파일을 직접 수정하지 않고 별도 layout/theme/skin 또는 출력 슬롯을 쓰는가.
- [ ] 배포판 업데이트 후 `/admin/updates`와 관련 public/admin 화면을 스모크 테스트하는가.

