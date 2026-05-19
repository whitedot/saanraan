# 관리자 화면 시맨틱 DOM 전환 계획

## 목적

관리자 화면의 HTML 구조와 CSS class 이름을 관리자 런타임 UI-KIT의 컴포넌트 원형에 맞춰 정리한다. 목표는 화면별로 우연히 맞아 보이는 스타일을 줄이고, 카드, 폼, 필터, 표, 탭, 액션 영역이 같은 DOM 패턴으로 반복되게 만드는 것이다.

이 계획은 새 라우팅 방식이나 자동 class 주입을 만들지 않는다. 각 관리자 view가 의미 있는 DOM을 직접 출력하고, CSS는 그 의미 구조를 안정적으로 꾸미는 역할만 맡는다.

2026-05-18 기준으로 관리자 GET 화면의 공통 카드, 폼, 필터, 목록, 행 액션 DOM은 이 계획의 `admin-*` 원형으로 1차 전환했다. 배포 전 상태이므로 과거 `member-*`, `af-*` 호환 class를 병기하지 않고 새 구조를 직접 적용했다.

텍스트 색상과 크기는 기본 상속을 우선한다. `html`, `body`, `.admin-content`의 기본 글자색을 충분히 진하게 두고, 기본 글자 크기는 `16px`로 고정한다. view 또는 모듈 CSS는 일반 라벨, 표 셀, 제목에 옅은 회색이나 작은 글자 크기를 개별 지정하지 않는다. 도움말, 힌트, 빈 상태, 보조 메타처럼 우선순위가 낮은 텍스트만 muted 계열 class나 작은 크기 전용 선택자를 사용한다.

화면 내 이동 링크는 사이드 메뉴와 중복하지 않는다. 사이드 메뉴에 등록된 관리자 화면 전환은 shell navigation이 맡고, view 본문은 현재 화면의 작업, 필터, 목록, 폼만 출력한다. `admin-local-nav`는 사이드 메뉴에 없는 하위 흐름이나 상태 필터에 한해 사용한다.

## 참고 기준

- `/admin/ui-kit`: 관리자 런타임 기준 UI-KIT 조회 화면
- `/admin/ui-kit-public`: public layout 기준 UI-KIT 조회 화면
- `modules/admin/views/ui-kit-samples/ui-cards.php`: `card`, `card-header`, `card-title`, `card-body`
- `modules/admin/views/ui-kit-samples/form-elements.php`: `ui-form-theme`, `form-label`, `form-input`, `form-select`, `form-textarea`, `form-checkbox`
- `modules/admin/views/ui-kit-samples/tables-static.php`: `table-wrapper`, `table`, `thead`, `tbody`
- `modules/admin/views/ui-kit-samples/ui-tabs.php`: `tab-nav`, `tab-nav-bordered`, `tab-trigger-underline`
- `docs/admin-ui-guide.md`: 관리자 UI 작성 기준
- `assets/common/tokens.css`: 사이트 공통 디자인 토큰
- `modules/admin/assets/admin.css`: 관리자 reset/base 및 실제 관리자 의미 클래스

## 명명 원칙

| 계층 | 사용 class | 책임 |
| --- | --- | --- |
| 공통 UI | `card`, `btn`, `table`, `table-wrapper`, `tab-*` | 공통 원형 |
| 관리자 UI | `admin-*`, `admin-ui-*`, `form-*` | 관리자 shell, 운영 화면 밀도, 관리자 전용 조합과 입력 원형 |
| 도메인 관리자 | `{module_key}-admin-*` | 특정 모듈 화면에서만 필요한 도메인 표현 |
| 도메인 잔여 | `member-*`, `community-*` | 실제 회원·커뮤니티 도메인 표현에만 제한 |

새 관리자 화면에는 `member-table-card`, `member-summary`, `member-manage` 같은 도메인 오해가 생기는 class를 추가하지 않는다. 배포 전 관리자 화면은 호환 alias 병기 없이 `admin-*` 원형으로 직접 전환한다.

## 목표 DOM 원형

### 1. 페이지 본문

관리자 shell은 이미 페이지 제목과 breadcrumb를 출력한다. 개별 view는 본문에 필요한 기능 블록만 둔다.

```php
<?php
$adminPageTitle = '회원관리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-page-actions">
    ...
</div>

<section class="admin-card card">
    ...
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
```

권장 class:

