<?php

$adminPageTitle = '디자인 시스템';
$adminPageSubtitle = 'g5codex 원본 관리자 UI 조합을 기준으로 common.css와 admin.css를 확인합니다.';
$adminContainerClass = 'admin-page-design-system';

$sourceFiles = [
    ['role' => '공통 CSS 원본', 'path' => 'g5codex/css/common.css'],
    ['role' => '공통 CSS 소스', 'path' => 'g5codex/tailwind4/common.css'],
    ['role' => '관리자 CSS 원본', 'path' => 'g5codex/adm/css/admin.css'],
    ['role' => '관리자 CSS 소스', 'path' => 'g5codex/tailwind4/admin.css'],
    ['role' => '목록 마크업 기준', 'path' => 'g5codex/adm/member_list_parts/*.php'],
    ['role' => '폼 마크업 기준', 'path' => 'g5codex/adm/*_parts/form.php'],
];

$colorTokens = [
    ['label' => 'Primary', 'var' => '--color-primary'],
    ['label' => 'Secondary', 'var' => '--color-secondary'],
    ['label' => 'Success', 'var' => '--color-success'],
    ['label' => 'Info', 'var' => '--color-info'],
    ['label' => 'Warning', 'var' => '--color-warning'],
    ['label' => 'Danger', 'var' => '--color-danger'],
    ['label' => 'Default 100', 'var' => '--color-default-100'],
    ['label' => 'Default 300', 'var' => '--color-default-300'],
    ['label' => 'Default 700', 'var' => '--color-default-700'],
    ['label' => 'Default 900', 'var' => '--color-default-900'],
];

