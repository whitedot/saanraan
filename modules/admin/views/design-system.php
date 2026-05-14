<?php

$adminPageTitle = '디자인 시스템';
$adminPageSubtitle = 'assets/common.css 기본 UI와 관리자 보조 패턴을 한 화면에서 확인합니다.';
$adminContainerClass = 'admin-page-design-system';

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

$buttonGroups = [
    'Solid' => [
        ['label' => 'Primary', 'class' => 'btn btn-solid-primary'],
        ['label' => 'Secondary', 'class' => 'btn btn-solid-secondary'],
        ['label' => 'Success', 'class' => 'btn btn-solid-success'],
        ['label' => 'Info', 'class' => 'btn btn-solid-info'],
        ['label' => 'Warning', 'class' => 'btn btn-solid-warning'],
        ['label' => 'Danger', 'class' => 'btn btn-solid-danger'],
        ['label' => 'Dark', 'class' => 'btn btn-solid-dark'],
    ],
    'Outline' => [
        ['label' => 'Primary', 'class' => 'btn btn-outline-primary'],
        ['label' => 'Secondary', 'class' => 'btn btn-outline-secondary'],
        ['label' => 'Success', 'class' => 'btn btn-outline-success'],
        ['label' => 'Info', 'class' => 'btn btn-outline-info'],
        ['label' => 'Warning', 'class' => 'btn btn-outline-warning'],
        ['label' => 'Danger', 'class' => 'btn btn-outline-danger'],
    ],
    'Soft / Ghost' => [
        ['label' => 'Soft Primary', 'class' => 'btn btn-soft-primary'],
        ['label' => 'Soft Info', 'class' => 'btn btn-soft-info'],
        ['label' => 'Ghost Primary', 'class' => 'btn btn-ghost-primary'],
        ['label' => 'Ghost Danger', 'class' => 'btn btn-ghost-danger'],
        ['label' => 'Surface', 'class' => 'btn btn-surface-default-soft'],
        ['label' => 'Inline', 'class' => 'btn-inline'],
    ],
    'Size' => [
        ['label' => 'Small', 'class' => 'btn btn-sm btn-solid-primary'],
        ['label' => 'Default', 'class' => 'btn btn-solid-primary'],
        ['label' => 'Large', 'class' => 'btn btn-lg btn-solid-primary'],
        ['label' => 'Pill', 'class' => 'btn btn-pill btn-outline-primary'],
    ],
];