- `admin-page-actions`: 현재 화면의 보조 액션 묶음. 사이드 메뉴와 같은 화면 이동 링크는 두지 않는다.
- `admin-page-intro`: 화면 설명이 실제로 필요할 때만 사용
- `admin-card card`: 관리자 카드. 기존 `member-table-card`의 대체 이름

### 2. 카드

공통 UI의 카드 원형을 따른다.

```php
<section class="admin-card card">
    <div class="card-header">
        <div>
            <h2 class="card-title">게시판 목록</h2>
            <p class="admin-card-subtitle">활성 게시판과 노출 상태를 확인합니다.</p>
        </div>
        <a href="..." class="btn btn-sm btn-surface-default-soft">새 게시판 추가</a>
    </div>
    <div class="card-body">
        ...
    </div>
</section>
```

규칙:

- 카드 안에 또 다른 카드 형태의 floating panel을 넣지 않는다.
- 반복 목록 항목이나 모달처럼 실제로 프레임이 필요한 곳에만 카드를 쓴다.
- `h2`는 카드 제목, `h3`는 카드 안의 하위 묶음 제목으로 제한한다.

### 3. 폼

관리자 폼은 `ui-form-theme`와 `admin-form` 조합을 쓴다. 기존 `admin-form-layout` 대신 새 원형인 `admin-form`을 기준으로 한다.

```php
<form method="post" action="..." class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title">기본 정보</h2>
        </div>
        <div class="card-body admin-form-body">
            <div class="admin-form-row">
                <div class="admin-form-label">
                    <label for="board_name" class="form-label">게시판 이름</label>
                </div>
                <div class="admin-form-field">
                    <input id="board_name" type="text" name="name" class="form-input" required>
                    <p class="admin-form-help">관리자와 사용자 화면에 표시되는 이름입니다.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="admin-form-actions admin-form-actions-split">
        <a href="..." class="btn btn-surface-default-soft">목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>
```

기존 대응:

| 현재 | 목표 |
| --- | --- |
| `admin-form-layout ui-form-theme ui-form-showcase` | `admin-form ui-form-theme` |
| `af-row` | `admin-form-row` |
| `af-label` | `admin-form-label` |
| `af-field` | `admin-form-field` |
| `sr-install-help` | `admin-form-help` |
| `af-check` | `admin-form-check` |

전환 중에는 같은 요소에 기존 class와 새 class를 함께 붙인다.

```php
<div class="af-row admin-form-row">
    <div class="af-label admin-form-label">...</div>
    <div class="af-field admin-form-field">...</div>
</div>
```

### 4. 필터

필터는 “폼 화면”이 아니라 “목록 범위 조정 도구”다. 카드 본문 또는 목록 위에 다음 구조로 둔다.

```php
<form method="get" action="..." class="admin-filter ui-form-theme">
    <div class="admin-filter-header">
        <strong class="admin-filter-title">검색</strong>
        <a href="..." class="btn btn-sm btn-surface-default-soft">초기화</a>
    </div>
    <div class="admin-filter-grid">
        <label class="admin-filter-field">
            <span class="admin-filter-label">상태</span>
            <select name="status" class="form-select">...</select>
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
    </div>
</form>
```

기존 대응:

| 현재 | 목표 |
| --- | --- |
| `admin-filter-form` | `admin-filter` |
| `admin-filter-heading` | `admin-filter-header` |
| `admin-filter-fields` | `admin-filter-grid` |

### 5. 목록과 표

공통 UI의 `table-wrapper > table.table`을 유지하되 관리자 의미 class를 추가한다.

```php
<section class="admin-card admin-list-card card">
    <div class="card-header">
        <h2 class="card-title">회원 목록</h2>
        <a href="..." class="btn btn-sm btn-surface-default-soft">내보내기</a>
    </div>
    <div class="card-body">
        <div class="table-wrapper admin-table-wrapper" tabindex="0">
            <table class="table admin-table">
                <thead class="ui-table-head">
                    ...
                </thead>
                <tbody>
                    <tr>
                        <td>...</td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a class="btn btn-sm btn-surface-default-soft" href="...">수정</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
```

기존 대응:

| 현재 | 목표 |
| --- | --- |
| `member-table-card admin-member-list-form` | `admin-card admin-list-card card` |
| `member-cell-manage` | `admin-table-actions-cell` |
| `member-manage` | `admin-row-actions` |
| `admin-dashboard-empty` | `admin-empty-state` |

모바일 정책:

