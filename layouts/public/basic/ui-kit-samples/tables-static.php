<?php

$uiKitTableRows = [
    [
        'email' => 'ad***@2.com',
        'hash' => '9d36663b12b9c46cd57e7455ed64979f',
        'name' => '테스트',
        'status' => '정상',
        'status_class' => 'is-normal',
        'email_verified_at' => '2026-06-02 13:19:10',
        'last_login_at' => '2026-06-02 13:53:34',
        'sessions' => '1',
        'created_at' => '2026-06-02 13:19:10',
    ],
    [
        'email' => 'ad***@admin.com',
        'hash' => 'b838148e8455a19e7d9224fb6bb3b0b8',
        'name' => '회원261636',
        'status' => '정상',
        'status_class' => 'is-normal',
        'email_verified_at' => '2026-05-20 17:54:02',
        'last_login_at' => '2026-06-02 09:40:54',
        'sessions' => '25',
        'created_at' => '2026-05-20 17:54:02',
    ],
];
?>
<div class="ui-kit-sample-section" data-ui-kit-sample="tables-static">
    <div class="container-fluid">
        <section class="card table-card">
            <div class="card-header">
                <h4 class="card-title">회원 목록</h4>
                <button type="button" class="btn btn-sm btn-outline-secondary">새 회원 추가</button>
            </div>
            <p class="table-summary">전체 <strong>2</strong>건 중 <strong>1-2</strong>건 표시</p>
            <div class="table-wrapper">
                <table class="table table-list">
                    <caption class="sr-only">회원 목록 테이블</caption>
                    <thead>
                        <tr>
                            <th scope="col">
                                <span class="table-sort-header">
                                    <span class="table-sort-label">이메일 / 공개 해시</span>
                                    <span class="table-sort-button-group" role="group" aria-label="이메일 / 공개 해시 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="이메일 / 공개 해시 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-light" aria-label="이메일 / 공개 해시 내림차순 정렬"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col">
                                <span class="table-sort-header">
                                    <span class="table-sort-label">공개 이름</span>
                                    <span class="table-sort-button-group" role="group" aria-label="공개 이름 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="공개 이름 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-light" aria-label="공개 이름 내림차순 정렬"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col">
                                <span class="table-sort-header">
                                    <span class="table-sort-label">상태</span>
                                    <span class="table-sort-button-group" role="group" aria-label="상태 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="상태 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-light" aria-label="상태 내림차순 정렬"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col">
                                <span class="table-sort-header">
                                    <span class="table-sort-label">이메일 인증</span>
                                    <span class="table-sort-button-group" role="group" aria-label="이메일 인증 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="이메일 인증 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-light" aria-label="이메일 인증 내림차순 정렬"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col">
                                <span class="table-sort-header">
                                    <span class="table-sort-label">최근 로그인</span>
                                    <span class="table-sort-button-group" role="group" aria-label="최근 로그인 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="최근 로그인 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-light" aria-label="최근 로그인 내림차순 정렬"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col" class="table-align-end">
                                <span class="table-sort-header table-sort-header-end">
                                    <span class="table-sort-label">활성 세션</span>
                                    <span class="table-sort-button-group" role="group" aria-label="활성 세션 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="활성 세션 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-light" aria-label="활성 세션 내림차순 정렬"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col">
                                <span class="table-sort-header">
                                    <span class="table-sort-label">생성일</span>
                                    <span class="table-sort-button-group" role="group" aria-label="생성일 정렬 방향">
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-start btn-solid-light" aria-label="생성일 오름차순 정렬"><?php echo sr_material_icon_html('arrow_upward'); ?></a>
                                        <a href="#" class="btn btn-sm table-sort-button btn-group-end btn-solid-primary" aria-label="생성일 내림차순 정렬" aria-current="true"><?php echo sr_material_icon_html('arrow_downward'); ?></a>
                                    </span>
                                </span>
                            </th>
                            <th scope="col" class="table-align-end">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uiKitTableRows as $uiKitTableRow) { ?>
                            <tr>
                                <td class="table-break">
                                    <strong><?php echo sr_e($uiKitTableRow['email']); ?></strong>
                                    <span class="table-meta"><?php echo sr_e($uiKitTableRow['hash']); ?></span>
                                </td>
                                <td class="table-nowrap"><?php echo sr_e($uiKitTableRow['name']); ?></td>
                                <td class="table-nowrap"><span class="table-status <?php echo sr_e($uiKitTableRow['status_class']); ?>"><?php echo sr_e($uiKitTableRow['status']); ?></span></td>
                                <td class="table-nowrap"><?php echo sr_e($uiKitTableRow['email_verified_at']); ?></td>
                                <td class="table-nowrap"><?php echo sr_e($uiKitTableRow['last_login_at']); ?></td>
                                <td class="table-nowrap table-align-end"><?php echo sr_e($uiKitTableRow['sessions']); ?></td>
                                <td class="table-nowrap"><?php echo sr_e($uiKitTableRow['created_at']); ?></td>
                                <td class="table-actions-cell">
                                    <div class="table-row-actions">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></button>
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger" aria-label="삭제" title="삭제"><?php echo sr_material_icon_html('delete'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
