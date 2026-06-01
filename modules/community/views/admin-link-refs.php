<?php

$adminPageTitle = '커뮤니티 링크 카드 점검';
$adminPageSubtitle = '커뮤니티 게시글 본문에 배치된 외부 모듈 링크 카드와 깨진 대상을 확인합니다.';
$adminContainerClass = 'admin-community-link-refs admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/link-refs')); ?>" class="btn btn-solid-light">전체</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/link-refs?status=broken')); ?>" class="btn btn-solid-light">깨진 카드</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/posts')); ?>" class="btn btn-solid-light">게시글 목록</a>
    </div>
</div>

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <h2 class="card-title">링크 카드 참조</h2>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">커뮤니티 링크 카드 참조 목록</caption>
            <thead>
                <tr>
                    <th>게시글</th>
                    <th>대상</th>
                    <th>상태</th>
                    <th>표시</th>
                    <th class="text-end">액션</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($linkRefs === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state">표시할 링크 카드 참조가 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($linkRefs as $linkRef) { ?>
                        <?php $target = is_array($linkRef['target'] ?? null) ? $linkRef['target'] : []; ?>
                        <tr>
                            <td class="admin-table-break">
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) $linkRef['post_id']))); ?>"><?php echo sr_e((string) $linkRef['post_title']); ?></a>
                                <small><?php echo sr_e((string) $linkRef['board_title']); ?> / <?php echo sr_e((string) $linkRef['post_status']); ?></small>
                            </td>
                            <td class="admin-table-break">
                                <?php echo sr_e((string) $linkRef['target_module'] . ':' . (string) $linkRef['target_entity_type'] . '#' . (string) $linkRef['target_entity_id']); ?>
                                <small><?php echo sr_e((string) ($target['title'] ?? '')); ?></small>
                            </td>
                            <td><span class="admin-status <?php echo !empty($linkRef['is_broken']) ? 'is-left' : 'is-normal'; ?>"><?php echo !empty($linkRef['is_broken']) ? '깨짐' : '정상'; ?></span></td>
                            <td><?php echo sr_e((string) ($linkRef['variant'] ?? 'compact')); ?> / <?php echo sr_e((string) ($linkRef['slot_key'] ?? 'body')); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a class="btn btn-sm btn-solid-light" href="<?php echo sr_e(sr_url('/community/edit?id=' . rawurlencode((string) (int) $linkRef['post_id']))); ?>">본문 수정</a>
                                    <?php if (!empty($target['url'])) { ?>
                                        <a class="btn btn-sm btn-solid-light" href="<?php echo sr_e(sr_url((string) $target['url'])); ?>">대상 보기</a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