- 표를 카드형으로 바꿀 화면과 가로 스크롤 표로 유지할 화면을 구분한다.
- 열 수가 많고 행별 비교가 중요한 화면은 `table-wrapper` 가로 스크롤을 유지한다.
- 모바일에서 주 데이터가 3개 이하이고 액션 중심이면 카드형 목록을 우선한다.

### 6. 탭과 로컬 내비게이션

공통 UI의 탭 구조를 따른다. 페이지 이동용 링크 묶음은 탭이 아니라 페이지 액션/로컬 내비게이션으로 분리한다.

```php
<nav class="tab-nav-bordered admin-tabs" aria-label="모듈 관리" data-admin-tabs>
    <button type="button" class="tab-trigger-underline active" data-admin-tab-target="installed">설치된 모듈</button>
    <button type="button" class="tab-trigger-underline" data-admin-tab-target="installable">설치 가능한 모듈</button>
</nav>
```

```php
<nav class="admin-local-nav" aria-label="커뮤니티 관리자 메뉴">
    <a href="..." class="btn btn-surface-default-soft">커뮤니티 설정</a>
    <a href="..." class="btn btn-surface-default-soft">게시판 목록</a>
</nav>
```

기존 대응:

| 현재 | 목표 |
| --- | --- |
| `member-summary` | `admin-local-nav-wrap` 또는 `admin-page-actions` |
| `member-summary-links` | `admin-local-nav` |
| 임의 버튼 목록 | 링크 목적에 따라 `admin-local-nav` 또는 `admin-page-actions` |

### 7. 상태와 피드백

상태 표시는 공통 UI의 badge 계열을 관리자 의미 class와 결합한다.

```php
<span class="badge badge-label admin-status admin-status-active">사용</span>
```

토스트와 오류는 기존 helper를 유지한다.

```php
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
```

빈 상태는 표 안 `td colspan`에만 두지 않고 의미 class를 통일한다.

```php
<tr>
    <td colspan="5" class="admin-empty-state">등록된 항목이 없습니다.</td>
</tr>
```

## 적용 순서

### 1단계: CSS 원형 추가

대상:

- `assets/admin-ui.css`
- `assets/common/tokens.css`
- `modules/admin/assets/admin.css`

작업:

- 공통 토큰은 `assets/common/tokens.css`에 둔다. 관리자 reset/base, 반복 UI 원형, 관리자 입력 필드의 `form-*` 기본 스타일은 `modules/admin/assets/admin.css`가 소유한다.
- 관리자 런타임은 `assets/common/tokens.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css` 순서로 호출한다.
- `admin-card`, `admin-list-card`, `admin-table`, `admin-row-actions`, `admin-form`, `admin-form-row`, `admin-filter` 계열 class를 추가한다.
- `admin-*` 원형 class가 직접 스타일을 받도록 CSS를 둔다.
- 이 단계에서는 view 변경을 최소화하고 시각 회귀를 막는다.

검증:

- `/admin/settings`
- `/admin/members`
- `/admin/community/boards`
- `/admin/modules`
- `/admin/audit-logs`

### 2단계: 폼 DOM 전환

우선순위:

1. `/admin/settings`, `/admin/member-settings`, `/admin/community/settings`, `/admin/seo`
2. 생성/수정 폼: 배너, 팝업레이어, 사이트 메뉴, 커뮤니티 게시판/그룹
3. 포인트/예치금/적립금 조정 폼

작업:

- `admin-form-layout`을 `admin-form`으로 바꾼다.
- `af-row`, `af-label`, `af-field`, `af-check`를 `admin-form-*` class로 바꾼다.
- `id`와 `for`를 추가해 라벨과 입력을 직접 연결한다.
- 도움말은 `admin-form-help`로 통일한다.

검증 기준:

- 데스크톱에서 라벨과 입력이 같은 행으로 연결되어 보인다.
- 모바일에서 1열로 접히고 입력이 화면 폭을 넘지 않는다.
- sticky 액션이 입력 영역을 가리지 않는다.

### 3단계: 목록/표 DOM 전환

우선순위:

1. `/admin/members`, `/admin/roles`
2. `/admin/community/boards`, `/admin/community/posts`, `/admin/community/comments`
3. `/admin/modules`, `/admin/audit-logs`
4. 배너, 팝업레이어, 사이트 메뉴, 개인정보 요청, 알림

작업:

