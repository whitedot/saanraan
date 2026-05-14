# 관리자 UI 작성 기준

관리자 화면은 G5 Codex 계열의 공통 UI 톤을 기준으로 맞춘다. `assets/common.css`는 버튼, 카드, 테이블, 폼 컨트롤 같은 디자인 시스템과 ui-kit 역할을 맡고, `modules/admin/assets/admin.css`는 사이드바, 상단바, 관리자 콘텐츠 폭, 목록/폼 배치 같은 관리자 전용 레이아웃만 정의한다.

관리자 디자인 책임은 admin 모듈에 둔다. 각 모듈의 관리자 view는 본문 마크업과 도메인 출력만 맡고, 관리자 shell, 사이드바, 상단바, 공통 관리자 asset, 관리자 콘텐츠 컨테이너는 admin skin이 맡는다. 현재 관리자 skin은 `admin_skin_key`로 선택하며, 등록된 key가 없거나 파일이 없으면 `basic`으로 fallback한다.

CSS class는 범위를 드러내는 접두어를 사용한다.

- 반복 가능한 공통 UI는 `ui-*`, `btn`, `card`, `table`처럼 `assets/common.css`에 둔다.
- 관리자 shell과 관리자 전용 배치는 `admin-*` 접두어를 사용하고 `modules/admin/assets/admin.css`에 둔다.
- 모듈별 관리자 본문에서 도메인 고유 스타일이 필요하면 `{module_key}-admin-*` 또는 `sr-{module_key}-admin-*` 형식을 사용한다.
- 관리자 view는 전역 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 직접 재정의하지 않는다.

## 사이드바

사이드바는 모듈의 `admin.category_label`을 라벨로만 표시하고, 실제 조작 가능한 메뉴는 `모듈 그룹 > 메뉴 항목`의 2단계 구조로 유지한다. 라벨은 사이트 구성처럼 메뉴를 구분하는 시각적 구획일 뿐이며 접기/펼치기 버튼이나 링크로 사용하지 않는다.

## 폼 화면

등록, 수정, 설정처럼 화면의 주 작업이 폼인 페이지는 실제 view 마크업에서 다음 구조를 사용한다.

```php
<form method="post" action="..." class="admin-form-layout ui-form-theme ui-form-showcase">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2>기본 정보</h2>
        <p>
            <label>이름<br>
                <input type="text" name="title" required>
            </label>
        </p>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="..." class="btn btn-surface-default-soft">목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>
```

- 폼의 주 섹션은 `form > section.card`로 둔다.
- 섹션 제목은 `h2`를 사용한다. 공통 관리자 렌더링은 이를 `card-header`와 `card-title`로 정규화한다.
- 체크박스와 라디오처럼 컨트롤 옆 문구가 실제 레이블인 항목도 같은 `<label>` 안에 컨트롤과 문구를 함께 둔다. 공통 관리자 렌더링은 문구를 좌측 레이블 칸으로 옮기고 컨트롤은 우측 필드 칸에 둔다.
- 좌측 레이블이 필요 없는 안내 문장은 입력항목 행으로 만들지 말고 일반 텍스트 문단으로 둔다.
- 저장, 생성, 변경 버튼은 `admin-form-sticky-actions` 안에 둔다.
- 목록 화면의 검색 폼, 테이블 행의 상태 변경/삭제 폼, 툴바 폼은 페이지 폼 레이아웃을 적용하지 않는다.

## 목록 화면

목록형 화면은 회원 목록 화면을 기준으로 맞춘다.

- 상단 요약/탭 성격의 이동 링크는 `member-summary`와 `member-summary-links`를 사용한다.
- 목록 테이블은 `member-table-card admin-member-list-form` 섹션 안에서 `table-wrapper`와 `table`을 사용한다.
- 행 단위 관리 버튼은 `member-cell-manage`와 `member-manage` 안에 둔다.
