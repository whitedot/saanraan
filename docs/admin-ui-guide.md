# 관리자 UI 작성 기준

관리자 화면은 G5 Codex 계열의 공통 UI 톤을 기준으로 맞춘다. `assets/common.css`는 버튼, 카드, 테이블, 폼 컨트롤 같은 디자인 시스템과 ui-kit 역할을 맡고, `modules/admin/assets/admin.css`는 사이드바, 상단바, 관리자 콘텐츠 폭, 목록/폼 배치 같은 관리자 전용 레이아웃만 정의한다.

관리자 디자인 책임은 admin 모듈에 둔다. 각 모듈의 관리자 view는 본문 마크업과 도메인 출력만 맡고, 관리자 shell, 사이드바, 상단바, 공통 관리자 asset, 관리자 콘텐츠 컨테이너는 admin skin이 맡는다. 현재 관리자 skin은 `admin_skin_key`로 선택하며, 등록된 key가 없거나 파일이 없으면 `basic`으로 fallback한다.

관리자 shell은 view 출력 뒤 `DOMDocument`로 HTML을 다시 해석하거나 class를 주입하지 않는다. 폼 행, 카드 헤더, 테이블 wrapper, 체크박스 보조 텍스트처럼 의미가 있는 구조는 view가 최종 마크업으로 직접 출력해야 한다.

CSS class는 범위를 드러내는 접두어를 사용한다.

- 반복 가능한 공통 UI는 `ui-*`, `btn`, `card`, `table`처럼 `assets/common.css`에 둔다.
- 관리자 shell과 관리자 전용 배치는 `admin-*` 접두어를 사용하고 `modules/admin/assets/admin.css`에 둔다.
- 모듈별 관리자 본문에서 도메인 고유 스타일이 필요하면 `{module_key}-admin-*` 또는 `sr-{module_key}-admin-*` 형식을 사용한다.
- 관리자 view는 전역 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 직접 재정의하지 않는다.

## 사이드바

사이드바는 모듈의 `admin.category_label`을 라벨로만 표시하고, 실제 조작 가능한 메뉴는 `모듈 그룹 > 메뉴 항목`의 2단계 구조로 유지한다. 라벨은 사이트 구성처럼 메뉴를 구분하는 시각적 구획일 뿐이며 접기/펼치기 버튼이나 링크로 사용하지 않는다.

## 페이지 타이틀

관리자 페이지의 주 타이틀은 admin skin이 출력하는 `#container_title` 하나를 기준으로 스타일링한다. 각 모듈 view는 `$adminPageTitle` 값만 지정하고, 페이지 최상단 `h1`을 별도로 반복하지 않는다.

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
- 섹션 제목은 `h2`를 사용한다. 카드 헤더가 별도로 필요하면 view에서 `card-header`, `card-title` 마크업을 직접 출력한다.
- 체크박스와 라디오처럼 컨트롤 옆 문구가 실제 레이블인 항목은 좌측 설명 칸과 우측 조작 레이블을 view에 직접 둔다. 좌측에는 전체 문구를 그대로 표시하고, 우측의 시각적 레이블은 `허용`, `사용`, `확인했습니다.`처럼 필요한 부분만 보이게 줄인다. 우측에서 생략된 맥락은 `.sr-only` 텍스트로 남겨 보조기기가 전체 의미를 읽을 수 있게 한다.
- 좌측 레이블이 필요 없는 안내 문장은 입력항목 행으로 만들지 말고 일반 텍스트 문단으로 둔다.
- 저장, 생성, 변경 버튼은 `admin-form-sticky-actions` 안에 둔다.
- 목록 화면의 검색 폼, 테이블 행의 상태 변경/삭제 폼, 툴바 폼은 페이지 폼 레이아웃을 적용하지 않는다.

체크박스 행 예시는 다음처럼 작성한다.

```php
<div class="af-grid">
    <div class="af-row">
        <div class="af-label"><span class="form-label">공개 회원가입 허용</span></div>
        <div class="af-field">
            <label class="af-check form-label">
                <input type="checkbox" name="allow_registration" value="1" class="form-checkbox">
                <?php echo sr_admin_choice_label_html('공개 회원가입 허용'); ?>
            </label>
        </div>
    </div>
</div>
```

## 목록 화면

목록형 화면은 회원 목록 화면을 기준으로 맞춘다.

- 상단 요약/탭 성격의 이동 링크는 `member-summary`와 `member-summary-links`를 사용한다.
- 목록 테이블은 `member-table-card admin-member-list-form` 섹션 안에서 `table-wrapper`와 `table`을 사용한다.
- 행 단위 관리 버튼은 `member-cell-manage`와 `member-manage` 안에 둔다.