- `member-table-card admin-member-list-form`을 `admin-card admin-list-card card admin-list-form`으로 바꾼다.
- `member-cell-manage`를 `admin-table-actions-cell`로 전환한다.
- `member-manage`를 `admin-row-actions`로 전환한다.
- 빈 상태는 `admin-empty-state`로 전환한다.
- 모바일 카드형 목록이 필요한 화면은 별도 `admin-list-stack` 원형을 만든 뒤 적용한다.

검증 기준:

- 모바일에서 한 글자 단위 줄바꿈이 없다.
- 가로 스크롤이 필요한 표는 카드 내부에서만 스크롤된다.
- 행 액션 버튼이 화면 오른쪽에서 잘리지 않는다.

### 4단계: 필터와 페이지 액션 전환

대상:

- `admin-filter-form` 사용 화면 전체
- `member-summary`, `member-summary-links` 사용 화면 전체

작업:

- `admin-filter-form`을 `admin-filter`로 바꾼다.
- `admin-filter-heading`을 `admin-filter-header`로, `admin-filter-fields`를 `admin-filter-grid`로 바꾼다.
- 페이지 이동 링크 묶음은 `admin-local-nav` 또는 `admin-page-actions`로 교체한다.

검증 기준:

- 필터가 데스크톱에서 불필요하게 좁게 쌓이지 않는다.
- 모바일에서 필터 필드가 화면 밖으로 나가지 않는다.
- 탭이 아닌 페이지 이동 링크가 탭처럼 보이지 않는다.

### 5단계: 기존 class 정리

조건:

- 모든 관리자 view에 새 class가 전환되어 있고 브라우저 검증이 끝난 뒤 진행한다.

작업:

- `member-*`가 관리자 공통 의미로 쓰이는 부분을 제거한다.
- `af-*`는 `admin-form-*`로 교체한다.
- 더 이상 쓰지 않는 과거 관리자 공통 선택자를 삭제한다.

주의:

- 공개 회원 화면이나 member 모듈의 실제 도메인 UI에서 쓰는 `member-*` class는 제거하지 않는다.
- 커뮤니티 도메인 전용 표현은 `community-admin-*`로 남길 수 있다.

## 화면별 적용 매트릭스

| 화면군 | 주요 변경 | 우선순위 |
| --- | --- | --- |
| 관리자 설정 | `admin-form`, `admin-form-row`, `admin-form-help` | 높음 |
| 회원 관리 | `admin-list-card`, `admin-table`, `admin-row-actions` | 높음 |
| 커뮤니티 관리 | `admin-local-nav`, `admin-list-card`, `community-admin-*` | 높음 |
| 모듈 관리 | `admin-tabs`, `admin-list-card`, 긴 열 요약 DOM | 높음 |
| 감사 로그 | `admin-filter`, `admin-table`, metadata disclosure | 높음 |
| 배너/팝업/사이트 메뉴 | 폼과 목록 원형 동시 적용 | 중간 |
| 포인트/예치금/적립금 | 필터, 조정 폼, 최근 목록 원형 적용 | 중간 |
| 개인정보/알림 | 필터, 목록, 행 액션 원형 적용 | 중간 |

## 브라우저 검증 계획

각 단계마다 최소 다음 화면을 확인한다.

- 데스크톱 `1440x1000`
- 모바일 `390x900`
- `/admin/settings`
- `/admin/members`
- `/admin/community/boards`
- `/admin/modules`
- `/admin/audit-logs`
- 해당 단계에서 직접 바꾼 모듈 화면 2개 이상

확인 항목:

- 문서 전체 수평 overflow가 없다.
- 카드 내부 표는 필요한 경우에만 내부 가로 스크롤을 만든다.
- 라벨과 입력 필드의 관계가 시각적으로 끊기지 않는다.
- 버튼 그룹이 입력 또는 표 내용을 덮지 않는다.
- 토스트, 드롭다운, details 패널이 카드 밖에서 잘리지 않는다.

## 문서 업데이트 기준

DOM 원형이나 class 이름이 바뀌면 다음 문서를 함께 갱신한다.

- `docs/admin-ui-guide.md`
- 이 계획 문서
- 필요 시 GitHub Wiki의 관리자 화면 필드 가이드 또는 개발자 가이드

단순히 화면별 view에 새 class를 적용하는 작업은 이 계획의 범위 안에서 진행된 것으로 보고, 별도 Wiki 갱신은 화면 동작이나 필드 의미가 바뀔 때만 한다.