$badges = [
    ['label' => '기본', 'class' => 'badge'],
    ['label' => '라벨', 'class' => 'badge badge-label'],
    ['label' => '성공', 'class' => 'badge badge-label btn-soft-success'],
    ['label' => '위험', 'class' => 'badge badge-label btn-soft-danger'],
    ['label' => '정보', 'class' => 'badge badge-label btn-soft-info'],
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-design-system">
    <nav class="tab-nav-bordered admin-design-system-nav" aria-label="디자인 시스템 미리보기 목차">
        <a class="tab-trigger-underline active" href="#ds-foundation">토큰</a>
        <a class="tab-trigger-underline" href="#ds-buttons">버튼</a>
        <a class="tab-trigger-underline" href="#ds-forms">폼</a>
        <a class="tab-trigger-underline" href="#ds-data">데이터</a>
        <a class="tab-trigger-underline" href="#ds-feedback">피드백</a>
    </nav>

    <div id="ds-foundation" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>토큰</h2>
            <p>색상, 글자 크기, 간격 변수의 실제 렌더링을 확인합니다.</p>
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
    </div>

    <div id="ds-buttons" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>버튼과 배지</h2>
            <p>공통 버튼 계열과 상태 배지의 조합을 확인합니다.</p>
        </div>
        <?php foreach ($buttonGroups as $groupLabel => $buttons) { ?>
            <div class="admin-design-system-row">
                <h3><?php echo sr_e((string) $groupLabel); ?></h3>
                <div class="admin-design-system-inline">
                    <?php foreach ($buttons as $button) { ?>
                        <button type="button" class="<?php echo sr_e($button['class']); ?>"><?php echo sr_e($button['label']); ?></button>
                    <?php } ?>
                    <?php if ($groupLabel === 'Size') { ?>
                        <button type="button" class="btn btn-icon btn-outline-primary" aria-label="닫기 아이콘">
                            <span class="close-icon" aria-hidden="true"></span>
                        </button>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <div class="admin-design-system-row">
            <h3>Badge</h3>
            <div class="admin-design-system-inline">
                <?php foreach ($badges as $badge) { ?>
                    <span class="<?php echo sr_e($badge['class']); ?>"><?php echo sr_e($badge['label']); ?></span>
                <?php } ?>
            </div>
        </div>
    </div>

    <div id="ds-forms" class="admin-design-system-panel ui-form-theme">
        <div class="admin-design-system-panel-header">
            <h2>폼</h2>
            <p>입력 필드, 선택 필드, 체크박스, 라디오, 스위치 상태를 확인합니다.</p>
        </div>
        <div class="admin-design-system-form-grid">
            <label>
                <span class="form-label">텍스트 입력</span>
                <input type="text" class="form-input" value="Saanraan">
            </label>
            <label>
                <span class="form-label">작은 입력</span>
                <input type="text" class="form-input form-input-sm" value="Small">
            </label>
            <label>
                <span class="form-label">큰 입력</span>
                <input type="text" class="form-input form-input-lg" value="Large">
            </label>
            <label>
                <span class="form-label">선택</span>
                <select class="form-select">
                    <option>기본 옵션</option>
                    <option>보조 옵션</option>
                </select>
            </label>
            <label class="admin-design-system-form-wide">
                <span class="form-label">텍스트 영역</span>
                <textarea class="form-textarea" rows="4">공통 textarea 스타일입니다.</textarea>
            </label>
            <label>
                <span class="form-label">파일</span>
                <input type="file" class="form-input">
            </label>
        </div>
        <div class="admin-design-system-inline admin-design-system-control-row">
            <label class="af-check form-label">
                <input type="checkbox" class="form-checkbox" checked>
                체크박스
            </label>
            <label class="af-check form-label">
                <input type="radio" name="design_system_radio" class="form-radio" checked>
                라디오 A
            </label>
            <label class="af-check form-label">
                <input type="radio" name="design_system_radio" class="form-radio">
                라디오 B
            </label>
            <label class="af-check form-label">
                <input type="checkbox" class="form-switch" checked>
                스위치
            </label>
        </div>
    </div>

    <div id="ds-data" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>카드, 탭, 테이블</h2>
            <p>목록 화면과 상세 화면에서 자주 쓰는 컨테이너와 데이터 표시를 확인합니다.</p>
        </div>
        <div class="admin-design-system-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">카드 제목</h3>
                    <span class="badge badge-label btn-soft-primary">Preview</span>
                </div>
                <div class="card-body">
                    <p>카드 본문은 짧은 설명, 상태 요약, 보조 액션을 담습니다.</p>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-sm btn-surface-default-soft">보조</button>
                    <button type="button" class="btn btn-sm btn-solid-primary">확인</button>
                </div>
            </div>
            <div>
                <nav class="tab-nav-bordered-tight" aria-label="탭 미리보기">
                    <button type="button" class="tab-trigger-underline active">전체</button>
                    <button type="button" class="tab-trigger-underline">활성</button>
                    <button type="button" class="tab-trigger-underline">보류</button>
                </nav>
                <div class="tab-panel-space">
                    <p>탭 본문 간격과 활성 상태를 확인합니다.</p>
                </div>
            </div>
        </div>
        <div class="table-wrapper admin-design-system-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>항목</th>
                        <th>상태</th>
                        <th>버전</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>common.css</td>
                        <td><span class="badge badge-label btn-soft-success">정상</span></td>
                        <td>현재</td>
                        <td><button type="button" class="btn btn-sm btn-outline-primary">보기</button></td>
                    </tr>
                    <tr>
                        <td>admin.css</td>
                        <td><span class="badge badge-label btn-soft-info">참조</span></td>
                        <td>현재</td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary">보기</button></td>
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

    <div id="ds-feedback" class="admin-design-system-panel">
        <div class="admin-design-system-panel-header">
            <h2>피드백과 모달</h2>
            <p>관리자 알림 메시지와 공통 모달 구조의 시각 상태를 확인합니다.</p>
        </div>
        <div class="admin-design-system-grid">
            <div class="admin-design-system-feedback-stack">
                <div class="admin-flash-message admin-flash-message-success">
                    <strong>완료</strong>
                    <span>저장되었습니다.</span>
                </div>
                <div class="admin-flash-message admin-flash-message-error">
                    <strong>확인 필요</strong>
                    <span>필수 값을 확인해주세요.</span>
                </div>
            </div>
            <div class="admin-design-system-modal-sample">
                <div class="modal-content">
                    <div class="modal-header">
                        <strong class="modal-title">모달 제목</strong>
                        <button type="button" class="btn btn-sm btn-icon modal-close" aria-label="닫기">
                            <span class="close-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>모달 본문 영역입니다. 확인이 필요한 작업을 담습니다.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-surface-default-soft">취소</button>
                        <button type="button" class="btn btn-solid-primary">저장</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
