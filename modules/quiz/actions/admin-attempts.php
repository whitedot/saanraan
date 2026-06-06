<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/attempts', 'view');

$stmt = $pdo->query(
    'SELECT a.id, a.status, a.account_id, a.total_score, a.passed, a.submitted_at, a.updated_at, q.quiz_key, q.title,
            g.status AS grant_status, g.reward_module, g.reward_amount, g.error_message, g.granted_at, g.failed_at
     FROM sr_quiz_attempts a
     INNER JOIN sr_quiz_sets q ON q.id = a.quiz_id
     LEFT JOIN sr_quiz_reward_grants g ON g.attempt_id = a.id
     ORDER BY a.updated_at DESC, a.id DESC
     LIMIT 100'
);
$attempts = $stmt->fetchAll();
?>
<div class="admin-card">
    <div class="admin-card-header">
        <h2>퀴즈 시도/보상 내역</h2>
    </div>
    <div class="admin-card-body">
        <?php if ($attempts === []): ?>
            <p class="admin-empty">기록된 퀴즈 시도가 없습니다.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>퀴즈</th>
                        <th>회원 ID</th>
                        <th>상태</th>
                        <th>점수</th>
                        <th>통과</th>
                        <th>보상</th>
                        <th>제출일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td><?php echo sr_e((string) $attempt['id']); ?></td>
                            <td><?php echo sr_e((string) $attempt['title']); ?></td>
                            <td><?php echo sr_e((string) $attempt['account_id']); ?></td>
                            <td><?php echo sr_e((string) $attempt['status']); ?></td>
                            <td><?php echo sr_e((string) ($attempt['total_score'] ?? '')); ?></td>
                            <td><?php echo ((int) ($attempt['passed'] ?? 0) === 1) ? '예' : '아니오'; ?></td>
                            <td>
                                <?php if ((string) ($attempt['grant_status'] ?? '') === ''): ?>
                                    -
                                <?php else: ?>
                                    <?php echo sr_e((string) $attempt['grant_status']); ?>
                                    <?php echo sr_e(' ' . (string) ($attempt['reward_module'] ?? '') . ' ' . (string) ($attempt['reward_amount'] ?? '')); ?>
                                    <?php if ((string) ($attempt['error_message'] ?? '') !== ''): ?>
                                        <br><small><?php echo sr_e((string) $attempt['error_message']); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sr_e((string) ($attempt['submitted_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
