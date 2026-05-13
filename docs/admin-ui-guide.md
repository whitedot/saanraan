# 관리자 UI 작성 기준

관리자 화면은 G5 Codex 계열의 공통 UI 톤을 기준으로 맞춘다. `assets/common.css`는 버튼, 카드, 테이블, 폼 컨트롤 같은 디자인 시스템과 ui-kit 역할을 맡고, `modules/admin/assets/admin.css`는 사이드바, 상단바, 관리자 콘텐츠 폭, 목록/폼 배치 같은 관리자 전용 레이아웃만 정의한다.

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
- 저장, 생성, 변경 버튼은 `admin-form-sticky-actions` 안에 둔다.
- 목록 화면의 검색 폼, 테이블 행의 상태 변경/삭제 폼, 툴바 폼은 페이지 폼 레이아웃을 적용하지 않는다.

## 목록 화면

목록형 화면은 회원 목록 화면을 기준으로 맞춘다.

- 상단 요약/탭 성격의 이동 링크는 `member-summary`와 `member-summary-links`를 사용한다.
- 목록 테이블은 `member-table-card admin-member-list-form` 섹션 안에서 `table-wrapper`와 `table`을 사용한다.
- 행 단위 관리 버튼은 `member-cell-manage`와 `member-manage` 안에 둔다.
