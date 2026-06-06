<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'view');

$stmt = $pdo->query(
    'SELECT id, quiz_key, title, status, quiz_mode, scoring_model, reward_enabled, updated_at
     FROM sr_quiz_sets
     ORDER BY updated_at DESC, id DESC
     LIMIT 100'
);
$quizzes = $stmt->fetchAll();
?>
<div class="admin-card">
    <div class="admin-card-header">
        <h2>퀴즈 관리</h2>
    </div>
    <div class="admin-card-body">
        <?php if ($quizzes === []): ?>
            <p class="admin-empty">등록된 퀴즈가 없습니다.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>제목</th>
                        <th>상태</th>
                        <th>유형</th>
                        <th>보상</th>
                        <th>수정일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                        <tr>
                            <td><?php echo sr_e((string) $quiz['quiz_key']); ?></td>
                            <td><?php echo sr_e((string) $quiz['title']); ?></td>
                            <td><?php echo sr_e((string) $quiz['status']); ?></td>
                            <td><?php echo sr_e((string) $quiz['quiz_mode'] . ' / ' . (string) $quiz['scoring_model']); ?></td>
                            <td><?php echo ((int) ($quiz['reward_enabled'] ?? 0) === 1) ? '사용' : '미사용'; ?></td>
                            <td><?php echo sr_e((string) $quiz['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
