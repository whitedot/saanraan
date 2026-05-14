<?php

$adminPageTitle = '디자인 토큰';
$adminPageSubtitle = 'assets/common.css에서 추출한 디자인 토큰과 클래스 항목을 확인합니다.';
$adminContainerClass = 'admin-page-design-system';

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-design-system">
    <nav class="tab-nav-bordered admin-design-system-nav" aria-label="디자인 토큰 미리보기 목차">
        <a class="tab-trigger-underline active" href="#ds-tokens">토큰 <?php echo sr_e((string) $commonCssTokenCount); ?></a>
        <a class="tab-trigger-underline" href="#ds-classes">클래스 <?php echo sr_e((string) $commonCssClassCount); ?></a>
        <a class="tab-trigger-underline" href="#ds-buttons">버튼/배지</a>
        <a class="tab-trigger-underline" href="#ds-forms">폼</a>
        <a class="tab-trigger-underline" href="#ds-data">카드/테이블</a>
        <a class="tab-trigger-underline" href="#ds-overlays">탭/모달</a>
    </nav>

    <section id="ds-tokens" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>디자인 토큰 전체</h2>
            <p><code>assets/common.css</code>의 CSS custom property를 전부 추출해 그룹별로 표시합니다. 다크 모드 등에서 값이 재정의된 토큰은 값이 여러 줄로 표시됩니다.</p>
        </div>
        <?php foreach ($commonCssTokenGroups as $groupLabel => $tokens) { ?>
            <div class="admin-design-system-row">
                <h3><?php echo sr_e((string) $groupLabel); ?> <span class="badge badge-label"><?php echo sr_e((string) count($tokens)); ?></span></h3>
                <div class="table-wrapper admin-design-system-table">
                    <table class="table table-sm">
                        <thead class="ui-table-head">
                            <tr>
                                <th>미리보기</th>
                                <th>토큰</th>
                                <th>값</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tokens as $token) { ?>
                                <tr>
                                    <td class="admin-design-system-preview-cell">
                                        <?php if ($token['category'] === '색상') { ?>
                                            <span class="admin-design-system-token-preview admin-design-system-token-preview-color" style="background: var(<?php echo sr_e((string) $token['name']); ?>);"></span>
                                        <?php } elseif ($token['category'] === '그림자') { ?>
                                            <span class="admin-design-system-token-preview" style="box-shadow: var(<?php echo sr_e((string) $token['name']); ?>);"></span>
                                        <?php } elseif ($token['category'] === '모서리') { ?>
                                            <span class="admin-design-system-token-preview" style="border-radius: var(<?php echo sr_e((string) $token['name']); ?>);"></span>
                                        <?php } else { ?>
                                            <span class="admin-design-system-token-preview"></span>
                                        <?php } ?>
                                    </td>
                                    <td><code><?php echo sr_e((string) $token['name']); ?></code></td>
                                    <td>
                                        <?php foreach ($token['values'] as $value) { ?>
                                            <code class="admin-design-system-code-line"><?php echo sr_e((string) $value); ?></code>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </section>

    <section id="ds-classes" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>common.css 클래스 전체</h2>
            <p><code>assets/common.css</code>에 정의된 클래스 선택자를 전부 추출해 그룹별로 나열합니다.</p>
        </div>
        <div class="admin-design-system-class-groups">
            <?php foreach ($commonCssClassGroups as $groupLabel => $classes) { ?>
                <section class="admin-design-system-class-group">
                    <h3><?php echo sr_e((string) $groupLabel); ?> <span class="badge badge-label"><?php echo sr_e((string) count($classes)); ?></span></h3>
                    <div class="admin-design-system-class-list">
                        <?php foreach ($classes as $className) { ?>
                            <code>.<?php echo sr_e((string) $className); ?></code>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>
        </div>
    </section>

    <section id="ds-buttons" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>버튼/배지 항목</h2>
            <p>버튼과 배지 관련 클래스는 전체 목록과 함께 실제 렌더링을 같이 확인합니다.</p>
        </div>
        <div class="admin-design-system-row">
            <h3>Button Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssButtonClasses)); ?></span></h3>
            <div class="admin-design-system-preview-grid">
                <?php foreach ($commonCssButtonClasses as $className) { ?>
                    <div class="admin-design-system-preview-item">
                        <?php
                        $buttonClass = 'btn ' . $className;
                        if ($className === 'btn') {
                            $buttonClass = 'btn';
                        } elseif ($className === 'btn-inline') {
                            $buttonClass = 'btn-inline';
                        } elseif ($className === 'btn-icon') {
                            $buttonClass = 'btn btn-icon btn-outline-primary';
                        } elseif ($className === 'btn-sm' || $className === 'btn-lg' || $className === 'btn-pill') {
                            $buttonClass = 'btn ' . $className . ' btn-outline-primary';
                        }
                        ?>
                        <button type="button" class="<?php echo sr_e($buttonClass); ?>">
                            <?php if ($className === 'btn-icon') { ?>
                                <span class="close-icon" aria-hidden="true"></span>
                            <?php } else { ?>
                                <?php echo sr_e($className); ?>
                            <?php } ?>
                        </button>
                        <code>.<?php echo sr_e($className); ?></code>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="admin-design-system-row">
            <h3>Badge Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssBadgeClasses)); ?></span></h3>
            <div class="admin-design-system-inline">
                <?php foreach ($commonCssBadgeClasses as $className) { ?>
                    <span class="<?php echo sr_e($className === 'badge-label' ? 'badge badge-label' : $className); ?>"><?php echo sr_e($className); ?></span>
                <?php } ?>
            </div>
        </div>
    </section>

    <section id="ds-forms" class="admin-design-system-panel ui-form-theme">
        <div class="admin-design-system-panel-header">
            <h2>폼 항목</h2>
            <p>폼 관련 클래스 전체 목록과 대표 컨트롤 렌더링입니다.</p>
        </div>
        <div class="admin-design-system-row">
            <h3>Form Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssFormClasses)); ?></span></h3>
            <div class="admin-design-system-class-list">
                <?php foreach ($commonCssFormClasses as $className) { ?>
                    <code>.<?php echo sr_e((string) $className); ?></code>
                <?php } ?>
            </div>
        </div>
        <div class="admin-design-system-form-grid">
            <label>
                <span class="form-label">form-input</span>
                <input type="text" class="form-input" value="Saanraan">
            </label>
            <label>
                <span class="form-label">form-input-sm</span>
                <input type="text" class="form-input form-input-sm" value="Small">
            </label>
            <label>
                <span class="form-label">form-input-lg</span>
                <input type="text" class="form-input form-input-lg" value="Large">
            </label>
            <label>
                <span class="form-label">form-select</span>
                <select class="form-select">
                    <option>기본 옵션</option>
                    <option>보조 옵션</option>
                </select>
            </label>
            <label class="admin-design-system-form-wide">
                <span class="form-label">form-textarea</span>
                <textarea class="form-textarea" rows="4">textarea sample</textarea>
            </label>
            <label>
                <span class="form-label">file form-input</span>
                <input type="file" class="form-input">
            </label>
        </div>
        <div class="admin-design-system-inline admin-design-system-control-row">
            <label class="af-check form-label"><input type="checkbox" class="form-checkbox" checked> form-checkbox</label>
            <label class="af-check form-label"><input type="radio" name="design_system_radio" class="form-radio" checked> form-radio</label>
            <label class="af-check form-label"><input type="checkbox" class="form-switch" checked> form-switch</label>
            <input type="range" class="form-range" value="60" aria-label="form-range">
        </div>
    </section>

    <section id="ds-data" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>카드/테이블/페이지 항목</h2>
            <p>카드, 테이블, 페이지네이션 관련 클래스 전체 목록과 대표 렌더링입니다.</p>
        </div>
        <div class="admin-design-system-grid">
            <div>
                <h3>Card Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssCardClasses)); ?></span></h3>
                <div class="admin-design-system-class-list">
                    <?php foreach ($commonCssCardClasses as $className) { ?>
                        <code>.<?php echo sr_e((string) $className); ?></code>
                    <?php } ?>
                </div>
            </div>
            <div>
                <h3>Table/Page Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssTableClasses)); ?></span></h3>
                <div class="admin-design-system-class-list">
                    <?php foreach ($commonCssTableClasses as $className) { ?>
                        <code>.<?php echo sr_e((string) $className); ?></code>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="admin-design-system-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">card-header</h3>
                    <span class="badge badge-label">card</span>
                </div>
                <div class="card-body">
                    <p>card-body</p>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-surface-default-soft">취소</button>
                    <button type="button" class="btn btn-solid-primary">저장</button>
                </div>
            </div>
            <div>
                <div class="table-wrapper">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>항목</th>
                                <th>상태</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>table</td>
                                <td><span class="badge">기본</span></td>
                            </tr>
                            <tr>
                                <td>table-hover</td>
                                <td><span class="badge badge-label">라벨</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="pagination admin-design-system-pagination" aria-label="페이지네이션 미리보기">
                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                    <li class="page-item active"><span class="page-link">1</span></li>
                    <li class="page-item"><a class="page-link" href="#ds-data">2</a></li>
                    <li class="page-item"><a class="page-link" href="#ds-data">›</a></li>
                </ul>
            </div>
        </div>
    </section>

    <section id="ds-overlays" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>탭/내비게이션/모달 항목</h2>
            <p>탭, 내비게이션, 모달/오버레이 관련 클래스 전체 목록과 대표 렌더링입니다.</p>
        </div>
        <div class="admin-design-system-grid">
            <div>
                <h3>Tab/Nav Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssTabClasses)); ?></span></h3>
                <div class="admin-design-system-class-list">
                    <?php foreach ($commonCssTabClasses as $className) { ?>
                        <code>.<?php echo sr_e((string) $className); ?></code>
                    <?php } ?>
                </div>
            </div>
            <div>
                <h3>Modal/Overlay Classes <span class="badge badge-label"><?php echo sr_e((string) count($commonCssModalClasses)); ?></span></h3>
                <div class="admin-design-system-class-list">
                    <?php foreach ($commonCssModalClasses as $className) { ?>
                        <code>.<?php echo sr_e((string) $className); ?></code>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="admin-design-system-grid">
            <div>
                <nav class="tab-nav-bordered-tight" aria-label="탭 미리보기">
                    <button type="button" class="tab-trigger-underline active">전체</button>
                    <button type="button" class="tab-trigger-underline">활성</button>
                    <button type="button" class="tab-trigger-underline">보류</button>
                </nav>
                <div class="tab-panel-space">
                    <p>tab-panel-space</p>
                </div>
            </div>
            <div class="modal-content">
                <div class="modal-header">
                    <strong class="modal-title">modal-title</strong>
                    <button type="button" class="btn btn-sm btn-icon modal-close" aria-label="닫기">
                        <span class="close-icon" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>modal-body</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-surface-default-soft">취소</button>
                    <button type="button" class="btn btn-solid-primary">확인</button>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