$primaryButtons = [
    ['label' => '검색', 'class' => 'btn btn-solid-primary'],
    ['label' => '저장', 'class' => 'btn btn-solid-primary'],
    ['label' => '만료 포인트 정산', 'class' => 'btn btn-solid-secondary'],
    ['label' => '목록', 'class' => 'btn btn-surface-default-soft'],
    ['label' => '수정', 'class' => 'btn btn-sm btn-surface-default-soft'],
    ['label' => '선택삭제', 'class' => 'btn btn-outline-danger'],
    ['label' => '선택삭제', 'class' => 'btn btn-sm btn-outline-danger'],
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-design-system">
    <nav class="tab-nav-bordered admin-design-system-nav" aria-label="디자인 시스템 미리보기 목차">
        <a class="tab-trigger-underline active" href="#ds-source">기준</a>
        <a class="tab-trigger-underline" href="#ds-foundation">토큰</a>
        <a class="tab-trigger-underline" href="#ds-buttons">버튼</a>
        <a class="tab-trigger-underline" href="#ds-list">목록</a>
        <a class="tab-trigger-underline" href="#ds-form">폼</a>
        <a class="tab-trigger-underline" href="#ds-feedback">피드백</a>
    </nav>

    <section id="ds-source" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>원본 확인 기준</h2>
            <p>이 화면은 g5codex 원본의 CSS 소스와 관리자 부분 템플릿에서 실제로 반복 사용된 조합을 기준으로 구성합니다.</p>
        </div>
        <div class="table-wrapper admin-design-system-table">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>역할</th>
                        <th>원본 경로</th>
                        <th>이 preview에서 확인하는 내용</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sourceFiles as $sourceFile) { ?>
                        <tr>
                            <td><?php echo sr_e($sourceFile['role']); ?></td>
                            <td><code><?php echo sr_e($sourceFile['path']); ?></code></td>
                            <td>
                                <?php if ($sourceFile['role'] === '공통 CSS 원본') { ?>
                                    버튼, 배지, 카드, 폼, 테이블, 탭, 모달 기본 클래스
                                <?php } elseif ($sourceFile['role'] === '관리자 CSS 원본') { ?>
                                    관리자 shell, 목록, 폼, 상태, 안내 메시지 보조 클래스
                                <?php } elseif ($sourceFile['role'] === '목록 마크업 기준') { ?>
                                    <code>member-summary</code>, <code>member-search-card</code>, <code>member-table-card</code>
                                <?php } elseif ($sourceFile['role'] === '폼 마크업 기준') { ?>
                                    <code>admin-form-layout</code>, <code>af-grid</code>, <code>af-row</code>, <code>af-inline</code>
                                <?php } else { ?>
                                    Tailwind source 기준 토큰과 컴포넌트 정의
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="ds-foundation" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>기본 토큰</h2>
            <p><code>g5codex/tailwind4/common.css</code>의 theme token을 현재 CSS 변수 렌더링으로 확인합니다.</p>
        </div>
        <div class="admin-design-system-color-grid">
            <?php foreach ($colorTokens as $token) { ?>
                <div class="admin-design-system-swatch">
                    <span class="admin-design-system-swatch-color" style="background: var(<?php echo sr_e($token['var']); ?>);"></span>
                    <strong><?php echo sr_e($token['label']); ?></strong>
                    <code><?php echo sr_e($token['var']); ?></code>
                </div>
            <?php } ?>
        </div>
        <div class="admin-design-system-type-grid">
            <div><span class="admin-design-system-type-xs">Text XS</span><code>--text-xs</code></div>
            <div><span class="admin-design-system-type-sm">Text SM</span><code>--text-sm</code></div>
            <div><span class="admin-design-system-type-base">Text Base</span><code>--text-base</code></div>
            <div><span class="admin-design-system-type-lg">Text LG</span><code>--text-lg</code></div>
            <div><span class="admin-design-system-type-xl">Text XL</span><code>--text-xl</code></div>
        </div>
    </section>

    <section id="ds-buttons" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>버튼</h2>
            <p>원본 관리자 화면에서 실제로 반복 사용된 버튼 조합을 우선 표시합니다.</p>
        </div>
        <div class="admin-design-system-row">
            <h3>관리자 주요 작업 버튼</h3>
            <div class="admin-design-system-inline">
                <?php foreach ($primaryButtons as $button) { ?>
                    <button type="button" class="<?php echo sr_e($button['class']); ?>"><?php echo sr_e($button['label']); ?></button>
                <?php } ?>
            </div>
        </div>
        <div class="admin-design-system-row">
            <h3>원본 common.css 기본 요소</h3>
            <div class="admin-design-system-inline">
                <span class="badge">badge</span>
                <span class="badge badge-label">badge-label</span>
                <button type="button" class="btn btn-icon btn-outline-primary" aria-label="아이콘 버튼">
                    <span class="close-icon" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </section>

    <section id="ds-list" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>목록 화면 패턴</h2>
            <p><code>g5codex/adm/member_list_parts</code>와 배너/게시판 목록의 실제 구조를 기준으로 합니다.</p>
        </div>

        <div class="member-summary">
            <div class="member-summary-links">
                <a href="#ds-list" class="btn btn-surface-default-soft">전체 보기</a>
                <a href="#ds-list" class="btn btn-solid-primary">항목 추가</a>
            </div>
            <div class="member-summary-stats">
                <span class="member-summary-meta">총 항목 <strong>128</strong></span>
                <a href="#ds-list" class="member-summary-meta">정상 120</a>
                <a href="#ds-list" class="member-summary-meta">차단 8</a>
            </div>
        </div>

        <div class="member-search-card">
            <form method="get" action="<?php echo sr_e(sr_url('/admin/design-system')); ?>">
                <div class="member-search-fields community-search-fields community-search-fields-wide">
                    <div class="member-field">
                        <label for="preview_search_field" class="member-field-label">검색대상</label>
                        <select name="preview_search_field" id="preview_search_field" class="form-select member-field-input">
                            <option>이름</option>
                            <option>이메일</option>
                            <option>상태</option>
                        </select>
                    </div>
                    <div class="member-field">
                        <label for="preview_search_keyword" class="member-field-label">검색어</label>
                        <input type="text" name="preview_search_keyword" value="" id="preview_search_keyword" class="form-input member-field-input" placeholder="검색어를 입력하세요">
                    </div>
                    <button type="submit" class="btn btn-solid-primary member-search-submit">검색</button>
                </div>
            </form>
        </div>

        <form method="post" action="<?php echo sr_e(sr_url('/admin/design-system')); ?>" class="admin-member-list-form">
            <div class="member-table-card">
                <div class="table-wrapper">
                    <table class="table community-list-table">
                        <caption>원본 목록 테이블 패턴</caption>
                        <thead class="ui-table-head">
                            <tr>
                                <th scope="col">항목</th>
                                <th scope="col">상태</th>
                                <th scope="col">날짜</th>
                                <th scope="col" class="text-end">관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="community-primary">
                                        <strong>common.css</strong>
                                        <span>#ui-kit</span>
                                    </div>
                                </td>
                                <td><span class="community-status is-active">활성</span></td>
                                <td class="community-date">2026-05-14</td>
                                <td class="text-end">
                                    <div class="member-manage">
                                        <a href="#ds-list" class="btn btn-sm btn-surface-default-soft">수정</a>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="community-primary">
                                        <strong>admin.css</strong>
                                        <span>#admin</span>
                                    </div>
                                </td>
                                <td><span class="community-status is-hidden">숨김</span></td>
                                <td class="community-date">2026-05-14</td>
                                <td class="text-end">
                                    <div class="member-manage">
                                        <a href="#ds-list" class="btn btn-sm btn-surface-default-soft">수정</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="member-list-actions">
                    <div class="ui-table-actions">
                        <button type="button" class="btn btn-outline-danger">선택삭제</button>
                    </div>
                    <a href="#ds-list" class="btn btn-surface-default-soft">회원추가</a>
                </div>
            </div>
        </form>
    </section>

    <section id="ds-form" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>폼 화면 패턴</h2>
            <p><code>admin-form-layout ui-form-theme ui-form-showcase</code>와 <code>af-*</code> 행 구조를 원본 그대로 확인합니다.</p>
        </div>

        <form method="post" action="<?php echo sr_e(sr_url('/admin/design-system')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase admin-design-system-form-sample">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">기본 설정</h2>
                </div>
                <div class="card-body">
                    <div class="af-grid">
                        <div class="af-row">
                            <div class="af-label">
                                <label for="preview_title" class="form-label">이름<strong class="caption-sr-only">필수</strong></label>
                            </div>
                            <div class="af-field">
                                <input type="text" name="preview_title" value="Saanraan" id="preview_title" class="required form-input" required>
                            </div>
                        </div>
                        <div class="af-row">
                            <div class="af-label">
                                <label for="preview_status" class="form-label">상태</label>
                            </div>
                            <div class="af-field">
                                <select name="preview_status" id="preview_status" class="form-select">
                                    <option>활성</option>
                                    <option>숨김</option>
                                    <option>보류</option>
                                </select>
                            </div>
                        </div>
                        <div class="af-row">
                            <div class="af-label">
                                <label for="preview_summary" class="form-label">설명</label>
                            </div>
                            <div class="af-field">
                                <textarea name="preview_summary" id="preview_summary" rows="4" class="form-textarea">원본 폼 행 구조의 textarea입니다.</textarea>
                            </div>
                        </div>
                        <div class="af-row">
                            <div class="af-label">
                                <span class="form-label">사용 기능</span>
                            </div>
                            <div class="af-field">
                                <div class="af-inline">
                                    <label class="af-check form-label"><input type="checkbox" name="preview_feature[]" value="category" class="form-checkbox" checked><span class="form-label">카테고리</span></label>
                                    <label class="af-check form-label"><input type="checkbox" name="preview_feature[]" value="latest" class="form-checkbox" checked><span class="form-label">최신글</span></label>
                                    <label class="af-check form-label"><input type="checkbox" name="preview_feature[]" value="comment" class="form-checkbox"><span class="form-label">댓글</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="af-row">
                            <div class="af-label">
                                <span class="form-label">노출 기간</span>
                            </div>
                            <div class="af-field">
                                <div class="af-inline">
                                    <label for="preview_started_date" class="ui-form-inline-note">시작</label>
                                    <input type="date" name="preview_started_date" id="preview_started_date" class="form-input af-input-sm">
                                    <input type="time" name="preview_started_time" class="form-input af-input-sm">
                                    <label for="preview_ended_date" class="ui-form-inline-note">종료</label>
                                    <input type="date" name="preview_ended_date" id="preview_ended_date" class="form-input af-input-sm">
                                    <input type="time" name="preview_ended_time" class="form-input af-input-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
                <a href="#ds-form" class="btn btn-surface-default-soft">목록</a>
                <button type="button" class="btn btn-solid-primary">저장</button>
            </div>
        </form>
    </section>

    <section id="ds-feedback" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>피드백과 상태</h2>
            <p>원본 관리자 CSS에 있는 flash, notice, status 클래스를 현재 화면에서 확인합니다.</p>
        </div>
        <div class="admin-design-system-grid">
            <div class="admin-design-system-feedback-stack">
                <div class="admin-flash-message admin-flash-message-success">저장완료</div>
                <div class="admin-flash-message admin-flash-message-error">입력값을 확인해주세요.</div>
                <div class="member-notice">
                    <span class="member-notice-icon" aria-hidden="true">i</span>
                    <div class="member-notice-copy">
                        <strong>회원 관리 안내</strong>
                        <p>상태 변경과 삭제 같은 작업 전 안내 문구를 표시합니다.</p>
                    </div>
                </div>
            </div>
            <div class="admin-design-system-feedback-stack admin-member-list-form">
                <span class="member-status is-normal">정상</span>
                <span class="member-status is-blocked">차단</span>
                <span class="member-status is-left">탈퇴</span>
                <span class="community-status is-active">활성</span>
                <span class="community-status is-warning">주의</span>
                <span class="community-status is-deleted">삭제</span>
            </div>
        </div>
    </section>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
